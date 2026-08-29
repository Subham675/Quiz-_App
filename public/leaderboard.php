<?php
$pageTitle = 'Leaderboard';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

// Filter by quiz
$quizId = (int)($_GET['quiz'] ?? 0);
$quizzes = $db->query("SELECT id, title FROM quizzes WHERE is_active = 1 ORDER BY title")->fetchAll();

$sql = "
    SELECT u.name, u.id AS user_id,
           COUNT(a.id) AS quizzes_taken,
           ROUND(AVG(a.score * 100 / NULLIF(a.total_marks, 0)), 1) AS avg_score,
           MAX(a.score * 100 / NULLIF(a.total_marks, 0)) AS best_score,
           SUM(CASE WHEN a.score * 100 / NULLIF(a.total_marks,0) >= 60 THEN 1 ELSE 0 END) AS passes,
           (SELECT COUNT(*) FROM certificates WHERE user_id = u.id) AS certs
    FROM users u
    JOIN attempts a ON a.user_id = u.id AND a.is_completed = 1
";
$params = [];
if ($quizId > 0) {
    $sql .= " WHERE a.quiz_id = ?";
    $params[] = $quizId;
}
$sql .= " GROUP BY u.id, u.name ORDER BY avg_score DESC, passes DESC LIMIT 50";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$leaders = $stmt->fetchAll();

// Find current user's rank
$myRank = null;
foreach ($leaders as $i => $l) {
    if ($l['user_id'] == $userId) { $myRank = $i + 1; break; }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Leaderboard</h1>
    <p class="page-subtitle">Top performers across all quizzes</p>
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="quiz" class="form-select form-select-sm">
                <option value="">All quizzes</option>
                <?php foreach ($quizzes as $q): ?>
                    <option value="<?= $q['id'] ?>" <?= $quizId === (int)$q['id'] ? 'selected' : '' ?>><?= htmlspecialchars($q['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Filter</button>
            <?php if ($quizId): ?><a href="leaderboard.php" class="btn btn-outline-secondary btn-sm text-nowrap">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<?php if ($myRank): ?>
<div class="alert alert-success d-flex align-items-center mb-3">
    <i class="bi bi-trophy-fill me-2 fs-5"></i>
    <div>Your rank: <strong>#<?= $myRank ?></strong> out of <?= count($leaders) ?> students</div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($leaders)): ?>
            <p class="text-muted small mb-0">No attempts yet. Be the first!</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Student</th><th>Avg Score</th><th>Best Score</th><th>Passes</th><th>Certs</th></tr>
                </thead>
                <tbody>
                <?php foreach ($leaders as $i => $l): 
                    $isMe = $l['user_id'] == $userId;
                    $medal = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '' };
                ?>
                <tr class="<?= $isMe ? 'table-primary fw-bold' : '' ?>">
                    <td><?= $medal ?: ($i + 1) ?></td>
                    <td>
                        <?= htmlspecialchars($l['name']) ?> 
                        <?php if ($isMe): ?>
                            <span class="badge bg-primary ms-1">You</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge rounded-pill <?= $l['avg_score'] >= 60 ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $l['avg_score'] ?>%</span></td>
                    <td><?= round($l['best_score']) ?>%</td>
                    <td><?= $l['passes'] ?></td>
                    <td><?= $l['certs'] ?> 🏆</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>