<?php
$pageTitle = 'Take Quiz';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gemini.php';

$db      = getDB();
$userId  = $_SESSION['user_id'];
$quizId  = (int)($_GET['id'] ?? $_POST['quiz_id'] ?? 0);

if ($quizId <= 0) {
    header('Location: quiz-list.php');
    exit;
}

$quizStmt = $db->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = 1");
$quizStmt->execute([$quizId]);
$quiz = $quizStmt->fetch();

if (!$quiz) {
    header('Location: quiz-list.php');
    exit;
}

// ── Scheduling enforcement ────────────────────────────
$now = new DateTime();
if (!empty($quiz['starts_at'])) {
    $starts = new DateTime($quiz['starts_at']);
    if ($now < $starts) {
        $pageTitle = 'Quiz Not Yet Available';
        require_once __DIR__ . '/../includes/header.php';
        echo '<div class="quiz-wrapper"><div class="card" style="text-align:center;padding:40px">';
        echo '<div class="page-title" style="font-size:22px;margin-bottom:10px">⏳ Quiz Not Started Yet</div>';
        echo '<p style="color:var(--muted)">This quiz opens on <strong>' . htmlspecialchars(date('d M Y, h:i A', $starts->getTimestamp())) . '</strong>.</p>';
        echo '<a href="quiz-list.php" class="btn btn-outline" style="margin-top:18px">Browse other quizzes</a>';
        echo '</div></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}
if (!empty($quiz['ends_at'])) {
    $ends = new DateTime($quiz['ends_at']);
    if ($now > $ends) {
        $pageTitle = 'Quiz Expired';
        require_once __DIR__ . '/../includes/header.php';
        echo '<div class="quiz-wrapper"><div class="card" style="text-align:center;padding:40px">';
        echo '<div class="page-title" style="font-size:22px;margin-bottom:10px">🔒 Quiz Expired</div>';
        echo '<p style="color:var(--muted)">This quiz closed on <strong>' . htmlspecialchars(date('d M Y, h:i A', $ends->getTimestamp())) . '</strong>.</p>';
        echo '<a href="quiz-list.php" class="btn btn-outline" style="margin-top:18px">Browse other quizzes</a>';
        echo '</div></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }
}

$questionsStmt = $db->prepare("SELECT id, question_text, marks FROM questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC");
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

if (!empty($questions)) {
    shuffle($questions);
}

// ── Block retakes ──────────────────────────────────────
$existingStmt = $db->prepare("SELECT id FROM attempts WHERE user_id = ? AND quiz_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1");
$existingStmt->execute([$userId, $quizId]);
$existingAttemptId = $existingStmt->fetchColumn();

if ($existingAttemptId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: result.php?attempt=' . $existingAttemptId . '&already=1');
    exit;
}

$optionsStmt = $db->prepare("SELECT id, question_id, option_text, is_correct FROM options WHERE question_id = ?");

// ── Handle tab-switch AJAX ping ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tab_switch_ping'])) {
    header('Content-Type: application/json');
    $pendingAttemptId = (int)($_POST['attempt_id'] ?? 0);
    if ($pendingAttemptId > 0) {
        // Check it belongs to this user
        $chk = $db->prepare("SELECT id FROM attempts WHERE id = ? AND user_id = ? AND is_completed = 0");
        $chk->execute([$pendingAttemptId, $userId]);
        if ($chk->fetchColumn()) {
            $db->prepare("UPDATE attempts SET tab_switch_count = tab_switch_count + 1 WHERE id = ?")->execute([$pendingAttemptId]);
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Handle submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['tab_switch_ping'])) {
    verifyCsrf();

    $dupeCheck = $db->prepare("SELECT id FROM attempts WHERE user_id = ? AND quiz_id = ? AND is_completed = 1 LIMIT 1");
    $dupeCheck->execute([$userId, $quizId]);
    if ($already = $dupeCheck->fetchColumn()) {
        header('Location: result.php?attempt=' . $already . '&already=1');
        exit;
    }

    if (!checkRateLimit('quiz_submit_' . $userId, 10, 60)) {
        header('Location: quiz-list.php');
        exit;
    }

    $answers       = $_POST['answers'] ?? [];
    $timeTaken     = (int)($_POST['time_taken'] ?? 0);
    $tabSwitches   = (int)($_POST['tab_switch_count'] ?? 0);
    $negativeMarking = (float)($quiz['negative_marking'] ?? 0.00);

    $rawScore    = 0.0;
    $deductions  = 0.0;
    $totalMarks  = 0;

    $db->beginTransaction();

    $attemptStmt = $db->prepare("
        INSERT INTO attempts (user_id, quiz_id, score, total_marks, time_taken_seconds, tab_switch_count, is_completed, submitted_at)
        VALUES (?, ?, 0, 0, ?, ?, 1, NOW())
    ");
    $attemptStmt->execute([$userId, $quizId, $timeTaken, $tabSwitches]);
    $attemptId = $db->lastInsertId();

    $answerStmt = $db->prepare("
        INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct)
        VALUES (?, ?, ?, ?)
    ");

    $wrongAnswerItems = [];

    foreach ($questions as $q) {
        $totalMarks += $q['marks'];
        $selectedId = isset($answers[$q['id']]) ? (int)$answers[$q['id']] : null;

        $isCorrect = false;
        if ($selectedId) {
            $check = $db->prepare("SELECT is_correct FROM options WHERE id = ? AND question_id = ?");
            $check->execute([$selectedId, $q['id']]);
            $isCorrect = (bool)$check->fetchColumn();
        }

        if ($isCorrect) {
            $rawScore += $q['marks'];
        } elseif ($selectedId && $negativeMarking > 0) {
            $deductions += ($q['marks'] * $negativeMarking);
        }

        $answerStmt->execute([$attemptId, $q['id'], $selectedId ?: null, $isCorrect ? 1 : 0]);

        // Collect wrong/skipped for AI explanation
        if (!$isCorrect) {
            // Fetch correct option text
            $correctOpt = $db->prepare("SELECT option_text FROM options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
            $correctOpt->execute([$q['id']]);
            $correctText = $correctOpt->fetchColumn() ?? 'N/A';

            // Fetch selected option text
            $selectedText = 'None / Skipped';
            if ($selectedId) {
                $selOpt = $db->prepare("SELECT option_text FROM options WHERE id = ? LIMIT 1");
                $selOpt->execute([$selectedId]);
                $selectedText = $selOpt->fetchColumn() ?: 'None / Skipped';
            }

            $wrongAnswerItems[] = [
                'id'             => $q['id'],
                'question'       => $q['question_text'],
                'correct_answer' => $correctText,
                'user_answer'    => $selectedText,
            ];
        }
    }

    $finalScore = max(0, $rawScore - $deductions);

    $updateStmt = $db->prepare("UPDATE attempts SET score = ?, total_marks = ? WHERE id = ?");
    $updateStmt->execute([$finalScore, $totalMarks, $attemptId]);

    $db->commit();

    // ── Generate AI explanations for wrong answers ────
    if (!empty($wrongAnswerItems)) {
        $explanations = generateAnswerExplanations($wrongAnswerItems);
        if (!empty($explanations)) {
            $updExp = $db->prepare("UPDATE attempt_answers SET explanation = ? WHERE attempt_id = ? AND question_id = ?");
            foreach ($explanations as $qid => $exp) {
                $updExp->execute([$exp, $attemptId, $qid]);
            }
        }
    }

    // ── Update daily streak ───────────────────────────
    updateUserStreak($userId, $db);

    header('Location: result.php?attempt=' . $attemptId);
    exit;
}

// ── Create a draft attempt row to track tab switches ─
// (a placeholder row is inserted without is_completed=1 so the JS can ping it)
$draftStmt = $db->prepare("
    INSERT INTO attempts (user_id, quiz_id, score, total_marks, time_taken_seconds, tab_switch_count, is_completed)
    VALUES (?, ?, 0, 0, 0, 0, 0)
");
$draftStmt->execute([$userId, $quizId]);
$draftAttemptId = $db->lastInsertId();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="quiz-wrapper">
    <div class="quiz-header">
        <div>
            <div class="page-title"><?= htmlspecialchars($quiz['title']) ?></div>
            <div class="page-subtitle"><?= count($questions) ?> questions<?= ($quiz['negative_marking'] ?? 0) > 0 ? ' · <span style="color:var(--danger)">−' . number_format($quiz['negative_marking'], 2) . ' per wrong answer</span>' : '' ?></div>
        </div>
        <div class="quiz-timer" id="timer"><?= gmdate('i:s', $quiz['time_limit_seconds']) ?></div>
    </div>

    <!-- Tab switch anti-cheat warning banner -->
    <div id="tabSwitchBanner" class="alert alert-warning" style="display:none">
        ⚠️ <strong>Tab switch detected!</strong> Switching tabs is logged. <span id="tabSwitchCount">0</span> warning(s) recorded.
    </div>

    <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width:0%"></div>
    </div>

    <?php if (empty($questions)): ?>
        <div class="card">
            <p style="color:var(--muted)">This quiz has no questions yet. Please check back later.</p>
        </div>
    <?php else: ?>
    <form method="POST" id="quizForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <input type="hidden" name="time_taken" id="timeTakenInput" value="0">
        <input type="hidden" name="tab_switch_count" id="tabSwitchInput" value="0">

        <?php foreach ($questions as $i => $q):
            $optionsStmt->execute([$q['id']]);
            $options = $optionsStmt->fetchAll();
            shuffle($options);
        ?>
        <div class="question-card" data-question>
            <div class="question-number">Question <?= $i + 1 ?> of <?= count($questions) ?> · <?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?></div>
            <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>

            <?php foreach ($options as $opt): ?>
            <label class="option-label">
                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $opt['id'] ?>" required>
                <span><?= htmlspecialchars($opt['option_text']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px" id="submitBtn">
            Submit Quiz
        </button>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    // ── Timer ────────────────────────────────────────
    let secondsLeft = <?= (int)$quiz['time_limit_seconds'] ?>;
    const totalSeconds = secondsLeft;
    const timerEl  = document.getElementById('timer');
    const fillEl   = document.getElementById('progressFill');
    const form     = document.getElementById('quizForm');
    const timeInput = document.getElementById('timeTakenInput');

    function formatTime(s) {
        const m = Math.floor(s / 60).toString().padStart(2, '0');
        const sec = (s % 60).toString().padStart(2, '0');
        return m + ':' + sec;
    }

    const interval = setInterval(() => {
        secondsLeft--;
        if (timerEl) timerEl.textContent = formatTime(Math.max(secondsLeft, 0));
        if (fillEl) fillEl.style.width = (((totalSeconds - secondsLeft) / totalSeconds) * 100) + '%';

        if (secondsLeft <= 60 && secondsLeft > 0) {
            if (timerEl) {
                timerEl.style.background = 'var(--danger-bg)';
                timerEl.style.color = 'var(--danger)';
                timerEl.style.opacity = timerEl.style.opacity === '0.4' ? '1' : '0.4';
            }
            if (secondsLeft === 60 || secondsLeft === 30 || secondsLeft === 10) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.value = secondsLeft === 10 ? 880 : 660;
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
                } catch(e) {}
            }
        } else if (secondsLeft > 60 && timerEl) {
            timerEl.style.opacity = '1';
        }

        if (secondsLeft <= 0) {
            clearInterval(interval);
            if (timeInput) timeInput.value = totalSeconds;
            if (form) {
                form.querySelectorAll('[required]').forEach(el => el.removeAttribute('required'));
                form.submit();
            }
        }
    }, 1000);

    if (form) {
        form.addEventListener('submit', () => {
            const taken = totalSeconds - Math.max(secondsLeft, 0);
            if (timeInput) timeInput.value = taken;
            clearInterval(interval);
        });
    }

    // ── Tab Switch Anti-Cheat ─────────────────────────
    const draftAttemptId = <?= (int)$draftAttemptId ?>;
    const tabBanner = document.getElementById('tabSwitchBanner');
    const tabCountEl = document.getElementById('tabSwitchCount');
    const tabInput = document.getElementById('tabSwitchInput');
    let switchCount = 0;

    function recordTabSwitch() {
        switchCount++;
        if (tabInput) tabInput.value = switchCount;
        if (tabCountEl) tabCountEl.textContent = switchCount;
        if (tabBanner) tabBanner.style.display = 'block';

        // Ping backend to log it
        const fd = new FormData();
        fd.append('tab_switch_ping', '1');
        fd.append('attempt_id', draftAttemptId);
        fd.append('csrf_token', '<?= csrfToken() ?>');
        fetch(window.location.href, { method: 'POST', body: fd }).catch(() => {});
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) recordTabSwitch();
    });

    window.addEventListener('blur', () => {
        if (document.visibilityState === 'visible') recordTabSwitch();
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>