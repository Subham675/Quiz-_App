<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];

// Load quiz
$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) {
    // Show quiz picker
    $quizzes = $db->query("
        SELECT q.id, q.title, c.name AS category, q.total_marks,
               COUNT(qu.id) AS question_count
        FROM quizzes q
        JOIN categories c ON c.id = q.category_id
        JOIN questions qu ON qu.quiz_id = q.id
        WHERE q.is_active = 1
        GROUP BY q.id
        ORDER BY c.name, q.title
    ")->fetchAll();
}

// Submit answer in practice mode (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_id'])) {
    header('Content-Type: application/json');
    $questionId = (int)$_POST['question_id'];
    $selectedId = (int)$_POST['option_id'];

    $opts = $db->prepare("SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY id");
    $opts->execute([$questionId]);
    $options = $opts->fetchAll();

    $correctId = null;
    foreach ($options as $o) {
        if ($o['is_correct']) { $correctId = $o['id']; break; }
    }

    // Fetch explanation (question text re-used as hint for now)
    $q = $db->prepare("SELECT question_text FROM questions WHERE id = ?");
    $q->execute([$questionId]);
    $question = $q->fetch();

    echo json_encode([
        'correct_id' => $correctId,
        'selected_correct' => ($selectedId === $correctId),
        'options' => $options,
    ]);
    exit;
}

// Load questions for practice
if ($quizId) {
    $quiz = $db->prepare("SELECT q.*, c.name AS category FROM quizzes q JOIN categories c ON c.id = q.category_id WHERE q.id = ?");
    $quiz->execute([$quizId]);
    $quiz = $quiz->fetch();
    if (!$quiz) { header('Location: practice.php'); exit; }

    $questions = $db->prepare("
        SELECT q.id, q.question_text, q.marks
        FROM questions q WHERE q.quiz_id = ? ORDER BY q.order_index, q.id
    ");
    $questions->execute([$quizId]);
    $questions = $questions->fetchAll();

    foreach ($questions as &$q) {
        $opts = $db->prepare("SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY id");
        $opts->execute([$q['id']]);
        $q['options'] = $opts->fetchAll();
    }
    unset($q);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Practice Mode — QuizApp</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f4ff;min-height:100vh;color:#1e293b}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar .logo{font-weight:700;color:#4388f5;font-size:1rem}
.topbar .mode-badge{background:#edf4ff;color:#4388f5;font-size:0.72rem;font-weight:600;padding:4px 10px;border-radius:20px}
.topbar a{font-size:0.78rem;color:#64748b;text-decoration:none}
.topbar a:hover{color:#4388f5}

.wrap{max-width:720px;margin:0 auto;padding:28px 20px}

/* Quiz picker */
.picker-title{font-size:1.2rem;font-weight:700;margin-bottom:6px}
.picker-sub{font-size:0.82rem;color:#64748b;margin-bottom:24px}
.quiz-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.quiz-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:18px;cursor:pointer;text-decoration:none;color:inherit;transition:all .2s;display:block}
.quiz-card:hover{border-color:#4388f5;box-shadow:0 4px 16px rgba(67,136,245,0.12);transform:translateY(-2px)}
.quiz-cat{font-size:0.7rem;font-weight:600;color:#4388f5;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.quiz-title{font-size:0.88rem;font-weight:600;margin-bottom:8px}
.quiz-meta{font-size:0.74rem;color:#94a3b8}

/* Practice area */
.practice-header{background:#fff;border-radius:14px;padding:18px 22px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;border:1px solid #e2e8f0}
.practice-header h2{font-size:1rem;font-weight:700}
.practice-header .cat{font-size:0.76rem;color:#64748b;margin-top:2px}
.score-live{text-align:right}
.score-live .num{font-size:1.4rem;font-weight:700;color:#4388f5}
.score-live .lbl{font-size:0.72rem;color:#94a3b8}

.q-card{background:#fff;border-radius:14px;padding:22px;margin-bottom:16px;border:1.5px solid #e2e8f0;transition:border-color .2s}
.q-num{font-size:0.72rem;font-weight:600;color:#94a3b8;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.q-text{font-size:0.92rem;font-weight:500;line-height:1.6;margin-bottom:16px}

.options{display:flex;flex-direction:column;gap:8px}
.opt{padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:0.84rem;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:10px;user-select:none;background:#fafafa}
.opt:hover:not(.disabled){border-color:#4388f5;background:#edf4ff;transform:translateX(3px)}
.opt.correct{border-color:#10b981;background:#ecfdf5;color:#065f46;font-weight:500}
.opt.wrong{border-color:#ef4444;background:#fef2f2;color:#b91c1c}
.opt.show-correct{border-color:#10b981;background:#ecfdf5;color:#065f46}
.opt.disabled{cursor:default}
.opt-icon{width:20px;height:20px;border-radius:50%;border:1.5px solid #cbd5e1;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px;transition:all .2s}
.opt.correct .opt-icon{background:#10b981;border-color:#10b981;color:#fff}
.opt.wrong .opt-icon{background:#ef4444;border-color:#ef4444;color:#fff}
.opt.show-correct .opt-icon{background:#10b981;border-color:#10b981;color:#fff}

.feedback{margin-top:12px;padding:10px 14px;border-radius:9px;font-size:0.8rem;font-weight:500;display:none}
.feedback.show{display:flex;align-items:center;gap:8px}
.feedback.good{background:#ecfdf5;color:#065f46}
.feedback.bad{background:#fef2f2;color:#b91c1c}

.progress-bar{height:4px;background:#e2e8f0;border-radius:2px;margin-bottom:20px;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,#4388f5,#2a5dc2);border-radius:2px;transition:width .4s ease}

.done-banner{background:linear-gradient(135deg,#4388f5,#2a5dc2);color:#fff;border-radius:16px;padding:32px;text-align:center;margin-top:20px}
.done-banner h3{font-size:1.3rem;font-weight:700;margin-bottom:8px}
.done-banner p{font-size:0.85rem;opacity:.85;margin-bottom:20px}
.done-banner .score-big{font-size:3rem;font-weight:800;margin:12px 0}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;font-size:0.85rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s}
.btn-white{background:#fff;color:#4388f5}
.btn-white:hover{background:#edf4ff}
.btn-outline{background:rgba(255,255,255,0.15);color:#fff;border:1.5px solid rgba(255,255,255,0.4)}
.btn-outline:hover{background:rgba(255,255,255,0.25)}
</style>
</head>
<body>
<div class="topbar">
    <span class="logo">⚡ QuizApp</span>
    <span class="mode-badge">🎯 Practice Mode — No timer, learn freely</span>
    <a href="dashboard.php">← Dashboard</a>
</div>
<div class="wrap">
<?php if (!$quizId): ?>
    <p class="picker-title">Choose a quiz to practice</p>
    <p class="picker-sub">No timer, no pressure — instant feedback after each answer</p>
    <div class="quiz-grid">
    <?php foreach ($quizzes as $q): ?>
        <a class="quiz-card" href="practice.php?quiz_id=<?= $q['id'] ?>">
            <div class="quiz-cat"><?= htmlspecialchars($q['category']) ?></div>
            <div class="quiz-title"><?= htmlspecialchars($q['title']) ?></div>
            <div class="quiz-meta"><?= $q['question_count'] ?> questions · <?= $q['total_marks'] ?> marks</div>
        </a>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="progress-bar"><div class="progress-fill" id="prog" style="width:0%"></div></div>

    <div class="practice-header">
        <div>
            <h2><?= htmlspecialchars($quiz['title']) ?></h2>
            <div class="cat"><?= htmlspecialchars($quiz['category']) ?> · Practice Mode</div>
        </div>
        <div class="score-live">
            <div class="num"><span id="correct">0</span>/<?= count($questions) ?></div>
            <div class="lbl">correct</div>
        </div>
    </div>

    <?php foreach ($questions as $i => $q): ?>
    <div class="q-card" id="qc-<?= $q['id'] ?>">
        <div class="q-num">Question <?= $i+1 ?> of <?= count($questions) ?></div>
        <div class="q-text"><?= htmlspecialchars($q['question_text']) ?></div>
        <div class="options">
            <?php foreach ($q['options'] as $opt): ?>
            <div class="opt" data-qid="<?= $q['id'] ?>" data-oid="<?= $opt['id'] ?>" onclick="answer(<?= $q['id'] ?>, <?= $opt['id'] ?>, this)">
                <div class="opt-icon">○</div>
                <span><?= htmlspecialchars($opt['option_text']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="feedback" id="fb-<?= $q['id'] ?>"></div>
    </div>
    <?php endforeach; ?>

    <div class="done-banner" id="done" style="display:none">
        <h3>Practice Complete! 🎉</h3>
        <div class="score-big" id="final-score">0%</div>
        <p>You got <span id="final-correct">0</span> out of <?= count($questions) ?> correct</p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a href="practice.php?quiz_id=<?= $quizId ?>" class="btn btn-white">Try Again</a>
            <a href="practice.php" class="btn btn-outline">Pick Another Quiz</a>
        </div>
    </div>
<?php endif; ?>
</div>

<script>
const total = <?= count($questions ?? []) ?>;
let correct = 0, answered = 0;

async function answer(qid, oid, el) {
    const card = document.getElementById('qc-' + qid);
    if (card.dataset.done) return;
    card.dataset.done = '1';

    const opts = card.querySelectorAll('.opt');
    opts.forEach(o => o.classList.add('disabled'));

    const res = await fetch('practice.php?quiz_id=<?= $quizId ?>', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `question_id=${qid}&option_id=${oid}&csrf_token=<?= csrfToken() ?>`
    });
    const data = await res.json();

    opts.forEach(o => {
        const id = parseInt(o.dataset.oid);
        if (id === data.correct_id) o.classList.add(data.selected_correct ? 'correct' : 'show-correct');
        else if (id === oid && !data.selected_correct) o.classList.add('wrong');
        o.querySelector('.opt-icon').textContent = id === data.correct_id ? '✓' : (id === oid && !data.selected_correct ? '✗' : '○');
    });

    const fb = document.getElementById('fb-' + qid);
    fb.className = 'feedback show ' + (data.selected_correct ? 'good' : 'bad');
    fb.innerHTML = data.selected_correct
        ? '✅ Correct! Well done.'
        : '❌ Not quite — the highlighted option is correct.';

    answered++;
    if (data.selected_correct) { correct++; document.getElementById('correct').textContent = correct; }
    document.getElementById('prog').style.width = (answered/total*100) + '%';

    if (answered === total) {
        setTimeout(() => {
            document.getElementById('done').style.display = 'block';
            document.getElementById('final-correct').textContent = correct;
            document.getElementById('final-score').textContent = Math.round(correct/total*100) + '%';
            document.getElementById('done').scrollIntoView({behavior:'smooth'});
        }, 600);
    }
}
</script>
</body>
</html>