<?php
$pageTitle = 'AI Generator';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gemini.php';

$db = getDB();
$error = '';
$success = '';
$preview = null;       // generated questions awaiting confirmation
$evalResult = null;    // descriptive answer evaluation result

$quizzes    = $db->query("SELECT id, title FROM quizzes ORDER BY created_at DESC")->fetchAll();
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// ── Step 1: Generate questions (preview only, not saved yet) ──────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_questions'])) {
    verifyCsrf();

    if (!checkRateLimit('ai_generate_admin', 20, 300)) {
        $error = 'Too many requests — wait a few minutes before generating again.';
    } else {
        $topic      = trim($_POST['topic'] ?? '');
        $count      = (int)($_POST['count'] ?? 5);
        $difficulty = $_POST['difficulty'] ?? 'medium';

        if ($topic === '') {
            $error = 'Please enter a topic.';
        } else {
            try {
                $preview = generateQuizQuestions($topic, $count, $difficulty);
                $_SESSION['ai_preview'] = $preview;
                $_SESSION['ai_topic']   = $topic;
                $success = count($preview) . ' question(s) generated. Review below, then save to a quiz.';
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// ── Step 2: Save previewed questions to a quiz ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_generated'])) {
    verifyCsrf();

    $preview = $_SESSION['ai_preview'] ?? [];
    $selected = $_POST['include'] ?? []; // indices to actually save
    $targetQuizId = (int)($_POST['target_quiz_id'] ?? 0);
    $newQuizTitle = trim($_POST['new_quiz_title'] ?? '');
    $newQuizCategory = (int)($_POST['new_quiz_category'] ?? 0);

    if (empty($preview)) {
        $error = 'No generated questions found — please generate again.';
    } elseif ($targetQuizId <= 0 && ($newQuizTitle === '' || $newQuizCategory <= 0)) {
        $error = 'Select an existing quiz or provide a title + category for a new one.';
    } elseif (empty($selected)) {
        $error = 'Select at least one question to save.';
    } else {
        $db->beginTransaction();

        if ($targetQuizId <= 0) {
            $insertQuiz = $db->prepare("
                INSERT INTO quizzes (category_id, title, description, time_limit_seconds, is_active, is_ai_generated)
                VALUES (?, ?, ?, 600, 1, 1)
            ");
            $insertQuiz->execute([$newQuizCategory, $newQuizTitle, 'AI-generated quiz on ' . ($_SESSION['ai_topic'] ?? 'a topic')]);
            $targetQuizId = $db->lastInsertId();
        }

        $nextOrderStmt = $db->prepare("SELECT COALESCE(MAX(order_index), 0) FROM questions WHERE quiz_id = ?");
        $nextOrderStmt->execute([$targetQuizId]);
        $nextOrder = (int)$nextOrderStmt->fetchColumn();

        $qStmt = $db->prepare("INSERT INTO questions (quiz_id, question_text, marks, order_index) VALUES (?, ?, ?, ?)");
        $oStmt = $db->prepare("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

        $savedCount = 0;
        foreach ($selected as $idx) {
            $idx = (int)$idx;
            if (!isset($preview[$idx])) continue;
            $q = $preview[$idx];

            $nextOrder++;
            $qStmt->execute([$targetQuizId, $q['question'], $q['marks'], $nextOrder]);
            $questionId = $db->lastInsertId();

            foreach ($q['options'] as $opt) {
                $oStmt->execute([$questionId, $opt['text'], $opt['correct'] ? 1 : 0]);
            }
            $savedCount++;
        }

        $db->prepare("UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks),0) FROM questions WHERE quiz_id = ?) WHERE id = ?")
           ->execute([$targetQuizId, $targetQuizId]);

        $db->commit();
        unset($_SESSION['ai_preview'], $_SESSION['ai_topic']);
        $success = "{$savedCount} question(s) saved successfully.";
        $preview = null;
    }
}

// ── Descriptive answer evaluation tool ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evaluate_answer'])) {
    verifyCsrf();

    $q  = trim($_POST['eval_question'] ?? '');
    $ma = trim($_POST['eval_model_answer'] ?? '');
    $sa = trim($_POST['eval_student_answer'] ?? '');

    if ($q === '' || $ma === '' || $sa === '') {
        $error = 'Fill in the question, model answer, and student answer.';
    } else {
        try {
            $evalResult = evaluateDescriptiveAnswer($q, $ma, $sa);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">AI Generator</div>
    <div class="page-subtitle">Powered by Gemini</div>
</div>

<div id="offlineBanner" class="alert alert-danger" style="display:none">
    You're currently offline. AI features need an internet connection — reconnect and try again.
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px">
    <div class="card-title">Generate quiz questions</div>
    <form method="POST" id="generateForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
            <label>Topic</label>
            <input type="text" name="topic" id="topicInput" placeholder="e.g. World capitals, Photosynthesis, JavaScript basics" required value="<?= htmlspecialchars($_SESSION['ai_topic'] ?? '') ?>">
            <div id="topicCategoryHint" style="font-size:12.5px;color:var(--accent);margin-top:6px;display:none"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>Number of questions</label>
                <input type="number" name="count" min="1" max="20" value="5">
            </div>
            <div class="form-group">
                <label>Difficulty</label>
                <select name="difficulty">
                    <option value="easy">Easy</option>
                    <option value="medium" selected>Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </div>
        </div>

        <button type="submit" name="generate_questions" id="generateBtn" class="btn btn-primary">Generate with AI</button>
    </form>
</div>

<?php if ($preview): ?>
<div class="card" style="margin-bottom:24px">
    <div class="card-title">Review generated questions</div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Uncheck any you don't want, then choose where to save them.</p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <?php foreach ($preview as $i => $q): ?>
        <div class="question-card">
            <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer">
                <input type="checkbox" name="include[]" value="<?= $i ?>" checked style="width:auto;margin-top:4px">
                <div style="flex:1">
                    <div class="question-text"><?= htmlspecialchars($q['question']) ?></div>
                    <?php foreach ($q['options'] as $opt): ?>
                        <div class="option-label <?= $opt['correct'] ? 'correct' : '' ?>" style="cursor:default">
                            <?= htmlspecialchars($opt['text']) ?><?= $opt['correct'] ? ' ✓' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </label>
        </div>
        <?php endforeach; ?>

        <div class="form-group" style="margin-top:20px">
            <label>Save to existing quiz</label>
            <select name="target_quiz_id">
                <option value="">— Create a new quiz instead —</option>
                <?php foreach ($quizzes as $qz): ?>
                    <option value="<?= $qz['id'] ?>"><?= htmlspecialchars($qz['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>Or new quiz title</label>
                <input type="text" name="new_quiz_title" placeholder="Leave blank if using existing quiz above">
            </div>
            <div class="form-group">
                <label>New quiz category</label>
                <select name="new_quiz_category" id="newQuizCategorySelect">
                    <option value="">Select category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" name="save_generated" class="btn btn-primary" style="width:100%;justify-content:center">
            Save selected questions
        </button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">Evaluate a descriptive answer</div>
    <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Paste a question, the model/reference answer, and a student's written answer — AI will score it and explain why.
    </p>
    <form method="POST" id="evaluateForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group">
            <label>Question</label>
            <textarea name="eval_question" rows="2" required><?= htmlspecialchars($_POST['eval_question'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Model / reference answer</label>
            <textarea name="eval_model_answer" rows="3" required><?= htmlspecialchars($_POST['eval_model_answer'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label>Student's answer</label>
            <textarea name="eval_student_answer" rows="3" required><?= htmlspecialchars($_POST['eval_student_answer'] ?? '') ?></textarea>
        </div>

        <button type="submit" name="evaluate_answer" id="evaluateBtn" class="btn btn-primary">Evaluate with AI</button>
    </form>

    <?php if ($evalResult): ?>
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
            <span class="badge <?= $evalResult['score_percent'] >= 60 ? 'badge-success' : 'badge-danger' ?>" style="font-size:16px;padding:8px 14px">
                <?= $evalResult['score_percent'] ?>%
            </span>
        </div>
        <p style="font-size:13.5px;margin-bottom:10px"><strong>Feedback:</strong> <?= htmlspecialchars($evalResult['feedback']) ?></p>
        <p style="font-size:13.5px;margin-bottom:10px;color:var(--success)"><strong>Strengths:</strong> <?= htmlspecialchars($evalResult['strengths']) ?></p>
        <p style="font-size:13.5px;color:var(--danger)"><strong>Improvements:</strong> <?= htmlspecialchars($evalResult['improvements']) ?></p>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const topicInput = document.getElementById('topicInput');
    const hint = document.getElementById('topicCategoryHint');
    if (!topicInput || !hint) return;

    const keywordMap = {
        'Science':           ['science','physics','chemistry','biology','atom','cell','force','energy','gravity','photosynthesis','element','reaction','planet','space'],
        'History':           ['history','war','empire','ancient','king','queen','revolution','independence','dynasty','civilization','battle'],
        'Mathematics':       ['math','maths','algebra','geometry','equation','calculus','number','arithmetic','trigonometry','statistics'],
        'Technology':        ['technology','computer','programming','software','internet','ai','javascript','python','code','coding','data','network','app'],
        'General Knowledge': ['capital','river','mountain','country','ocean','continent','geography','currency','language','flag','population']
    };

    function suggestCategory(text) {
        const lower = text.toLowerCase();
        let best = null, bestScore = 0;
        for (const [category, words] of Object.entries(keywordMap)) {
            const score = words.filter(w => lower.includes(w)).length;
            if (score > bestScore) { bestScore = score; best = category; }
        }
        return best;
    }

    function applySuggestion(text) {
        text = (text || '').trim();
        if (text.length < 3) {
            hint.style.display = 'none';
            return;
        }
        const match = suggestCategory(text);
        if (match) {
            hint.textContent = 'Suggested category: ' + match + ' (you can pick this when saving the generated questions)';
            hint.style.display = 'block';
            sessionStorage.setItem('ai_suggested_category', match);

            const categorySelectNow = document.getElementById('newQuizCategorySelect');
            if (categorySelectNow) {
                for (const opt of categorySelectNow.options) {
                    if (opt.textContent.trim() === match) {
                        opt.selected = true;
                        break;
                    }
                }
            }
        } else {
            hint.style.display = 'none';
        }
    }

    topicInput.addEventListener('input', function () {
        applySuggestion(this.value);
    });

    // Run once on load too — covers the case where the topic field is
    // pre-filled after generating (page reload), with no typing involved
    applySuggestion(topicInput.value);
    // ── Offline detection — block AI features when there's no internet ──
    const offlineBanner = document.getElementById('offlineBanner');
    const generateBtn   = document.getElementById('generateBtn');
    const evaluateBtn   = document.getElementById('evaluateBtn');
    const generateForm  = document.getElementById('generateForm');
    const evaluateForm  = document.getElementById('evaluateForm');

    function updateOnlineState() {
        const isOnline = navigator.onLine;
        if (offlineBanner) offlineBanner.style.display = isOnline ? 'none' : 'block';
        [generateBtn, evaluateBtn].forEach(btn => {
            if (!btn) return;
            btn.disabled = !isOnline;
            btn.textContent = isOnline
                ? btn.dataset.originalText || btn.textContent
                : 'Offline — connect to the internet';
            if (isOnline && btn.dataset.originalText) {
                btn.textContent = btn.dataset.originalText;
            } else if (!isOnline && !btn.dataset.originalText) {
                btn.dataset.originalText = btn.textContent;
            }
        });
    }

    function blockIfOffline(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            if (offlineBanner) {
                offlineBanner.style.display = 'block';
                offlineBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    window.addEventListener('online', updateOnlineState);
    window.addEventListener('offline', updateOnlineState);
    if (generateForm) generateForm.addEventListener('submit', blockIfOffline);
    if (evaluateForm) evaluateForm.addEventListener('submit', blockIfOffline);
    updateOnlineState();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>