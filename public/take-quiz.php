<?php
$pageTitle = 'Take Quiz';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

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

$questionsStmt = $db->prepare("SELECT id, question_text, marks FROM questions WHERE quiz_id = ? ORDER BY order_index ASC, id ASC");
$questionsStmt->execute([$quizId]);
$questions = $questionsStmt->fetchAll();

// Shuffle questions and options for each student session (prevents answer sharing)
if (!empty($questions)) {
    shuffle($questions);
}

// ── Block retakes: if already completed, send to their existing result ──
$existingStmt = $db->prepare("SELECT id FROM attempts WHERE user_id = ? AND quiz_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1");
$existingStmt->execute([$userId, $quizId]);
$existingAttemptId = $existingStmt->fetchColumn();

if ($existingAttemptId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: result.php?attempt=' . $existingAttemptId . '&already=1');
    exit;
}

$optionsStmt = $db->prepare("SELECT id, question_id, option_text, is_correct FROM options WHERE question_id = ?");

// ── Handle submission ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Double-check no completed attempt slipped in (e.g. duplicate form submit)
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

    $answers     = $_POST['answers'] ?? []; // [question_id => option_id]
    $timeTaken   = (int)($_POST['time_taken'] ?? 0);
    $score       = 0;
    $totalMarks  = 0;

    $db->beginTransaction();

    $attemptStmt = $db->prepare("
        INSERT INTO attempts (user_id, quiz_id, score, total_marks, time_taken_seconds, is_completed, submitted_at)
        VALUES (?, ?, 0, 0, ?, 1, NOW())
    ");
    $attemptStmt->execute([$userId, $quizId, $timeTaken]);
    $attemptId = $db->lastInsertId();

    $answerStmt = $db->prepare("
        INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct)
        VALUES (?, ?, ?, ?)
    ");

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
            $score += $q['marks'];
        }

        $answerStmt->execute([$attemptId, $q['id'], $selectedId ?: null, $isCorrect ? 1 : 0]);
    }

    $updateStmt = $db->prepare("UPDATE attempts SET score = ?, total_marks = ? WHERE id = ?");
    $updateStmt->execute([$score, $totalMarks, $attemptId]);

    $db->commit();

    header('Location: result.php?attempt=' . $attemptId);
    exit;
}

// ── Display quiz ──────────────────────────────────────
require_once __DIR__ . '/../includes/header.php';
?>

<div class="quiz-wrapper">
    <div class="quiz-header">
        <div>
            <div class="page-title"><?= htmlspecialchars($quiz['title']) ?></div>
            <div class="page-subtitle"><?= count($questions) ?> questions</div>
        </div>
        <div class="quiz-timer" id="timer"><?= gmdate('i:s', $quiz['time_limit_seconds']) ?></div>
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

        <?php foreach ($questions as $i => $q):
            $optionsStmt->execute([$q['id']]);
            $options = $optionsStmt->fetchAll();
            shuffle($options); // randomize option order
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
    let secondsLeft = <?= (int)$quiz['time_limit_seconds'] ?>;
    const totalSeconds = secondsLeft;
    const timerEl = document.getElementById('timer');
    const fillEl  = document.getElementById('progressFill');
    const form    = document.getElementById('quizForm');
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
                // Flash effect
                timerEl.style.opacity = timerEl.style.opacity === '0.4' ? '1' : '0.4';
            }
            // Beep using Web Audio API
            if (secondsLeft === 60 || secondsLeft === 30 || secondsLeft === 10) {
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.type = 'sine';
                    osc.frequency.value = secondsLeft === 10 ? 880 : 660;
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.4);
                } catch(e) {}
            }
        } else if (secondsLeft > 60 && timerEl) {
            timerEl.style.opacity = '1';
        }

        if (secondsLeft <= 0) {
            clearInterval(interval);
            if (timeInput) timeInput.value = totalSeconds;
            if (form) {
                // Skip "required" validation on time-up auto-submit
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
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>