<?php
$pageTitle = 'Adaptive Quiz';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gemini.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

// ── Handle AJAX next question / answer evaluation ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // Evaluate answer & decide next difficulty
    if ($action === 'submit_answer') {
        $questionId = (int)$_POST['question_id'];
        $selectedId = (int)$_POST['option_id'];
        $categoryId = (int)$_POST['category_id'];
        $answeredIds = $_POST['answered_ids'] ?? [];
        if (!is_array($answeredIds)) $answeredIds = json_decode($answeredIds, true) ?: [];

        // Check correctness
        $check = $db->prepare("SELECT is_correct FROM options WHERE id = ? AND question_id = ?");
        $check->execute([$selectedId, $questionId]);
        $isCorrect = (bool)$check->fetchColumn();

        // Update analytics
        $db->prepare("
            UPDATE questions
            SET times_attempted = times_attempted + 1,
                times_correct = times_correct + ?
            WHERE id = ?
        ")->execute([$isCorrect ? 1 : 0, $questionId]);

        // Fetch correct option & explanation
        $correctOpt = $db->prepare("SELECT id, option_text FROM options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
        $correctOpt->execute([$questionId]);
        $correctRow = $correctOpt->fetch();

        // Current difficulty & streak
        $curLevel = in_array($_POST['current_level'], ['easy','medium','hard']) ? $_POST['current_level'] : 'medium';
        $consecutiveCorrect = (int)($_POST['consecutive_correct'] ?? 0);
        $consecutiveWrong   = (int)($_POST['consecutive_wrong'] ?? 0);

        if ($isCorrect) {
            $consecutiveCorrect++;
            $consecutiveWrong = 0;
            // Step UP if 2 correct in a row
            if ($consecutiveCorrect >= 2) {
                if ($curLevel === 'easy') $nextLevel = 'medium';
                elseif ($curLevel === 'medium') $nextLevel = 'hard';
                else $nextLevel = 'hard';
                $consecutiveCorrect = 0; // reset streak after step
            } else {
                $nextLevel = $curLevel;
            }
        } else {
            $consecutiveWrong++;
            $consecutiveCorrect = 0;
            // Step DOWN if wrong
            if ($curLevel === 'hard') $nextLevel = 'medium';
            elseif ($curLevel === 'medium') $nextLevel = 'easy';
            else $nextLevel = 'easy';
        }

        $answeredIds[] = $questionId;

        // Fetch next question matching target difficulty & category
        $nextQ = null;
        $levelsToTry = [$nextLevel, 'medium', 'easy', 'hard'];

        foreach ($levelsToTry as $tryLevel) {
            $placeholders = empty($answeredIds) ? '' : ' AND q.id NOT IN (' . implode(',', array_map('intval', $answeredIds)) . ')';
            $catFilter = $categoryId > 0 ? " AND qu.category_id = {$categoryId}" : "";

            $qStmt = $db->query("
                SELECT q.id, q.question_text, q.marks, q.difficulty, q.tag, qu.title AS quiz_title
                FROM questions q
                JOIN quizzes qu ON qu.id = q.quiz_id
                WHERE q.deleted_at IS NULL AND qu.deleted_at IS NULL AND q.difficulty = '{$tryLevel}' {$catFilter} {$placeholders}
                ORDER BY RAND()
                LIMIT 1
            ");
            $nextQ = $qStmt->fetch();
            if ($nextQ) {
                $nextLevel = $tryLevel;
                break;
            }
        }

        $nextOptions = [];
        if ($nextQ) {
            $optStmt = $db->prepare("SELECT id, option_text FROM options WHERE question_id = ? ORDER BY RAND()");
            $optStmt->execute([$nextQ['id']]);
            $nextOptions = $optStmt->fetchAll();
        }

        // Generate AI explanation if wrong
        $explanation = null;
        if (!$isCorrect) {
            $qInfo = $db->prepare("SELECT question_text FROM questions WHERE id = ?");
            $qInfo->execute([$questionId]);
            $qText = $qInfo->fetchColumn();

            $selOptText = $db->prepare("SELECT option_text FROM options WHERE id = ?");
            $selOptText->execute([$selectedId]);
            $selText = $selOptText->fetchColumn() ?: 'Skipped';

            $exps = generateAnswerExplanations([[
                'id' => $questionId,
                'question' => $qText,
                'correct_answer' => $correctRow['option_text'] ?? '',
                'user_answer' => $selText
            ]]);
            $explanation = $exps[$questionId] ?? null;
        }

        // Update streak
        updateUserStreak($userId, $db);

        echo json_encode([
            'is_correct'          => $isCorrect,
            'correct_option_id'   => (int)($correctRow['id'] ?? 0),
            'correct_text'        => $correctRow['option_text'] ?? '',
            'explanation'         => $explanation,
            'next_level'          => $nextLevel,
            'consecutive_correct' => $consecutiveCorrect,
            'consecutive_wrong'   => $consecutiveWrong,
            'answered_ids'        => $answeredIds,
            'next_question'       => $nextQ ? [
                'id'            => $nextQ['id'],
                'question_text' => $nextQ['question_text'],
                'marks'         => $nextQ['marks'],
                'difficulty'    => $nextQ['difficulty'],
                'tag'           => $nextQ['tag'],
                'options'       => $nextOptions
            ] : null
        ]);
        exit;
    }

    // Start session / fetch initial question
    if ($action === 'start_session') {
        $categoryId = (int)$_POST['category_id'];
        $startLevel = 'medium';

        $catFilter = $categoryId > 0 ? " AND qu.category_id = {$categoryId}" : "";
        $qStmt = $db->query("
            SELECT q.id, q.question_text, q.marks, q.difficulty, q.tag, qu.title AS quiz_title
            FROM questions q
            JOIN quizzes qu ON qu.id = q.quiz_id
            WHERE q.deleted_at IS NULL AND qu.deleted_at IS NULL {$catFilter}
            ORDER BY CASE WHEN q.difficulty = 'medium' THEN 1 WHEN q.difficulty = 'easy' THEN 2 ELSE 3 END, RAND()
            LIMIT 1
        ");
        $firstQ = $qStmt->fetch();

        $options = [];
        if ($firstQ) {
            $optStmt = $db->prepare("SELECT id, option_text FROM options WHERE question_id = ? ORDER BY RAND()");
            $optStmt->execute([$firstQ['id']]);
            $options = $optStmt->fetchAll();
        }

        echo json_encode([
            'question' => $firstQ ? [
                'id'            => $firstQ['id'],
                'question_text' => $firstQ['question_text'],
                'marks'         => $firstQ['marks'],
                'difficulty'    => $firstQ['difficulty'],
                'tag'           => $firstQ['tag'],
                'options'       => $options
            ] : null
        ]);
        exit;
    }
}

$categories = $db->query("SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">🎯 Adaptive Quiz Engine</div>
    <div class="page-subtitle">Real-time intelligent questions that dynamically scale with your accuracy</div>
</div>

<!-- Setup screen -->
<div class="card" id="setupCard" style="max-width:600px;margin:0 auto 24px">
    <div class="card-title">Choose your Category</div>
    <p style="color:var(--muted);font-size:13.5px;margin-bottom:18px">
        The engine automatically adapts difficulty based on your answers:
        <br>• <strong>2 consecutive correct</strong> &rarr; Level steps up (Easy &rarr; Medium &rarr; Hard)
        <br>• <strong>1 incorrect</strong> &rarr; Level steps down to reinforce concepts
    </p>

    <div class="form-group">
        <label>Select Subject / Category</label>
        <select id="adaptiveCategorySelect" style="height:42px">
            <option value="0">🌐 All Categories (Mixed Topics)</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="button" id="startAdaptiveBtn" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px;font-size:15px">
        Start Adaptive Quiz
    </button>
</div>

<!-- Active Adaptive Quiz Container -->
<div id="quizContainer" style="display:none;max-width:680px;margin:0 auto">
    <!-- Live Mastery Meter -->
    <div class="card" style="margin-bottom:16px;padding:16px 20px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <div style="font-size:13px;font-weight:600">
                🎯 Current Mastery: <span id="masteryBadge" class="badge badge-info">Intermediate (Medium)</span>
            </div>
            <div style="font-size:12.5px;color:var(--muted)">
                Score: <strong id="liveScore" style="color:var(--accent)">0</strong> | Questions: <strong id="questionCounter">1</strong>/10
            </div>
        </div>
        <div class="progress-bar" style="height:8px;margin:0">
            <div class="progress-fill" id="masteryProgress" style="width:50%;background:var(--accent)"></div>
        </div>
    </div>

    <!-- Question Card -->
    <div class="card" id="questionBox" style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <div class="question-number" id="qNumberLabel">Question 1</div>
            <div style="display:flex;gap:6px">
                <span id="diffBadge" class="badge badge-info">Medium</span>
                <span id="tagBadge" class="badge" style="display:none;background:var(--accent-light);color:var(--accent)"></span>
            </div>
        </div>

        <div class="question-text" id="qText" style="font-size:16px;font-weight:600;margin-bottom:18px"></div>

        <div id="optionsWrap"></div>

        <!-- Explanation card -->
        <div id="feedbackBox" style="display:none;margin-top:16px;padding:14px;border-radius:8px"></div>

        <button type="button" id="nextQBtn" class="btn btn-primary" style="display:none;width:100%;justify-content:center;margin-top:16px;padding:11px">
            Next Question &rarr;
        </button>
    </div>
</div>

<!-- Completion Card -->
<div class="card" id="summaryCard" style="display:none;max-width:600px;margin:0 auto;text-align:center;padding:36px">
    <div style="font-size:44px;margin-bottom:10px">🏆</div>
    <div class="page-title" style="font-size:22px">Adaptive Session Complete!</div>
    <div id="finalMasteryText" style="font-size:15px;color:var(--muted);margin:10px 0 20px"></div>

    <div style="display:flex;gap:16px;justify-content:center;margin-bottom:24px">
        <div style="background:var(--accent-light);border-radius:10px;padding:14px 20px">
            <div style="font-size:12px;color:var(--muted)">Accuracy</div>
            <div id="accuracyText" style="font-size:22px;font-weight:700;color:var(--accent)">0%</div>
        </div>
        <div style="background:var(--success-bg);border-radius:10px;padding:14px 20px">
            <div style="font-size:12px;color:var(--muted)">Correct</div>
            <div id="correctCountText" style="font-size:22px;font-weight:700;color:var(--success)">0</div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 20px">
            <div style="font-size:12px;color:var(--muted)">Peak Level</div>
            <div id="peakLevelText" style="font-size:20px;font-weight:700">Medium</div>
        </div>
    </div>

    <div style="display:flex;gap:10px;justify-content:center">
        <button type="button" onclick="location.reload()" class="btn btn-primary btn-sm">Try Another Session</button>
        <a href="quiz-list.php" class="btn btn-outline btn-sm">Browse Quizzes</a>
    </div>
</div>

<script>
(function () {
    const setupCard       = document.getElementById('setupCard');
    const quizContainer   = document.getElementById('quizContainer');
    const summaryCard     = document.getElementById('summaryCard');
    const startBtn        = document.getElementById('startAdaptiveBtn');
    const catSelect       = document.getElementById('adaptiveCategorySelect');

    const masteryBadge    = document.getElementById('masteryBadge');
    const masteryProgress = document.getElementById('masteryProgress');
    const liveScore       = document.getElementById('liveScore');
    const qCounter        = document.getElementById('questionCounter');

    const qNumberLabel    = document.getElementById('qNumberLabel');
    const diffBadge       = document.getElementById('diffBadge');
    const tagBadge        = document.getElementById('tagBadge');
    const qText           = document.getElementById('qText');
    const optionsWrap     = document.getElementById('optionsWrap');
    const feedbackBox     = document.getElementById('feedbackBox');
    const nextQBtn        = document.getElementById('nextQBtn');

    let currentQ = null;
    let categoryId = 0;
    let currentLevel = 'medium';
    let consecutiveCorrect = 0;
    let consecutiveWrong = 0;
    let answeredIds = [];
    let questionIndex = 0;
    const maxQuestions = 10;
    let totalScore = 0;
    let correctTotal = 0;
    let peakLevel = 'Medium';

    function updateMasteryUI() {
        if (currentLevel === 'easy') {
            masteryBadge.textContent = 'Apprentice (Easy)';
            masteryBadge.className = 'badge badge-warning';
            masteryProgress.style.width = '25%';
            masteryProgress.style.background = 'var(--warning,#BA7517)';
        } else if (currentLevel === 'medium') {
            masteryBadge.textContent = 'Intermediate (Medium)';
            masteryBadge.className = 'badge badge-info';
            masteryProgress.style.width = '55%';
            masteryProgress.style.background = 'var(--accent,#185FA5)';
        } else {
            masteryBadge.textContent = 'Master (Hard)';
            masteryBadge.className = 'badge badge-success';
            masteryProgress.style.width = '90%';
            masteryProgress.style.background = 'var(--success,#1D9E75)';
            peakLevel = 'Master (Hard)';
        }
    }

    function renderQuestion(q) {
        if (!q) {
            endSession();
            return;
        }

        currentQ = q;
        questionIndex++;
        qCounter.textContent = questionIndex;
        qNumberLabel.textContent = 'Question ' + questionIndex + ' of ' + maxQuestions;

        diffBadge.textContent = q.difficulty ? q.difficulty.toUpperCase() : 'MEDIUM';
        if (q.tag) {
            tagBadge.textContent = '🏷️ ' + q.tag;
            tagBadge.style.display = 'inline-block';
        } else {
            tagBadge.style.display = 'none';
        }

        qText.textContent = q.question_text;
        optionsWrap.innerHTML = '';
        feedbackBox.style.display = 'none';
        nextQBtn.style.display = 'none';

        q.options.forEach(opt => {
            const label = document.createElement('div');
            label.className = 'option-label';
            label.style.cursor = 'pointer';
            label.style.transition = 'all .15s ease';
            label.dataset.optionId = opt.id;
            label.textContent = opt.option_text;

            label.addEventListener('click', function () {
                if (feedbackBox.style.display === 'block') return; // already answered
                selectOption(opt.id, label);
            });

            optionsWrap.appendChild(label);
        });
    }

    function selectOption(optId, selectedEl) {
        const fd = new FormData();
        fd.append('action', 'submit_answer');
        fd.append('question_id', currentQ.id);
        fd.append('option_id', optId);
        fd.append('category_id', categoryId);
        fd.append('current_level', currentLevel);
        fd.append('consecutive_correct', consecutiveCorrect);
        fd.append('consecutive_wrong', consecutiveWrong);
        fd.append('answered_ids', JSON.stringify(answeredIds));

        fetch('adaptive-quiz.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                currentLevel        = data.next_level;
                consecutiveCorrect  = data.consecutive_correct;
                consecutiveWrong    = data.consecutive_wrong;
                answeredIds         = data.answered_ids;

                // Colorize options
                optionsWrap.querySelectorAll('.option-label').forEach(optEl => {
                    optEl.style.cursor = 'default';
                    if (parseInt(optEl.dataset.optionId) === data.correct_option_id) {
                        optEl.classList.add('correct');
                    } else if (optEl === selectedEl && !data.is_correct) {
                        optEl.classList.add('wrong');
                    }
                });

                if (data.is_correct) {
                    totalScore += (currentQ.marks || 1);
                    correctTotal++;
                    liveScore.textContent = totalScore;
                    feedbackBox.style.background = 'var(--success-bg,#EAF3DE)';
                    feedbackBox.style.color = 'var(--success,#1D9E75)';
                    feedbackBox.innerHTML = `<strong>✓ Correct!</strong> Level scaling to: <strong>${currentLevel.toUpperCase()}</strong>`;
                } else {
                    feedbackBox.style.background = 'var(--danger-bg,#FCEBEB)';
                    feedbackBox.style.color = 'var(--text,#111318)';
                    let html = `<div style="color:var(--danger,#E24B4A);font-weight:700;margin-bottom:6px">✗ Incorrect</div>`;
                    html += `Correct answer: <strong>${escapeHtml(data.correct_text)}</strong>`;
                    if (data.explanation) {
                        html += `<div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(0,0,0,.08);font-size:12.5px;color:var(--text)">💡 ${escapeHtml(data.explanation)}</div>`;
                    }
                    feedbackBox.innerHTML = html;
                }

                feedbackBox.style.display = 'block';
                updateMasteryUI();

                if (questionIndex >= maxQuestions || !data.next_question) {
                    nextQBtn.textContent = 'View Final Results 🏆';
                    nextQBtn.onclick = endSession;
                } else {
                    nextQBtn.textContent = 'Next Question →';
                    nextQBtn.onclick = () => renderQuestion(data.next_question);
                }
                nextQBtn.style.display = 'flex';
            });
    }

    function endSession() {
        quizContainer.style.display = 'none';
        summaryCard.style.display = 'block';

        const pct = Math.round((correctTotal / Math.max(1, questionIndex)) * 100);
        document.getElementById('accuracyText').textContent = pct + '%';
        document.getElementById('correctCountText').textContent = correctTotal + ' / ' + questionIndex;
        document.getElementById('peakLevelText').textContent = peakLevel;

        let msg = '';
        if (pct >= 80) msg = 'Outstanding performance! You mastered complex questions with high accuracy.';
        elseif (pct >= 50) msg = 'Great job! You adapted well through intermediate questions.';
        else msg = 'Good practice session! Review wrong answers and try again to level up.';
        document.getElementById('finalMasteryText').textContent = msg;
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    startBtn.addEventListener('click', function () {
        categoryId = parseInt(catSelect.value) || 0;
        startBtn.disabled = true;
        startBtn.textContent = 'Loading questions...';

        const fd = new FormData();
        fd.append('action', 'start_session');
        fd.append('category_id', categoryId);

        fetch('adaptive-quiz.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.question) {
                    setupCard.style.display = 'none';
                    quizContainer.style.display = 'block';
                    updateMasteryUI();
                    renderQuestion(data.question);
                } else {
                    alert('No questions available in this category yet. Please pick another category.');
                    startBtn.disabled = false;
                    startBtn.textContent = 'Start Adaptive Quiz';
                }
            })
            .catch(() => {
                alert('Failed to start session. Please try again.');
                startBtn.disabled = false;
                startBtn.textContent = 'Start Adaptive Quiz';
            });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
