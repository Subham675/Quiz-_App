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
    SELECT q.id, q.title, q.description, q.time_limit_seconds, c.name AS category,
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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Browse Quizzes</div>
    <div class="page-subtitle">Pick a quiz and test your knowledge</div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <a href="quiz-list.php" class="btn btn-sm <?= $catId === 0 ? 'btn-primary' : 'btn-outline' ?>">All</a>
    <?php foreach ($categories as $c): ?>
        <a href="quiz-list.php?category=<?= $c['id'] ?>"
           class="btn btn-sm <?= $catId === (int)$c['id'] ? 'btn-primary' : 'btn-outline' ?>">
            <?= htmlspecialchars($c['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($quizzes)): ?>
    <div class="card">
        <p style="color:var(--muted);font-size:13.5px">No quizzes found in this category yet.</p>
    </div>
<?php else: ?>
    <div class="three-col">
        <?php foreach ($quizzes as $q): ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                <span class="badge badge-info"><?= htmlspecialchars($q['category']) ?></span>
                <?php if ($q['attempt_id']): ?>
                    <span class="badge badge-success">Completed</span>
                <?php endif; ?>
            </div>
            <div class="card-title" style="margin-bottom:6px"><?= htmlspecialchars($q['title']) ?></div>
            <p style="font-size:13px;color:var(--muted);margin-bottom:14px;min-height:36px">
                <?= htmlspecialchars($q['description'] ?? '') ?>
            </p>
            <div style="font-size:12px;color:var(--muted);margin-bottom:14px">
                <?= $q['q_count'] ?> questions · <?= round($q['time_limit_seconds'] / 60) ?> min
            </div>
            <?php if ($q['attempt_id']): ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>