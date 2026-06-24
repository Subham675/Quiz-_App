<?php
$pageTitle = 'Student Report';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db         = getDB();
$studentId  = (int)($_GET['id'] ?? 0);

$userStmt = $db->prepare("SELECT id, name, email, created_at, is_banned FROM users WHERE id = ? AND role = 'user'");
$userStmt->execute([$studentId]);
$student = $userStmt->fetch();

if (!$student) {
    header('Location: manage-users.php');
    exit;
}

$statsStmt = $db->prepare("
    SELECT COUNT(*) AS total,
           ROUND(AVG(score*100/NULLIF(total_marks,0)),1) AS avg_score,
           MAX(score*100/NULLIF(total_marks,0)) AS best_score,
           SUM(CASE WHEN score*100/NULLIF(total_marks,0) >= 60 THEN 1 ELSE 0 END) AS passes
    FROM attempts WHERE user_id = ? AND is_completed = 1
");
$statsStmt->execute([$studentId]);
$stats = $statsStmt->fetch();

$certCount = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
$certCount->execute([$studentId]);
$certCount = $certCount->fetchColumn();

$attemptsStmt = $db->prepare("
    SELECT a.id, q.title, a.score, a.total_marks, a.time_taken_seconds, a.submitted_at
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id = ? AND a.is_completed = 1
    ORDER BY a.submitted_at DESC
");
$attemptsStmt->execute([$studentId]);
$attempts = $attemptsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title"><?= htmlspecialchars($student['name']) ?></div>
    <div class="page-subtitle">
        <a href="manage-users.php">&larr; Back to users</a> · <?= htmlspecialchars($student['email']) ?>
        · Joined <?= date('d M Y', strtotime($student['created_at'])) ?>
        <?php if ($student['is_banned']): ?>
            <span class="badge badge-danger">Banned</span>
        <?php endif; ?>
    </div>
</div>

<div class="stat-grid" style="margin-bottom:24px">
    <div class="card"><div class="stat-label">Quizzes taken</div><div class="stat-value"><?= (int)$stats['total'] ?></div></div>
    <div class="card"><div class="stat-label">Avg score</div><div class="stat-value"><?= $stats['avg_score'] ?? 0 ?>%</div></div>
    <div class="card"><div class="stat-label">Best score</div><div class="stat-value"><?= round($stats['best_score'] ?? 0) ?>%</div></div>
    <div class="card"><div class="stat-label">Passes</div><div class="stat-value"><?= (int)$stats['passes'] ?></div></div>
    <div class="card"><div class="stat-label">Certificates</div><div class="stat-value"><?= $certCount ?></div></div>
</div>

<div class="card">
    <div class="card-title">All attempts</div>
    <?php if (empty($attempts)): ?>
        <p style="color:var(--muted)">No attempts yet.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Quiz</th><th>Score</th><th>Time</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($attempts as $a):
            $pct = $a['total_marks'] > 0 ? round($a['score']*100/$a['total_marks']) : 0;
        ?>
        <tr>
            <td><?= htmlspecialchars($a['title']) ?></td>
            <td><span class="badge <?= $pct >= 60 ? 'badge-success' : 'badge-danger' ?>"><?= $pct ?>%</span></td>
            <td style="color:var(--muted)"><?= gmdate('i:s', $a['time_taken_seconds']) ?></td>
            <td style="color:var(--muted);font-size:12px"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td>
            <td><a href="attempt-detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>