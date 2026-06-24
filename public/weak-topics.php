<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$db     = getDB();
$userId = $_SESSION['user_id'];

// Load .env for Gemini key
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}
$GEMINI_KEY = $_ENV['GEMINI_API_KEY'] ?? '';

// ── AJAX: Generate AI questions for a weak topic ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic'])) {
    header('Content-Type: application/json');
    $topic = trim(strip_tags($_POST['topic'] ?? ''));
    $count = min(max((int)($_POST['count'] ?? 5), 3), 10);

    if (!$GEMINI_KEY || $GEMINI_KEY === 'your_gemini_api_key_here') {
        echo json_encode(['error' => 'Gemini API key not configured in .env']); exit;
    }

    $prompt = "Generate exactly {$count} multiple choice questions specifically about \"{$topic}\" at medium difficulty, focusing on common mistakes and tricky concepts beginners get wrong.

Return ONLY a raw JSON array, no markdown, no code blocks:
[{\"question\":\"...\",\"options\":[\"A\",\"B\",\"C\",\"D\"],\"correct\":0,\"explanation\":\"...\"}]

Rules: correct is 0-based index, exactly 4 options per question, explanations 1-2 sentences.";

    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]], 'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 2048]]);
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$GEMINI_KEY}");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 30]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode !== 200) { echo json_encode(['error' => 'Gemini API failed. Check your API key.']); exit; }
    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/', '', $text);
    $questions = json_decode(trim($text), true);

    if (!is_array($questions) || empty($questions)) { echo json_encode(['error' => 'Could not parse questions. Try again.']); exit; }

    $clean = [];
    foreach ($questions as $q) {
        if (!isset($q['question'], $q['options'], $q['correct']) || count($q['options']) !== 4) continue;
        $clean[] = [
            'question'    => htmlspecialchars(strip_tags($q['question'])),
            'options'     => array_map(fn($o) => htmlspecialchars(strip_tags($o)), $q['options']),
            'correct'     => (int)$q['correct'],
            'explanation' => htmlspecialchars(strip_tags($q['explanation'] ?? '')),
        ];
    }
    echo json_encode(empty($clean) ? ['error' => 'No valid questions generated.'] : ['questions' => $clean]);
    exit;
}

// ── Category accuracy ──────────────────────────────────
$stats = $db->prepare("
    SELECT c.id, c.name AS category,
           COUNT(aa.id)                                    AS total_answers,
           SUM(aa.is_correct)                              AS correct_answers,
           ROUND(SUM(aa.is_correct)/COUNT(aa.id)*100)      AS accuracy,
           COUNT(DISTINCT a.quiz_id)                       AS quizzes_taken
    FROM attempt_answers aa
    JOIN attempts a   ON a.id  = aa.attempt_id
    JOIN quizzes  q   ON q.id  = a.quiz_id
    JOIN categories c ON c.id  = q.category_id
    WHERE a.user_id = ? AND a.is_completed = 1
    GROUP BY c.id ORDER BY accuracy ASC
");
$stats->execute([$userId]);
$categories = $stats->fetchAll();
$hasData    = count($categories) > 0;

// Practice quizzes for weakest category
$recommendations = [];
if ($hasData) {
    $recs = $db->prepare("SELECT q.id, q.title, COUNT(qu.id) AS qcount FROM quizzes q JOIN questions qu ON qu.quiz_id = q.id WHERE q.category_id = ? AND q.is_active = 1 GROUP BY q.id LIMIT 3");
    $recs->execute([$categories[0]['id']]);
    $recommendations = $recs->fetchAll();
}

function accuracyColor(int $acc): string { return $acc >= 75 ? '#10b981' : ($acc >= 50 ? '#f59e0b' : '#ef4444'); }
function accuracyLabel(int $acc): string { return $acc >= 75 ? 'Strong' : ($acc >= 50 ? 'Average' : 'Needs work'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Weak Topics — QuizApp</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:#f0f4ff;min-height:100vh;color:#1e293b}
.topbar{background:#fff;border-bottom:1px solid #e2e8f0;padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar .logo{font-weight:700;color:#4388f5}
.topbar a{font-size:.78rem;color:#64748b;text-decoration:none}.topbar a:hover{color:#4388f5}
.wrap{max-width:760px;margin:0 auto;padding:24px 16px}

.page-title{font-size:1.25rem;font-weight:800;margin-bottom:4px}
.page-sub{font-size:.82rem;color:#64748b;margin-bottom:22px}

.empty{background:#fff;border-radius:14px;padding:40px;text-align:center;border:1.5px dashed #e2e8f0}
.empty h3{font-size:.96rem;font-weight:600;margin-bottom:6px}
.empty p{font-size:.8rem;color:#94a3b8;margin-bottom:18px}

.alert{background:linear-gradient(135deg,#fef3c7,#fde68a);border:1.5px solid #f59e0b;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px}
.alert-icon{font-size:1.3rem;flex-shrink:0}
.alert h3{font-size:.9rem;font-weight:700;color:#92400e;margin-bottom:3px}
.alert p{font-size:.78rem;color:#92400e;opacity:.85;line-height:1.5}

/* Category list */
.section-title{font-size:.82rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px}
.cat-list{display:flex;flex-direction:column;gap:10px;margin-bottom:24px}
.cat-card{background:#fff;border-radius:13px;padding:16px 18px;border:1.5px solid #e2e8f0;display:flex;align-items:center;gap:14px}
.cat-rank{width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.76rem;font-weight:700;flex-shrink:0;background:#f1f5f9;color:#64748b}
.cat-info{flex:1}
.cat-name{font-size:.88rem;font-weight:600;margin-bottom:3px}
.cat-meta{font-size:.72rem;color:#94a3b8}
.bar-bg{height:5px;background:#f1f5f9;border-radius:3px;margin-top:7px;overflow:hidden}
.bar-fill{height:100%;border-radius:3px}
.acc-badge{padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:600;flex-shrink:0;text-align:center;min-width:70px}
.acc-num{font-size:1rem;font-weight:700;display:block}
.acc-lbl{font-size:.66rem;display:block}

/* Practice quizzes */
.rec-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-bottom:28px}
.rec-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:12px;padding:14px;text-decoration:none;color:inherit;transition:all .2s;display:block}
.rec-card:hover{border-color:#4388f5;transform:translateY(-2px);box-shadow:0 4px 14px rgba(67,136,245,.12)}
.rec-card .tag{font-size:.68rem;font-weight:600;color:#ef4444;text-transform:uppercase;margin-bottom:5px}
.rec-card .title{font-size:.82rem;font-weight:600;margin-bottom:5px}
.rec-card .meta{font-size:.72rem;color:#94a3b8}

/* ── AI Practice section ── */
.ai-section{background:linear-gradient(135deg,#f5f0ff,#ede9fe);border:1.5px solid #c4b5fd;border-radius:18px;padding:24px;margin-bottom:24px}
.ai-section-head{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.ai-section-head h2{font-size:1rem;font-weight:800;color:#5b21b6}
.ai-badge{background:#7c3aed;color:#fff;font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:20px}
.ai-section p{font-size:.8rem;color:#6d28d9;margin-bottom:18px;line-height:1.6}

.topic-btns{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.topic-btn{padding:6px 14px;border-radius:20px;border:1.5px solid #c4b5fd;background:#fff;color:#5b21b6;font-size:.76rem;font-weight:600;cursor:pointer;transition:all .2s}
.topic-btn:hover,.topic-btn.active{background:#7c3aed;color:#fff;border-color:#7c3aed}

.ai-controls{display:flex;gap:10px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap}
.ai-controls .field{flex:1;min-width:160px}
.ai-controls label{display:block;font-size:.72rem;font-weight:600;color:#5b21b6;margin-bottom:5px}
.ai-controls input,.ai-controls select{width:100%;padding:9px 12px;border:1.5px solid #c4b5fd;border-radius:10px;font-size:.83rem;outline:none;font-family:'Inter',sans-serif;background:#fff;color:#1e293b;transition:all .2s}
.ai-controls input:focus,.ai-controls select:focus{border-color:#7c3aed;box-shadow:0 0 0 3px rgba(124,58,237,.1)}
.btn-ai{padding:9px 20px;background:linear-gradient(135deg,#7c3aed,#4388f5);color:#fff;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:all .2s;box-shadow:0 4px 12px rgba(124,58,237,.3);display:flex;align-items:center;gap:7px;height:40px}
.btn-ai:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(124,58,237,.4)}
.btn-ai:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* AI loading */
.ai-loading{text-align:center;padding:24px;display:none}
.ai-loading.show{display:block}
.spinner{width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:#7c3aed;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 10px}
@keyframes spin{to{transform:rotate(360deg)}}

.ai-err{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:10px 14px;font-size:.8rem;margin-bottom:14px;display:none}
.ai-err.show{display:flex;align-items:center;gap:8px}

/* AI questions */
.ai-q-area{display:none}.ai-q-area.show{display:block}
.ai-q-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.ai-q-header h3{font-size:.9rem;font-weight:700;color:#5b21b6}
.score-pill{background:#7c3aed;color:#fff;padding:3px 12px;border-radius:20px;font-size:.74rem;font-weight:600}
.ai-prog-bar{height:4px;background:#ddd6fe;border-radius:2px;margin-bottom:14px;overflow:hidden}
.ai-prog-fill{height:100%;background:linear-gradient(90deg,#7c3aed,#4388f5);border-radius:2px;transition:width .4s}

.q-card{background:#fff;border-radius:13px;padding:18px;margin-bottom:10px;border:1.5px solid #e2e8f0;transition:border-color .2s}
.q-num{font-size:.7rem;font-weight:600;color:#94a3b8;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.q-text{font-size:.9rem;font-weight:500;line-height:1.55;margin-bottom:13px}
.options{display:flex;flex-direction:column;gap:7px}
.opt{padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;font-size:.82rem;cursor:pointer;transition:all .18s;display:flex;align-items:center;gap:9px;background:#fafafa;user-select:none}
.opt:hover:not(.disabled){border-color:#7c3aed;background:#f5f0ff;transform:translateX(3px)}
.opt.correct{border-color:#10b981;background:#ecfdf5;color:#065f46;font-weight:500}
.opt.wrong{border-color:#ef4444;background:#fef2f2;color:#b91c1c}
.opt.show-correct{border-color:#10b981;background:#ecfdf5;color:#065f46}
.opt.disabled{cursor:default}
.opt-icon{width:20px;height:20px;border-radius:50%;border:1.5px solid #cbd5e1;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;transition:all .2s}
.opt.correct .opt-icon,.opt.show-correct .opt-icon{background:#10b981;border-color:#10b981;color:#fff}
.opt.wrong .opt-icon{background:#ef4444;border-color:#ef4444;color:#fff}
.explanation{margin-top:10px;padding:9px 12px;border-radius:8px;background:#f5f0ff;border:1px solid #ddd6fe;font-size:.78rem;color:#5b21b6;line-height:1.5;display:none}
.explanation.show{display:flex;align-items:flex-start;gap:7px}

.ai-done{background:linear-gradient(135deg,#7c3aed,#4388f5);color:#fff;border-radius:14px;padding:24px;text-align:center;margin-top:10px;display:none}
.ai-done.show{display:block}
.ai-done h3{font-size:1.1rem;font-weight:800;margin-bottom:6px}
.ai-done .big{font-size:2.8rem;font-weight:800;margin:8px 0}
.ai-done p{font-size:.8rem;opacity:.85;margin-bottom:16px}
.btn-white{background:#fff;color:#7c3aed;padding:9px 20px;border-radius:10px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;transition:all .2s}
.btn-white:hover{background:#f5f0ff}
.btn-outline{background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.4);padding:9px 20px;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s}
.btn-outline:hover{background:rgba(255,255,255,.25)}
.btn-link{display:inline-block;padding:9px 20px;background:linear-gradient(135deg,#4388f5,#2a5dc2);color:#fff;border-radius:10px;font-size:.83rem;font-weight:700;text-decoration:none;transition:all .2s}
.btn-link:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(67,136,245,.3)}

@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.cat-card{animation:fadeUp .35s ease both}
</style>
</head>
<body>
<div class="topbar">
    <span class="logo">⚡ QuizApp</span>
    <a href="dashboard.php">← Dashboard</a>
</div>
<div class="wrap">
    <p class="page-title">🧠 Weak Topic Detector</p>
    <p class="page-sub">See where you're struggling — then fix it with AI-generated practice questions</p>

<?php if (!$hasData): ?>
    <div class="empty">
        <h3>No data yet</h3>
        <p>Take a few quizzes first and we'll analyse your weak topics automatically.</p>
        <a href="quiz-list.php" class="btn-link">Browse Quizzes →</a>
    </div>
<?php else: ?>

    <?php if ($categories[0]['accuracy'] < 70): ?>
    <div class="alert">
        <div class="alert-icon">⚠️</div>
        <div>
            <h3>You're struggling with <?= htmlspecialchars($categories[0]['category']) ?></h3>
            <p>Your accuracy is only <?= $categories[0]['accuracy'] ?>% here. Use the AI practice below to improve fast.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Category breakdown -->
    <p class="section-title">Your accuracy by category</p>
    <div class="cat-list">
    <?php foreach ($categories as $i => $cat): ?>
        <?php $color = accuracyColor($cat['accuracy']); ?>
        <div class="cat-card" style="animation-delay:<?= $i * 0.06 ?>s">
            <div class="cat-rank"><?= $i+1 ?></div>
            <div class="cat-info">
                <div class="cat-name"><?= htmlspecialchars($cat['category']) ?></div>
                <div class="cat-meta"><?= $cat['total_answers'] ?> questions answered · <?= $cat['quizzes_taken'] ?> quiz<?= $cat['quizzes_taken'] > 1 ? 'zes' : '' ?> taken</div>
                <div class="bar-bg"><div class="bar-fill" style="width:<?= $cat['accuracy'] ?>%;background:<?= $color ?>"></div></div>
            </div>
            <div class="acc-badge" style="background:<?= $color ?>18;color:<?= $color ?>">
                <span class="acc-num"><?= $cat['accuracy'] ?>%</span>
                <span class="acc-lbl"><?= accuracyLabel($cat['accuracy']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- Practice quizzes from quiz bank -->
    <?php if ($recommendations): ?>
    <p class="section-title">📚 Quiz bank — <?= htmlspecialchars($categories[0]['category']) ?></p>
    <div class="rec-grid" style="margin-bottom:28px">
        <?php foreach ($recommendations as $r): ?>
        <a href="practice.php?quiz_id=<?= $r['id'] ?>" class="rec-card">
            <div class="tag">⚡ Weak area</div>
            <div class="title"><?= htmlspecialchars($r['title']) ?></div>
            <div class="meta"><?= $r['qcount'] ?> questions · Practice mode</div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── AI Practice section ── -->
    <div class="ai-section">
        <div class="ai-section-head">
            <h2>🤖 AI Practice for Weak Topics</h2>
            <span class="ai-badge">Powered by Gemini</span>
        </div>
        <p>Select a weak topic below and generate custom practice questions instantly. The AI focuses on the tricky concepts you're most likely getting wrong.</p>

        <!-- Topic quick-select buttons from actual weak categories -->
        <div class="topic-btns">
            <?php foreach ($categories as $cat): ?>
            <button class="topic-btn <?= $cat['accuracy'] < 60 ? 'active' : '' ?>"
                onclick="selectTopic('<?= htmlspecialchars($cat['category'], ENT_QUOTES) ?>', this)">
                <?= $cat['accuracy'] < 60 ? '⚠️ ' : '' ?><?= htmlspecialchars($cat['category']) ?> (<?= $cat['accuracy'] ?>%)
            </button>
            <?php endforeach; ?>
        </div>

        <div class="ai-controls">
            <div class="field">
                <label>Topic (auto-filled or custom)</label>
                <input type="text" id="aiTopic" placeholder="e.g. <?= htmlspecialchars($categories[0]['category']) ?>" value="<?= htmlspecialchars($categories[0]['category']) ?>">
            </div>
            <div class="field" style="max-width:110px">
                <label>Questions</label>
                <select id="aiCount">
                    <option value="3">3</option>
                    <option value="5" selected>5</option>
                    <option value="7">7</option>
                    <option value="10">10</option>
                </select>
            </div>
            <button class="btn-ai" id="genBtn" onclick="generateAI()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                Generate
            </button>
        </div>

        <?php if (!$GEMINI_KEY || $GEMINI_KEY === 'your_gemini_api_key_here'): ?>
        <div class="ai-err show">⚙️ Add your <code>GEMINI_API_KEY</code> to the <code>.env</code> file to enable AI practice.</div>
        <?php else: ?>
        <div class="ai-err" id="aiErr"></div>
        <?php endif; ?>

        <div class="ai-loading" id="aiLoading">
            <div class="spinner"></div>
            <p style="font-size:.8rem;color:#6d28d9">Generating questions about "<span id="loadingTopic"></span>"...</p>
        </div>

        <div class="ai-q-area" id="aiQArea">
            <div class="ai-q-header">
                <h3 id="aiQTitle"></h3>
                <span class="score-pill"><span id="aiCorrect">0</span>/<span id="aiTotal">0</span> ✓</span>
            </div>
            <div class="ai-prog-bar"><div class="ai-prog-fill" id="aiProg" style="width:0%"></div></div>
            <div id="aiQList"></div>
            <div class="ai-done" id="aiDone">
                <h3>Practice Done! 🎉</h3>
                <div class="big" id="aiDoneScore"></div>
                <p id="aiDoneMsg"></p>
                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
                    <button class="btn-white" onclick="generateAI()">🔄 Try Again</button>
                    <button class="btn-outline" onclick="resetAI()">New Topic</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<script>
let aiQuestions = [], aiAnswered = 0, aiCorrect = 0;

function selectTopic(name, btn) {
    document.querySelectorAll('.topic-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('aiTopic').value = name;
}

async function generateAI() {
    const topic = document.getElementById('aiTopic').value.trim();
    const count = document.getElementById('aiCount').value;
    if (!topic) { showAiErr('Please enter a topic.'); return; }

    hideAiErr();
    document.getElementById('aiLoading').classList.add('show');
    document.getElementById('aiQArea').classList.remove('show');
    document.getElementById('loadingTopic').textContent = topic;

    const btn = document.getElementById('genBtn');
    if (btn) btn.disabled = true;

    const fd = new FormData();
    fd.append('topic', topic);
    fd.append('count', count);

    try {
        const res  = await fetch('weak-topics.php', {method:'POST', body:fd});
        const data = await res.json();
        document.getElementById('aiLoading').classList.remove('show');
        if (btn) btn.disabled = false;

        if (data.error) { showAiErr(data.error); return; }
        renderAIQuestions(data.questions, topic);
    } catch(e) {
        document.getElementById('aiLoading').classList.remove('show');
        if (btn) btn.disabled = false;
        showAiErr('Network error. Please try again.');
    }
}

function renderAIQuestions(qs, topic) {
    aiQuestions = qs; aiAnswered = 0; aiCorrect = 0;
    document.getElementById('aiQTitle').textContent = `"${topic}" — AI Practice`;
    document.getElementById('aiCorrect').textContent = 0;
    document.getElementById('aiTotal').textContent   = qs.length;
    document.getElementById('aiProg').style.width    = '0%';
    document.getElementById('aiDone').classList.remove('show');
    document.getElementById('aiQArea').classList.add('show');

    const list = document.getElementById('aiQList');
    list.innerHTML = '';
    qs.forEach((q, i) => {
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id = 'aqc-' + i;
        card.style.animationDelay = (i * .06) + 's';
        card.innerHTML = `
            <div class="q-num">Question ${i+1} of ${qs.length}</div>
            <div class="q-text">${q.question}</div>
            <div class="options">
                ${q.options.map((o, oi) => `
                <div class="opt" onclick="aiAnswer(${i}, ${oi}, this)">
                    <div class="opt-icon">${String.fromCharCode(65+oi)}</div>
                    <span>${o}</span>
                </div>`).join('')}
            </div>
            <div class="explanation" id="aexp-${i}">${q.explanation}</div>`;
        list.appendChild(card);
    });
    document.getElementById('aiQArea').scrollIntoView({behavior:'smooth', block:'start'});
}

function aiAnswer(qi, oi, el) {
    const card = document.getElementById('aqc-' + qi);
    if (card.dataset.done) return;
    card.dataset.done = '1';
    const q = aiQuestions[qi];
    card.querySelectorAll('.opt').forEach((o, idx) => {
        o.classList.add('disabled');
        const icon = o.querySelector('.opt-icon');
        if (idx === q.correct) { o.classList.add(oi === idx ? 'correct' : 'show-correct'); icon.textContent = '✓'; }
        else if (idx === oi && oi !== q.correct) { o.classList.add('wrong'); icon.textContent = '✗'; }
    });
    if (q.explanation) document.getElementById('aexp-' + qi).classList.add('show');

    aiAnswered++;
    if (oi === q.correct) { aiCorrect++; document.getElementById('aiCorrect').textContent = aiCorrect; }
    document.getElementById('aiProg').style.width = (aiAnswered/aiQuestions.length*100) + '%';
    if (aiAnswered === aiQuestions.length) setTimeout(showAIDone, 600);
}

function showAIDone() {
    const pct = Math.round(aiCorrect/aiQuestions.length*100);
    document.getElementById('aiDoneScore').textContent = pct + '%';
    document.getElementById('aiDoneMsg').textContent =
        pct === 100 ? 'Perfect! You mastered this topic! 🌟' :
        pct >= 70  ? `${aiCorrect}/${aiQuestions.length} correct — great improvement! Keep it up!` :
        `${aiCorrect}/${aiQuestions.length} correct — keep practicing, you're getting there! 💪`;
    document.getElementById('aiDone').classList.add('show');
    document.getElementById('aiDone').scrollIntoView({behavior:'smooth'});
}

function resetAI() {
    document.getElementById('aiTopic').value = '';
    document.getElementById('aiQArea').classList.remove('show');
    document.getElementById('aiTopic').focus();
    window.scrollTo({top:document.querySelector('.ai-section').offsetTop - 80, behavior:'smooth'});
}

function showAiErr(msg) { const e = document.getElementById('aiErr'); if(e){e.innerHTML='⚠️ '+msg; e.classList.add('show');} }
function hideAiErr()    { const e = document.getElementById('aiErr'); if(e) e.classList.remove('show'); }
</script>
</body>
</html>