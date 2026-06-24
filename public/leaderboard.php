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

<div class="page-header">
    <div class="page-title">Leaderboard</div>
    <div class="page-subtitle">Top performers across all quizzes</div>
</div>

<div class="card" style="margin-bottom:16px">
    <form method="GET" style="display:flex;gap:10px;align-items:center">
        <select name="quiz" style="flex:1">
            <option value="">All quizzes</option>
            <?php foreach ($quizzes as $q): ?>
                <option value="<?= $q['id'] ?>" <?= $quizId === (int)$q['id'] ? 'selected' : '' ?>><?= htmlspecialchars($q['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if ($quizId): ?><a href="leaderboard.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($myRank): ?>
<div class="alert alert-success" style="margin-bottom:16px">
    Your rank: <strong>#<?= $myRank ?></strong> out of <?= count($leaders) ?> students
</div>
<?php endif; ?>

<div class="card">
    <?php if (empty($leaders)): ?>
        <p style="color:var(--muted)">No attempts yet. Be the first!</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>#</th><th>Student</th><th>Avg Score</th><th>Best Score</th><th>Passes</th><th>Certs</th></tr>
        </thead>
        <tbody>
        <?php foreach ($leaders as $i => $l): 
            $isMe = $l['user_id'] == $userId;
            $medal = match($i) { 0 => '🥇', 1 => '🥈', 2 => '🥉', default => '' };
        ?>
        <tr style="<?= $isMe ? 'background:var(--accent-light, rgba(24,95,165,.06));font-weight:600' : '' ?>">
            <td><?= $medal ?: ($i + 1) ?></td>
            <td><?= htmlspecialchars($l['name']) ?> <?= $isMe ? '<span class="badge badge-info" style="font-size:10px">You</span>' : '' ?></td>
            <td><span class="badge <?= $l['avg_score'] >= 60 ? 'badge-success' : 'badge-warning' ?>"><?= $l['avg_score'] ?>%</span></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>