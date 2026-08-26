<?php
$pageTitle = 'Browse Quizzes';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

$catId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$sql = "
    SELECT q.id, q.title, q.description, q.time_limit_seconds, q.negative_marking,
           q.starts_at, q.ends_at, c.name AS category, c.id AS category_id,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS q_count,
           (SELECT id FROM attempts WHERE quiz_id = q.id AND user_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1) AS attempt_id
    FROM quizzes q
    JOIN categories c ON c.id = q.category_id
    WHERE q.is_active = 1
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

<div class="page-header">
    <div class="page-title">Browse Quizzes</div>
    <div class="page-subtitle">Pick a quiz and test your knowledge</div>
</div>

<!-- Live search with category autocomplete -->
<div style="position:relative;margin-bottom:16px;max-width:550px">
    <div style="position:relative">
        <input type="text" id="liveSearchInput" placeholder="🔍 Search any category or quiz (e.g. Politics, Science, Math)..." autocomplete="off" style="padding-left:14px;height:42px;border-radius:8px">
        <button id="clearSearchBtn" type="button" style="display:none;position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;font-size:16px">&times;</button>
    </div>
    <div id="searchDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:99;background:var(--surface,#ffffff);border:1px solid var(--border,#E4E6EA);border-radius:var(--radius-md,10px);max-height:260px;overflow-y:auto;box-shadow:0 8px 24px rgba(0,0,0,.08);margin-top:4px"></div>
</div>

<!-- Category pills -->
<div id="categoryPills" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <a href="quiz-list.php" class="btn btn-sm cat-pill <?= $catId === 0 ? 'btn-primary' : 'btn-outline' ?>" data-cat-id="0" data-cat-name="All">All</a>
    <?php foreach ($categories as $c): ?>
        <a href="quiz-list.php?category=<?= $c['id'] ?>"
           class="btn btn-sm cat-pill <?= $catId === (int)$c['id'] ? 'btn-primary' : 'btn-outline' ?>"
           data-cat-id="<?= $c['id'] ?>"
           data-cat-name="<?= htmlspecialchars($c['name']) ?>">
            <?= htmlspecialchars($c['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<div id="noMatchCard" class="card" style="display:none;text-align:center;padding:30px">
    <p style="color:var(--muted);font-size:14px">No quizzes found matching your search. Try another keyword!</p>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card" id="emptyCategoryCard">
        <p style="color:var(--muted);font-size:13.5px">No quizzes found in this category yet.</p>
    </div>
<?php else: ?>
    <div class="three-col" id="quizGrid">
        <?php foreach ($quizzes as $q):
            $scheduleStatus = getScheduleStatus($q, $now);
            $isLocked = $scheduleStatus !== 'active';
        ?>
        <div class="card quiz-card-item"
             style="<?= $isLocked ? 'opacity:.75' : '' ?>"
             data-title="<?= htmlspecialchars(strtolower($q['title'])) ?>"
             data-category="<?= htmlspecialchars(strtolower($q['category'])) ?>"
             data-desc="<?= htmlspecialchars(strtolower($q['description'] ?? '')) ?>">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;gap:6px">
                <span class="badge badge-info"><?= htmlspecialchars($q['category']) ?></span>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                    <?php if ($scheduleStatus === 'upcoming'): ?>
                        <span class="badge badge-warning">⏳ Upcoming</span>
                    <?php elseif ($scheduleStatus === 'expired'): ?>
                        <span class="badge badge-danger">🔒 Expired</span>
                    <?php elseif ($q['attempt_id']): ?>
                        <span class="badge badge-success">✓ Completed</span>
                    <?php endif; ?>
                    <?php if ((float)($q['negative_marking'] ?? 0) > 0): ?>
                        <span class="badge" style="background:rgba(239,68,68,.12);color:var(--danger);border:1px solid rgba(239,68,68,.2)">
                            −<?= number_format($q['negative_marking'], 2) ?>/wrong
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-title" style="margin-bottom:6px"><?= htmlspecialchars($q['title']) ?></div>
            <p style="font-size:13px;color:var(--muted);margin-bottom:14px;min-height:36px">
                <?= htmlspecialchars($q['description'] ?? '') ?>
            </p>
            <div style="font-size:12px;color:var(--muted);margin-bottom:6px">
                <?= $q['q_count'] ?> questions · <?= round($q['time_limit_seconds'] / 60) ?> min
            </div>

            <?php if (!empty($q['starts_at']) || !empty($q['ends_at'])): ?>
            <div style="font-size:11.5px;color:var(--muted);margin-bottom:10px">
                <?php if (!empty($q['starts_at'])): ?>
                    🗓 Opens: <?= date('d M Y, h:i A', strtotime($q['starts_at'])) ?><br>
                <?php endif; ?>
                <?php if (!empty($q['ends_at'])): ?>
                    🔒 Closes: <?= date('d M Y, h:i A', strtotime($q['ends_at'])) ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($scheduleStatus === 'upcoming'): ?>
                <button class="btn btn-outline btn-sm" style="width:100%;justify-content:center;cursor:not-allowed;opacity:.6" disabled>Not started yet</button>
            <?php elseif ($scheduleStatus === 'expired'): ?>
                <button class="btn btn-outline btn-sm" style="width:100%;justify-content:center;cursor:not-allowed;opacity:.6" disabled>Quiz closed</button>
            <?php elseif ($q['attempt_id']): ?>
                <a href="result.php?attempt=<?= $q['attempt_id'] ?>" class="btn btn-outline btn-sm" style="width:100%;justify-content:center">
                    View result
                </a>
            <?php else: ?>
                <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">
                    Start quiz
                </a>
            <?php endif; ?>
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

        // Filter category pills visibility if typing
        pills.forEach(pill => {
            const name = (pill.dataset.catName || '').toLowerCase();
            if (q === '' || pill.dataset.catId === '0' || name.includes(q)) {
                pill.style.display = '';
            } else {
                pill.style.display = 'none';
            }
        });

        // Filter quiz cards in real-time
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
            item.style.display = 'flex';
            item.style.justifyContent = 'space-between';
            item.style.alignItems = 'center';
            item.style.padding = '10px 14px';
            item.style.textDecoration = 'none';
            item.style.color = 'var(--text,#111318)';
            item.style.background = 'var(--surface,#ffffff)';
            item.style.borderBottom = '1px solid var(--border,#E4E6EA)';
            item.style.fontSize = '13.5px';

            item.innerHTML = `
                <div>📁 <strong>${escapeHtml(cat.name)}</strong></div>
                <span style="font-size:11px;color:var(--accent,#185FA5);background:var(--accent-light,#E6F1FB);padding:2px 8px;border-radius:4px;font-weight:600">Filter category &rarr;</span>
            `;

            item.addEventListener('mouseenter', () => {
                item.style.background = 'var(--accent-light,#E6F1FB)';
                item.style.color = 'var(--accent,#185FA5)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.background = 'var(--surface,#ffffff)';
                item.style.color = 'var(--text,#111318)';
            });

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