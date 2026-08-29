<?php
$pageTitle = 'Browse Quizzes';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

$catId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$categories = $db->query("SELECT id, name FROM categories WHERE deleted_at IS NULL ORDER BY name")->fetchAll();

$sql = "
    SELECT q.id, q.title, q.description, q.time_limit_seconds, q.negative_marking,
           q.starts_at, q.ends_at, c.name AS category, c.id AS category_id,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS q_count,
           (SELECT id FROM attempts WHERE quiz_id = q.id AND user_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1) AS attempt_id
    FROM quizzes q
    JOIN categories c ON c.id = q.category_id
    WHERE q.is_active = 1 AND q.deleted_at IS NULL AND c.deleted_at IS NULL
";
$params = [$userId];

if ($catId > 0) {
    $sql .= " AND q.category_id = ?";
    $params[] = $catId;
}
$sql .= " ORDER BY q.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$quizzes = $stmt->fetchAll();

$now = new DateTime();

function getScheduleStatus(array $quiz, DateTime $now): string {
    $startsAt = !empty($quiz['starts_at']) ? new DateTime($quiz['starts_at']) : null;
    $endsAt   = !empty($quiz['ends_at'])   ? new DateTime($quiz['ends_at'])   : null;

    if ($startsAt && $now < $startsAt) return 'upcoming';
    if ($endsAt   && $now > $endsAt)   return 'expired';
    return 'active';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Browse Quizzes</h1>
    <p class="page-subtitle">Pick a quiz and test your knowledge</p>
</div>

<!-- Live search with category autocomplete -->
<div class="position-relative mb-3" style="max-width:550px">
    <div class="position-relative">
        <input type="text" id="liveSearchInput" class="form-control" placeholder="🔍 Search any category or quiz (e.g. Politics, Science, Math)..." autocomplete="off">
        <button id="clearSearchBtn" type="button" class="btn-close position-absolute top-50 end-0 translate-middle-y me-2" style="display:none" aria-label="Clear"></button>
    </div>
    <div id="searchDropdown" class="dropdown-menu w-100 shadow" style="display:none;max-height:260px;overflow-y:auto"></div>
</div>

<!-- Category pills -->
<div id="categoryPills" class="d-flex flex-wrap gap-2 mb-4">
    <a href="quiz-list.php" class="btn btn-sm cat-pill <?= $catId === 0 ? 'btn-primary' : 'btn-outline-secondary' ?>" data-cat-id="0" data-cat-name="All">All</a>
    <?php foreach ($categories as $c): ?>
        <a href="quiz-list.php?category=<?= $c['id'] ?>"
           class="btn btn-sm cat-pill <?= $catId === (int)$c['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>"
           data-cat-id="<?= $c['id'] ?>"
           data-cat-name="<?= htmlspecialchars($c['name']) ?>">
            <?= htmlspecialchars($c['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div id="noMatchCard" class="card" style="display:none">
    <div class="card-body text-center">
        <p class="text-muted">No quizzes found matching your search. Try another keyword!</p>
    </div>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card" id="emptyCategoryCard">
        <div class="card-body">
            <p class="text-muted small">No quizzes found in this category yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3" id="quizGrid">
        <?php foreach ($quizzes as $q):
            $scheduleStatus = getScheduleStatus($q, $now);
            $isLocked = $scheduleStatus !== 'active';
        ?>
        <div class="col-md-6 col-lg-4 quiz-card-item"
             style="<?= $isLocked ? 'opacity:.75' : '' ?>"
             data-title="<?= htmlspecialchars(strtolower($q['title'])) ?>"
             data-category="<?= htmlspecialchars(strtolower($q['category'])) ?>"
             data-desc="<?= htmlspecialchars(strtolower($q['description'] ?? '')) ?>">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-1">
                        <span class="badge bg-info text-dark"><?= htmlspecialchars($q['category']) ?></span>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ($scheduleStatus === 'upcoming'): ?>
                                <span class="badge bg-warning text-dark">⏳ Upcoming</span>
                            <?php elseif ($scheduleStatus === 'expired'): ?>
                                <span class="badge bg-danger">🔒 Expired</span>
                            <?php elseif ($q['attempt_id']): ?>
                                <span class="badge bg-success">✓ Completed</span>
                            <?php endif; ?>
                            <?php if ((float)($q['negative_marking'] ?? 0) > 0): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                                    −<?= number_format($q['negative_marking'], 2) ?>/wrong
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h6 class="card-title mb-1"><?= htmlspecialchars($q['title']) ?></h6>
                    <p class="text-muted small mb-3" style="min-height:36px">
                        <?= htmlspecialchars($q['description'] ?? '') ?>
                    </p>
                    <small class="text-muted mb-2">
                        <?= $q['q_count'] ?> questions · <?= round($q['time_limit_seconds'] / 60) ?> min
                    </small>

                    <?php if (!empty($q['starts_at']) || !empty($q['ends_at'])): ?>
                    <small class="text-muted mb-2">
                        <?php if (!empty($q['starts_at'])): ?>
                            🗓 Opens: <?= date('d M Y, h:i A', strtotime($q['starts_at'])) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($q['ends_at'])): ?>
                            🔒 Closes: <?= date('d M Y, h:i A', strtotime($q['ends_at'])) ?>
                        <?php endif; ?>
                    </small>
                    <?php endif; ?>

                    <div class="mt-auto">
                        <?php if ($scheduleStatus === 'upcoming'): ?>
                            <button class="btn btn-outline-secondary btn-sm w-100" disabled>Not started yet</button>
                        <?php elseif ($scheduleStatus === 'expired'): ?>
                            <button class="btn btn-outline-secondary btn-sm w-100" disabled>Quiz closed</button>
                        <?php elseif ($q['attempt_id']): ?>
                            <a href="result.php?attempt=<?= $q['attempt_id'] ?>" class="btn btn-outline-secondary btn-sm w-100">View result</a>
                        <?php else: ?>
                            <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn btn-primary btn-sm w-100">Start quiz</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
(function () {
    const searchInput = document.getElementById('liveSearchInput');
    const clearBtn    = document.getElementById('clearSearchBtn');
    const dropdown    = document.getElementById('searchDropdown');
    const grid        = document.getElementById('quizGrid');
    const noMatchCard = document.getElementById('noMatchCard');
    const pills       = document.querySelectorAll('.cat-pill');
    const cards       = document.querySelectorAll('.quiz-card-item');

    const allCategories = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $categories)) ?>;

    function filterContent(query) {
        const q = (query || '').trim().toLowerCase();

        if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

        pills.forEach(pill => {
            const name = (pill.dataset.catName || '').toLowerCase();
            if (q === '' || pill.dataset.catId === '0' || name.includes(q)) {
                pill.style.display = '';
            } else {
                pill.style.display = 'none';
            }
        });

        let visibleCount = 0;
        cards.forEach(card => {
            const title = card.dataset.title || '';
            const cat   = card.dataset.category || '';
            const desc  = card.dataset.desc || '';

            if (q === '' || title.includes(q) || cat.includes(q) || desc.includes(q)) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noMatchCard && cards.length > 0) {
            noMatchCard.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    function renderDropdown(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q) {
            dropdown.style.display = 'none';
            return;
        }

        const matchedCats = allCategories.filter(c => c.name.toLowerCase().includes(q));

        if (matchedCats.length === 0) {
            dropdown.style.display = 'none';
            return;
        }

        dropdown.innerHTML = '';
        matchedCats.slice(0, 6).forEach(cat => {
            const item = document.createElement('a');
            item.href = 'quiz-list.php?category=' + cat.id;
            item.className = 'dropdown-item d-flex justify-content-between align-items-center';
            item.innerHTML = `
                <div>📁 <strong>${escapeHtml(cat.name)}</strong></div>
                <span class="badge bg-primary bg-opacity-10 text-primary">Filter category &rarr;</span>
            `;
            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterContent(this.value);
            renderDropdown(this.value);
        });

        searchInput.addEventListener('focus', function () {
            if (this.value.trim()) renderDropdown(this.value);
        });

        searchInput.addEventListener('blur', function () {
            setTimeout(() => { dropdown.style.display = 'none'; }, 250);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            filterContent('');
            dropdown.style.display = 'none';
            searchInput.focus();
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>