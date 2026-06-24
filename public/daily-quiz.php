<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];
$today  = date('Y-m-d');

// ── AJAX: fetch next batch of 5 questions ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'get_batch') {
        $offset   = (int)($_POST['offset'] ?? 0);
        $answered = json_decode($_POST['answered_ids'] ?? '[]', true);

        $placeholders = !empty($answered) ? implode(',', array_fill(0, count($answered), '?')) : '0';
        $params = array_merge($answered, [$today]);

        // Pull questions from all active quizzes, skip already answered
        $stmt = $db->prepare("
            SELECT q.id, q.question_text, q.marks, qu.title AS quiz_title
            FROM questions q
            JOIN quizzes qu ON qu.id = q.quiz_id
            WHERE qu.is_active = 1
            AND q.id NOT IN ($placeholders)
            ORDER BY RAND()
            LIMIT 5
        ");
        $stmt->execute($answered);
        $questions = $stmt->fetchAll();

        foreach ($questions as &$q) {
            $opts = $db->prepare("SELECT id, option_text, is_correct FROM options WHERE question_id = ? ORDER BY RAND()");
            $opts->execute([$q['id']]);
            $q['options'] = $opts->fetchAll();
        }
        unset($q);

        echo json_encode(['questions' => $questions]);
        exit;
    }

    if ($action === 'check_batch') {
        $answers = json_decode($_POST['answers'] ?? '[]', true); // [{qid, oid}]
        $results = [];
        $correct = 0;

        foreach ($answers as $ans) {
            $qid = (int)$ans['qid'];
            $oid = (int)$ans['oid'];
            $correct_opt = $db->prepare("SELECT id, option_text FROM options WHERE question_id = ? AND is_correct = 1 LIMIT 1");
            $correct_opt->execute([$qid]);
            $correctRow = $correct_opt->fetch();

            $all_opts = $db->prepare("SELECT id, option_text, is_correct FROM options WHERE question_id = ?");
            $all_opts->execute([$qid]);

            $isCorrect = $correctRow && ($oid === (int)$correctRow['id']);
            if ($isCorrect) $correct++;

            $results[] = [
                'qid'        => $qid,
                'selected'   => $oid,
                'correct_id' => $correctRow ? (int)$correctRow['id'] : null,
                'is_correct' => $isCorrect,
                'options'    => $all_opts->fetchAll(),
            ];
        }

        echo json_encode(['results' => $results, 'correct' => $correct, 'total' => count($answers)]);
        exit;
    }

    if ($action === 'save_session') {
        $totalQ   = (int)($_POST['total_questions'] ?? 0);
        $totalC   = (int)($_POST['total_correct'] ?? 0);
        $stmt = $db->prepare("
            INSERT INTO daily_sessions (user_id, session_date, total_questions, total_correct, completed_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE total_questions=VALUES(total_questions), total_correct=VALUES(total_correct), completed_at=NOW()
        ");
        $stmt->execute([$userId, $today, $totalQ, $totalC]);
        echo json_encode(['ok' => true]);
        exit;
    }
}

// ── Check today's session ──────────────────────────────
// Try daily_sessions table, fallback gracefully if not exists
$todaySession = null;
try {
    $chk = $db->prepare("SELECT * FROM daily_sessions WHERE user_id = ? AND session_date = ?");
    $chk->execute([$userId, $today]);
    $todaySession = $chk->fetch();
} catch (Exception $e) {
    // Table might not exist yet — create it
    $db->exec("CREATE TABLE IF NOT EXISTS daily_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_date DATE NOT NULL,
        total_questions INT DEFAULT 0,
        total_correct INT DEFAULT 0,
        completed_at DATETIME DEFAULT NULL,
        UNIQUE KEY unique_user_day (user_id, session_date),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// ── Streak ─────────────────────────────────────────────
$streakQ = $db->prepare("
    SELECT session_date FROM daily_sessions
    WHERE user_id = ? ORDER BY session_date DESC
");
$streakQ->execute([$userId]);
$days   = $streakQ->fetchAll(PDO::FETCH_COLUMN);
$streak = 0;
$check  = new DateTime($today);
foreach ($days as $d) {
    if ($d === $check->format('Y-m-d')) { $streak++; $check->modify('-1 day'); }
    else break;
}

// ── Total available questions ──────────────────────────
$totalAvailable = (int)$db->query("
    SELECT COUNT(q.id) FROM questions q
    JOIN quizzes qu ON qu.id = q.quiz_id WHERE qu.is_active = 1
")->fetchColumn();

$timeLeft = (new DateTime('tomorrow midnight'))->diff(new DateTime())->format('%Hh %Im');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Daily Quiz — QuizApp</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f4ff;min-height:100vh;color:#1e293b}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar .logo{font-weight:700;color:#4388f5}
.topbar a{font-size:.78rem;color:#64748b;text-decoration:none}.topbar a:hover{color:#4388f5}
.wrap{max-width:700px;margin:0 auto;padding:24px 16px}

/* Hero */
.hero{background:linear-gradient(135deg,#4388f5,#2a5dc2);border-radius:20px;padding:26px 28px;color:#fff;margin-bottom:18px;position:relative;overflow:hidden}
.hero::after{content:'📅';position:absolute;right:20px;top:14px;font-size:3.5rem;opacity:.12;pointer-events:none}
.hero h1{font-size:1.35rem;font-weight:800;margin-bottom:4px}
.hero p{font-size:.8rem;opacity:.8;margin-bottom:14px}
.hero-meta{display:flex;gap:20px;flex-wrap:wrap}
.meta .lbl{font-size:.68rem;opacity:.7;margin-bottom:1px}
.meta .val{font-size:.9rem;font-weight:700}

/* Streak */
.streak-bar{background:#fff;border-radius:14px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:14px;border:1.5px solid #e2e8f0}
.streak-icon{font-size:1.6rem}
.streak-info h3{font-size:.86rem;font-weight:700;margin-bottom:1px}
.streak-info p{font-size:.74rem;color:#64748b}
.streak-num{margin-left:auto;text-align:right}
.streak-num .num{font-size:1.9rem;font-weight:800;color:#f59e0b;line-height:1}
.streak-num .lbl{font-size:.68rem;color:#94a3b8}

/* Already done */
.done-today{background:#fff;border-radius:16px;padding:28px;text-align:center;border:1.5px solid #e2e8f0;margin-bottom:16px}
.done-today .big{font-size:3rem;font-weight:800;color:#4388f5;margin:10px 0}
.done-today h3{font-size:.98rem;font-weight:700;margin-bottom:4px}
.done-today p{font-size:.8rem;color:#64748b;margin-bottom:16px}
.timer-pill{display:inline-flex;align-items:center;gap:6px;background:#fef3c7;color:#92400e;padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:600}

/* Start card */
.start-card{background:#fff;border-radius:16px;padding:24px;border:1.5px solid #e2e8f0;margin-bottom:16px}
.start-card h3{font-size:.96rem;font-weight:700;margin-bottom:4px}
.start-card p{font-size:.8rem;color:#64748b;margin-bottom:16px;line-height:1.6}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.chip{background:#f1f5f9;padding:5px 12px;border-radius:20px;font-size:.74rem;font-weight:500;color:#475569}
.btn{width:100%;padding:12px;background:linear-gradient(135deg,#4388f5,#2a5dc2);color:#fff;border:none;border-radius:12px;font-size:.9rem;font-weight:700;cursor:pointer;transition:all .2s;box-shadow:0 4px 14px rgba(67,136,245,.3);display:flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(67,136,245,.4)}

/* Quiz UI */
.quiz-ui{display:none}
.quiz-ui.show{display:block}

/* Progress strip */
.prog-strip{background:#fff;border-radius:12px;padding:14px 18px;margin-bottom:16px;border:1.5px solid #e2e8f0}
.prog-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:.78rem}
.prog-label{font-weight:600;color:#475569}
.score-live{font-weight:700;color:#4388f5}
.prog-bar{height:7px;background:#f1f5f9;border-radius:4px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,#4388f5,#10b981);border-radius:4px;transition:width .5s ease}
.batch-info{display:flex;justify-content:space-between;margin-top:6px;font-size:.72rem;color:#94a3b8}

/* Batch label */
.batch-label{background:#edf4ff;color:#4388f5;font-size:.72rem;font-weight:700;padding:5px 12px;border-radius:20px;margin-bottom:14px;display:inline-block}

/* Question cards */
.q-card{background:#fff;border-radius:14px;padding:20px;margin-bottom:12px;border:1.5px solid #e2e8f0;transition:border-color .2s}
.q-num{font-size:.7rem;font-weight:600;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.q-text{font-size:.9rem;font-weight:500;line-height:1.6;margin-bottom:14px}
.options{display:flex;flex-direction:column;gap:7px}
.opt{padding:9px 13px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:9px;background:#fafafa;user-select:none}
.opt:hover:not(.disabled){border-color:#4388f5;background:#edf4ff;transform:translateX(3px)}
.opt.correct{border-color:#10b981;background:#ecfdf5;color:#065f46;font-weight:500}
.opt.wrong{border-color:#ef4444;background:#fef2f2;color:#b91c1c}
.opt.show-correct{border-color:#10b981;background:#ecfdf5;color:#065f46}
.opt.disabled{cursor:default}
.opt-icon{width:20px;height:20px;border-radius:50%;border:1.5px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;transition:all .2s}
.opt.correct .opt-icon,.opt.show-correct .opt-icon{background:#10b981;border-color:#10b981;color:#fff}
.opt.wrong .opt-icon{background:#ef4444;border-color:#ef4444;color:#fff}

/* Batch result */
.batch-result{border-radius:14px;padding:20px;margin:16px 0;text-align:center;display:none}
.batch-result.show{display:block}
.batch-result.pass{background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1.5px solid #10b981}
.batch-result.fail{background:linear-gradient(135deg,#fff7ed,#fed7aa);border:1.5px solid #f59e0b}
.batch-result h3{font-size:1.1rem;font-weight:800;margin-bottom:6px}
.batch-result.pass h3{color:#065f46}
.batch-result.fail h3{color:#92400e}
.batch-result p{font-size:.82rem;margin-bottom:14px;line-height:1.6}
.batch-result.pass p{color:#047857}
.batch-result.fail p{color:#b45309}
.batch-score{font-size:2.2rem;font-weight:800;margin:8px 0}
.batch-result.pass .batch-score{color:#10b981}
.batch-result.fail .batch-score{color:#f59e0b}
.btn-next{padding:11px 28px;border:none;border-radius:11px;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .2s}
.btn-next.green{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 12px rgba(16,185,129,.3)}
.btn-next.green:hover{transform:translateY(-1px)}
.btn-next.orange{background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;box-shadow:0 4px 12px rgba(245,158,11,.3)}
.btn-next.orange:hover{transform:translateY(-1px)}

/* Session done */
.session-done{background:linear-gradient(135deg,#4388f5,#2a5dc2);color:#fff;border-radius:18px;padding:32px;text-align:center;display:none;margin-top:8px}
.session-done.show{display:block}
.session-done h3{font-size:1.4rem;font-weight:800;margin-bottom:6px}
.session-done .big-score{font-size:3.5rem;font-weight:800;margin:12px 0;line-height:1}
.session-done p{font-size:.82rem;opacity:.85;margin-bottom:20px;line-height:1.6}
.done-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn-white{background:#fff;color:#4388f5;padding:10px 22px;border-radius:10px;font-size:.84rem;font-weight:700;border:none;cursor:pointer;transition:all .2s}
.btn-white:hover{background:#edf4ff}

/* Loading */
.loading-q{text-align:center;padding:28px;color:#94a3b8;font-size:.82rem}
.spinner{width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:#4388f5;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 10px}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.q-card{animation:fadeUp .35s ease both}
</style>
</head>
<body>
<div class="topbar">
    <span class="logo">⚡ QuizApp</span>
    <a href="dashboard.php">← Dashboard</a>
</div>
<div class="wrap">

    <!-- Hero -->
    <div class="hero">
        <h1>📅 Daily Quiz</h1>
        <p><?= date('l, F j, Y') ?> · Adaptive mode — keep going as long as you're winning!</p>
        <div class="hero-meta">
            <div class="meta"><div class="lbl">Next quiz in</div><div class="val"><?= $timeLeft ?></div></div>
            <div class="meta"><div class="lbl">Questions available</div><div class="val"><?= $totalAvailable ?>+</div></div>
            <div class="meta"><div class="lbl">Min questions</div><div class="val">50</div></div>
        </div>
    </div>

    <!-- Streak -->
    <div class="streak-bar">
        <div class="streak-icon"><?= $streak >= 7 ? '🔥' : ($streak >= 3 ? '⚡' : '📆') ?></div>
        <div class="streak-info">
            <h3>Daily Streak</h3>
            <p><?= $streak >= 3 ? "You're on fire! Keep the streak alive." : "Start today's quiz to build your streak!" ?></p>
        </div>
        <div class="streak-num">
            <div class="num"><?= $streak ?></div>
            <div class="lbl">day<?= $streak !== 1 ? 's' : '' ?></div>
        </div>
    </div>

    <?php if ($todaySession): ?>
    <!-- Already completed today -->
    <div class="done-today">
        <h3>You crushed today's quiz! 🎉</h3>
        <div class="big"><?= round($todaySession['total_correct'] / max($todaySession['total_questions'],1) * 100) ?>%</div>
        <p>You answered <?= $todaySession['total_correct'] ?> out of <?= $todaySession['total_questions'] ?> questions correctly. Come back tomorrow!</p>
        <div class="timer-pill">⏰ Next quiz in <?= $timeLeft ?></div>
    </div>

    <?php else: ?>
    <!-- Start card -->
    <div class="start-card" id="startCard">
        <h3>Ready for today's challenge? 🚀</h3>
        <p>Answer in batches of <strong>5 questions</strong>. Get <strong>3 or more correct</strong> to unlock the next batch. Minimum <strong>50 questions</strong> — go as far as you can!</p>
        <div class="chips">
            <span class="chip">📦 5 questions per batch</span>
            <span class="chip">✅ Need 3/5 to continue</span>
            <span class="chip">🎯 Minimum 50 questions</span>
            <span class="chip">🔀 Random from all quizzes</span>
        </div>
        <button class="btn" onclick="startQuiz()">Start Today's Quiz →</button>
    </div>
    <?php endif; ?>

    <!-- Quiz UI -->
    <div class="quiz-ui" id="quizUI">
        <!-- Progress strip -->
        <div class="prog-strip">
            <div class="prog-top">
                <span class="prog-label">Today's Progress</span>
                <span class="score-live">✅ <span id="totalCorrect">0</span> correct · <span id="totalAnswered">0</span> answered</span>
            </div>
            <div class="prog-bar"><div class="prog-fill" id="progFill" style="width:0%"></div></div>
            <div class="batch-info">
                <span>Batch <span id="batchNum">1</span></span>
                <span id="minLabel">Keep going — minimum 50 questions</span>
            </div>
        </div>

        <div id="batchLabel" class="batch-label">Batch 1 — Questions 1–5</div>

        <!-- Questions render here -->
        <div id="qList"></div>

        <!-- Loading -->
        <div class="loading-q" id="loadingQ" style="display:none">
            <div class="spinner"></div>
            <p>Loading next batch...</p>
        </div>

        <!-- Batch result -->
        <div class="batch-result" id="batchResult">
            <div class="batch-score" id="batchScore"></div>
            <h3 id="batchTitle"></h3>
            <p id="batchMsg"></p>
            <button class="btn-next" id="batchBtn" onclick="handleBatchNext()"></button>
        </div>

        <!-- Session done -->
        <div class="session-done" id="sessionDone">
            <h3 id="doneTitle">Session Complete! 🎉</h3>
            <div class="big-score" id="doneScore"></div>
            <p id="doneMsg"></p>
            <div class="done-btns">
                <button class="btn-white" onclick="location.reload()">View Summary</button>
            </div>
        </div>
    </div>

</div>
<script>
const MIN_QUESTIONS = 50;
let totalAnswered = 0, totalCorrect = 0;
let batchNum = 1, answeredIds = [], pendingAnswers = [];
let batchAnswers = {}, batchDone = false, canContinue = false;

function startQuiz() {
    document.getElementById('startCard').style.display = 'none';
    document.getElementById('quizUI').classList.add('show');
    loadBatch();
}

async function loadBatch() {
    document.getElementById('loadingQ').style.display = 'block';
    document.getElementById('qList').innerHTML = '';
    document.getElementById('batchResult').classList.remove('show');
    batchAnswers = {};
    batchDone    = false;

    const fd = new FormData();
    fd.append('action', 'get_batch');
    fd.append('offset', totalAnswered);
    fd.append('answered_ids', JSON.stringify(answeredIds));

    const res  = await fetch('daily-quiz.php', {method:'POST', body:fd});
    const data = await res.json();
    document.getElementById('loadingQ').style.display = 'none';

    if (!data.questions || data.questions.length === 0) {
        finishSession('no_more');
        return;
    }

    const start = totalAnswered + 1;
    const end   = totalAnswered + data.questions.length;
    document.getElementById('batchLabel').textContent = `Batch ${batchNum} — Questions ${start}–${end}`;
    document.getElementById('batchNum').textContent   = batchNum;
    renderBatch(data.questions);
}

function renderBatch(questions) {
    const list = document.getElementById('qList');
    list.innerHTML = '';
    questions.forEach((q, i) => {
        const globalNum = totalAnswered + i + 1;
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id = 'qc-' + q.id;
        card.style.animationDelay = (i * 0.07) + 's';
        card.innerHTML = `
            <div class="q-num">Question ${globalNum} · ${q.quiz_title || ''}</div>
            <div class="q-text">${q.question_text}</div>
            <div class="options">
                ${q.options.map(o => `
                <div class="opt" data-qid="${q.id}" data-oid="${o.id}" onclick="selectOpt(${q.id}, ${o.id}, this)">
                    <div class="opt-icon">○</div>
                    <span>${o.option_text}</span>
                </div>`).join('')}
            </div>`;
        list.appendChild(card);
        answeredIds.push(q.id);
    });
    window._currentBatch = questions;
}

function selectOpt(qid, oid, el) {
    const card = document.getElementById('qc-' + qid);
    if (card.dataset.selected) return;
    card.dataset.selected = oid;
    batchAnswers[qid] = oid;
    card.querySelectorAll('.opt').forEach(o => {
        o.classList.remove('selected-preview');
        o.style.borderColor = '';
    });
    el.style.borderColor = '#4388f5';
    el.style.background  = '#edf4ff';

    // Auto-submit batch when all answered
    const allAnswered = window._currentBatch.every(q => batchAnswers[q.id]);
    if (allAnswered && !batchDone) submitBatch();
}

async function submitBatch() {
    batchDone = true;
    const answers = Object.entries(batchAnswers).map(([qid, oid]) => ({qid: parseInt(qid), oid: parseInt(oid)}));

    const fd = new FormData();
    fd.append('action', 'check_batch');
    fd.append('answers', JSON.stringify(answers));

    const res  = await fetch('daily-quiz.php', {method:'POST', body:fd});
    const data = await res.json();

    // Show correct/wrong on cards
    data.results.forEach(r => {
        const card = document.getElementById('qc-' + r.qid);
        if (!card) return;
        card.querySelectorAll('.opt').forEach(o => {
            o.classList.add('disabled');
            const oid = parseInt(o.dataset.oid);
            const icon = o.querySelector('.opt-icon');
            if (oid === r.correct_id) {
                o.classList.add(r.selected === oid ? 'correct' : 'show-correct');
                icon.textContent = '✓';
            } else if (oid === r.selected && !r.is_correct) {
                o.classList.add('wrong');
                icon.textContent = '✗';
            }
        });
    });

    totalAnswered += data.total;
    totalCorrect  += data.correct;
    batchNum++;

    // Update progress strip
    document.getElementById('totalAnswered').textContent = totalAnswered;
    document.getElementById('totalCorrect').textContent  = totalCorrect;
    const pct = Math.min(Math.round(totalAnswered / MIN_QUESTIONS * 100), 100);
    document.getElementById('progFill').style.width = pct + '%';
    if (totalAnswered >= MIN_QUESTIONS) {
        document.getElementById('minLabel').textContent = `🎯 Minimum reached! Keep going!`;
    }

    showBatchResult(data.correct, data.total);
}

function showBatchResult(correct, total) {
    const pass = correct >= 3;
    canContinue = pass;
    const result = document.getElementById('batchResult');
    result.className = 'batch-result show ' + (pass ? 'pass' : 'fail');
    document.getElementById('batchScore').textContent = correct + '/' + total;

    const motivations = [
        "You're unstoppable! Keep it going! 🔥",
        "Brilliant performance! Next batch awaits! 🚀",
        "You're on fire! Keep the momentum! ⚡",
        "Excellent work! You earned the next batch! 🌟",
        "Outstanding! Your streak continues! 💪",
    ];
    const encouragements = [
        "Don't give up — review and try again! 💪",
        "Almost there! Believe in yourself! 🌟",
        "Mistakes help you learn — you've got this! 🎯",
    ];

    if (pass) {
        document.getElementById('batchTitle').textContent = correct === total ? 'Perfect Batch! 🌟' : 'Great Job! Keep Going! 🚀';
        document.getElementById('batchMsg').textContent   = motivations[Math.floor(Math.random() * motivations.length)];
        const btn = document.getElementById('batchBtn');
        btn.textContent = totalAnswered >= MIN_QUESTIONS ? 'Continue (Bonus!) →' : `Next Batch (${totalAnswered}/${MIN_QUESTIONS}) →`;
        btn.className = 'btn-next green';
    } else {
        document.getElementById('batchTitle').textContent = `Only ${correct}/5 — Session Ends Here`;
        document.getElementById('batchMsg').textContent   = encouragements[Math.floor(Math.random() * encouragements.length)] + ` You answered ${totalAnswered} questions total today!`;
        const btn = document.getElementById('batchBtn');
        btn.textContent = totalAnswered >= MIN_QUESTIONS ? 'Finish Session ✓' : 'End Session';
        btn.className = 'btn-next orange';
    }
    result.scrollIntoView({behavior:'smooth', block:'center'});
}

function handleBatchNext() {
    if (canContinue) {
        loadBatch();
    } else {
        finishSession('failed_batch');
    }
}

async function finishSession(reason) {
    // Save to DB
    const fd = new FormData();
    fd.append('action', 'save_session');
    fd.append('total_questions', totalAnswered);
    fd.append('total_correct', totalCorrect);
    await fetch('daily-quiz.php', {method:'POST', body:fd});

    const pct = totalAnswered > 0 ? Math.round(totalCorrect / totalAnswered * 100) : 0;
    document.getElementById('batchResult').classList.remove('show');
    document.getElementById('qList').innerHTML = '';
    document.getElementById('batchLabel').style.display = 'none';

    const done = document.getElementById('sessionDone');
    done.classList.add('show');
    document.getElementById('doneScore').textContent = pct + '%';

    let title, msg;
    if (reason === 'no_more') {
        title = 'All Questions Done! 🏆';
        msg   = `You completed all ${totalAnswered} available questions today with ${totalCorrect} correct answers. Incredible!`;
    } else if (totalAnswered >= MIN_QUESTIONS) {
        title = 'Amazing Session! 🎉';
        msg   = `You answered ${totalAnswered} questions with ${totalCorrect} correct (${pct}%). Well above the minimum — outstanding!`;
    } else {
        title = 'Good Effort! 💪';
        msg   = `You answered ${totalAnswered} questions today with ${totalCorrect} correct. Practice more to go further tomorrow!`;
    }
    document.getElementById('doneTitle').textContent = title;
    document.getElementById('doneMsg').textContent   = msg;
    done.scrollIntoView({behavior:'smooth'});
}
</script>
</body>
</html>