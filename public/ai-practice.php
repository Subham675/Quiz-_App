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

<div class="mb-4">
    <h1 class="page-title">AI Practice</h1>
    <p class="page-subtitle">Generate extra practice questions on any topic — instant feedback, not graded or saved</p>
</div>

<div id="offlineBanner" class="alert alert-danger" style="display:none">
    You're currently offline. AI Practice needs an internet connection — reconnect and try again.
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" id="practiceForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="mb-3 position-relative">
                <label class="form-label small fw-medium">What do you want to practice? <span class="text-muted fw-normal">(Type any topic or category e.g. "Politics", "Science")</span></label>
                <input type="text" class="form-control" name="topic" id="practiceTopicInput" placeholder="e.g. Politics, Photosynthesis, World capitals, Indian Constitution" required autocomplete="off" value="<?= htmlspecialchars($topic) ?>">
                <div id="practiceAutocompleteList" class="dropdown-menu w-100 shadow" style="display:none;max-height:220px;overflow-y:auto"></div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Number of questions</label>
                    <input type="number" class="form-control" name="count" min="1" max="10" value="5">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-medium">Difficulty</label>
                    <select name="difficulty" class="form-select">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="generate_practice" id="generateBtn" class="btn btn-primary">
                <i class="bi bi-stars me-1"></i> Generate practice questions
            </button>
        </form>
    </div>
</div>

<?php if ($practiceQuestions): ?>
<div id="practiceResults">
    <?php foreach ($practiceQuestions as $i => $q): ?>
    <div class="question-card mb-3" data-question-index="<?= $i ?>">
        <div class="question-number">Question <?= $i + 1 ?> of <?= count($practiceQuestions) ?></div>
        <div class="question-text"><?= htmlspecialchars($q['question']) ?></div>

        <?php foreach ($q['options'] as $j => $opt): ?>
        <div class="option-label practice-option" data-correct="<?= $opt['correct'] ? '1' : '0' ?>" tabindex="0">
            <?= htmlspecialchars($opt['text']) ?>
        </div>
        <?php endforeach; ?>

        <div class="practice-feedback mt-2 fw-semibold small" style="display:none"></div>
    </div>
    <?php endforeach; ?>

    <div class="card text-center mb-4">
        <div class="card-body">
            <p class="text-muted small mb-3">Want to try another topic?</p>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('practiceForm').scrollIntoView({behavior:'smooth'})">
                Generate more
            </button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.practice-option').forEach(option => {
    option.style.cursor = 'pointer';
    option.addEventListener('click', function () {
        const card = this.closest('.question-card');
        if (card.dataset.answered === '1') return;
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
        feedback.className = 'practice-feedback mt-2 fw-semibold small ' + (isCorrect ? 'text-success' : 'text-danger');
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
            const item = document.createElement('a');
            item.className = 'dropdown-item';
            item.href = '#';
            item.textContent = text;

            item.addEventListener('mousedown', (e) => {
                e.preventDefault();
                topicInput.value = text;
                autoList.style.display = 'none';
            });

            autoList.appendChild(item);
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