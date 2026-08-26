<?php
$pageTitle = 'AI Practice';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/gemini.php';

$error = '';
$practiceQuestions = null;
$topic = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_practice'])) {
    verifyCsrf();

    if (!checkRateLimit('ai_practice_' . $_SESSION['user_id'], 10, 300)) {
        $error = 'Too many requests — you can generate up to 10 practice sets every 5 minutes. Please wait a moment.';
    } else {
        $topic      = trim($_POST['topic'] ?? '');
        $count      = max(1, min((int)($_POST['count'] ?? 5), 10));
        $difficulty = $_POST['difficulty'] ?? 'medium';

        if ($topic === '') {
            $error = 'Please enter a topic you want to practice.';
        } else {
            try {
                $practiceQuestions = generateQuizQuestions($topic, $count, $difficulty);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">AI Practice</div>
    <div class="page-subtitle">Generate extra practice questions on any topic — instant feedback, not graded or saved</div>
</div>

<div id="offlineBanner" class="alert alert-danger" style="display:none">
    You're currently offline. AI Practice needs an internet connection — reconnect and try again.
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px">
    <form method="POST" id="practiceForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <div class="form-group" style="position:relative">
            <label>What do you want to practice? <span style="font-size:12px;color:var(--muted)">(Type any topic or category e.g. "Politics", "Science")</span></label>
            <input type="text" name="topic" id="practiceTopicInput" placeholder="e.g. Politics, Photosynthesis, World capitals, Indian Constitution" required autocomplete="off" value="<?= htmlspecialchars($topic) ?>">
            <div id="practiceAutocompleteList" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:99;background:var(--surface,#ffffff);border:1px solid var(--border,#E4E6EA);border-radius:var(--radius-sm,6px);max-height:220px;overflow-y:auto;box-shadow:0 6px 18px rgba(0,0,0,.08);margin-top:4px"></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="form-group">
                <label>Number of questions</label>
                <input type="number" name="count" min="1" max="10" value="5">
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

        <button type="submit" name="generate_practice" id="generateBtn" class="btn btn-primary">Generate practice questions</button>
    </form>
</div>

<?php if ($practiceQuestions): ?>
<div id="practiceResults">
    <?php foreach ($practiceQuestions as $i => $q): ?>
    <div class="question-card" data-question-index="<?= $i ?>">
        <div class="question-number">Question <?= $i + 1 ?> of <?= count($practiceQuestions) ?></div>
        <div class="question-text"><?= htmlspecialchars($q['question']) ?></div>

        <?php foreach ($q['options'] as $j => $opt): ?>
        <div class="option-label practice-option" data-correct="<?= $opt['correct'] ? '1' : '0' ?>" tabindex="0">
            <?= htmlspecialchars($opt['text']) ?>
        </div>
        <?php endforeach; ?>

        <div class="practice-feedback" style="display:none;margin-top:10px;font-size:13px;font-weight:600"></div>
    </div>
    <?php endforeach; ?>

    <div class="card" style="text-align:center">
        <p style="color:var(--muted);font-size:13.5px;margin-bottom:14px">Want to try another topic?</p>
        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('practiceForm').scrollIntoView({behavior:'smooth'})">
            Generate more
        </button>
    </div>
</div>

<script>
document.querySelectorAll('.practice-option').forEach(option => {
    option.style.cursor = 'pointer';
    option.addEventListener('click', function () {
        const card = this.closest('.question-card');
        if (card.dataset.answered === '1') return; // already answered, lock it
        card.dataset.answered = '1';

        const allOptions = card.querySelectorAll('.practice-option');
        const feedback = card.querySelector('.practice-feedback');
        const isCorrect = this.dataset.correct === '1';

        allOptions.forEach(opt => {
            opt.style.cursor = 'default';
            if (opt.dataset.correct === '1') {
                opt.classList.add('correct');
            } else if (opt === this) {
                opt.classList.add('wrong');
            }
        });

        feedback.style.display = 'block';
        feedback.style.color = isCorrect ? 'var(--success)' : 'var(--danger)';
        feedback.textContent = isCorrect ? '✓ Correct!' : '✗ Not quite — the correct answer is highlighted above.';
    });
});
</script>
<?php endif; ?>

<script>
(function () {
    const offlineBanner = document.getElementById('offlineBanner');
    const generateBtn   = document.getElementById('generateBtn');
    const practiceForm  = document.getElementById('practiceForm');
    const topicInput    = document.getElementById('practiceTopicInput');
    const autoList      = document.getElementById('practiceAutocompleteList');

    const suggestions = [
        'Politics & Government',
        'Political Theories & Thinkers',
        'Indian Constitution & Polity',
        'World Political Systems',
        'Elections & Voting Systems',
        'Public Policy & Administration',
        'International Relations & Diplomacy',
        'Science - Physics',
        'Science - Chemistry',
        'Science - Biology & Photosynthesis',
        'World History & Civilizations',
        'Mathematics - Algebra & Geometry',
        'Technology - AI & Computer Science',
        'Geography - Capitals & Rivers',
        'Sports & Olympics',
        'Economics & Stock Markets',
        'Law & Constitution',
        'Medicine & Human Anatomy',
        'Psychology & Human Behavior',
        'Philosophy & Ethics',
        'Environment & Ecology',
        'Astronomy & Space Exploration'
    ];

    function renderAutocomplete(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q || !autoList) {
            if (autoList) autoList.style.display = 'none';
            return;
        }

        const matches = suggestions.filter(s => s.toLowerCase().includes(q));
        if (matches.length === 0) {
            autoList.style.display = 'none';
            return;
        }

        autoList.innerHTML = '';
        matches.slice(0, 7).forEach(text => {
            const div = document.createElement('div');
            div.style.padding = '9px 14px';
            div.style.cursor = 'pointer';
            div.style.borderBottom = '1px solid var(--border,#E4E6EA)';
            div.style.fontSize = '13.5px';
            div.style.color = 'var(--text,#111318)';
            div.style.background = 'var(--surface,#ffffff)';
            div.textContent = text;

            div.addEventListener('mouseenter', () => {
                div.style.background = 'var(--accent-light,#E6F1FB)';
                div.style.color = 'var(--accent,#185FA5)';
            });
            div.addEventListener('mouseleave', () => {
                div.style.background = 'var(--surface,#ffffff)';
                div.style.color = 'var(--text,#111318)';
            });
            div.addEventListener('mousedown', (e) => {
                e.preventDefault();
                topicInput.value = text;
                autoList.style.display = 'none';
            });

            autoList.appendChild(div);
        });

        autoList.style.display = 'block';
    }

    if (topicInput) {
        topicInput.addEventListener('input', function () {
            renderAutocomplete(this.value);
        });
        topicInput.addEventListener('focus', function () {
            if (this.value.trim()) renderAutocomplete(this.value);
        });
        topicInput.addEventListener('blur', function () {
            setTimeout(() => { if (autoList) autoList.style.display = 'none'; }, 200);
        });
    }

    function updateOnlineState() {
        const isOnline = navigator.onLine;
        if (offlineBanner) offlineBanner.style.display = isOnline ? 'none' : 'block';
        if (generateBtn) {
            generateBtn.disabled = !isOnline;
            if (!isOnline && !generateBtn.dataset.originalText) {
                generateBtn.dataset.originalText = generateBtn.textContent;
                generateBtn.textContent = 'Offline — connect to the internet';
            } else if (isOnline && generateBtn.dataset.originalText) {
                generateBtn.textContent = generateBtn.dataset.originalText;
            }
        }
    }

    if (practiceForm) {
        practiceForm.addEventListener('submit', function (e) {
            if (!navigator.onLine) {
                e.preventDefault();
                if (offlineBanner) {
                    offlineBanner.style.display = 'block';
                    offlineBanner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }

    window.addEventListener('online', updateOnlineState);
    window.addEventListener('offline', updateOnlineState);
    updateOnlineState();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>