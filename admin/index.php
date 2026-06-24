<?php
$pageTitle = 'Dashboard — Admin';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$totalUsers    = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalQuizzes  = $db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalAttempts = $db->query("SELECT COUNT(*) FROM attempts WHERE DATE(started_at)=CURDATE()")->fetchColumn();
$totalCerts    = $db->query("SELECT COUNT(*) FROM certificates WHERE MONTH(issued_at)=MONTH(NOW())")->fetchColumn();

$recentAttempts = $db->query("
    SELECT a.id, u.name AS user_name, q.title AS quiz_title,
           ROUND(a.score * 100 / NULLIF(a.total_marks,0)) AS pct,
           a.submitted_at
    FROM attempts a
    JOIN users u  ON u.id  = a.user_id
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.is_completed = 1
    ORDER BY a.submitted_at DESC
    LIMIT 8
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Dashboard</div>
    <div class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?></div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Total users</div>
        <div class="stat-value"><?= $totalUsers ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total quizzes</div>
        <div class="stat-value"><?= $totalQuizzes ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Attempts today</div>
        <div class="stat-value"><?= $totalAttempts ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Certs this month</div>
        <div class="stat-value"><?= $totalCerts ?></div>
    </div>
</div>

<div class="card">
    <div class="card-title">Recent attempts</div>
    <table class="table">
        <thead>
            <tr>
                <th>User</th>
                <th>Quiz</th>
                <th>Score</th>
                <th>Result</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentAttempts as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['user_name']) ?></td>
                <td><?= htmlspecialchars($a['quiz_title']) ?></td>
                <td><?= $a['pct'] ?>%</td>
                <td>
                    <?php if ($a['pct'] >= 60): ?>
                        <span class="badge badge-success">Passed</span>
                    <?php elseif ($a['pct'] >= 40): ?>
                        <span class="badge badge-warning">Borderline</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Failed</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
                <td><a href="attempt-detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
