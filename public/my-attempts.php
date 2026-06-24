<?php
$pageTitle = 'My Attempts';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT a.id, q.title, a.score, a.total_marks, a.time_taken_seconds, a.submitted_at
    FROM attempts a
    JOIN quizzes q ON q.id = a.quiz_id
    WHERE a.user_id = ? AND a.is_completed = 1
    ORDER BY a.submitted_at DESC
");
$stmt->execute([$userId]);
$attempts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">My Attempts</div>
    <div class="page-subtitle">All the quizzes you've completed</div>
</div>

<div class="card">
    <?php if (empty($attempts)): ?>
        <p style="color:var(--muted);font-size:13.5px">You haven't attempted any quizzes yet. <a href="quiz-list.php">Browse quizzes</a></p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead>
                <tr><th>Quiz</th><th>Score</th><th>Time taken</th><th>Date</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($attempts as $a):
                $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
            ?>
                <tr>
                    <td><?= htmlspecialchars($a['title']) ?></td>
                    <td>
                        <span class="badge <?= $pct >= 60 ? 'badge-success' : 'badge-danger' ?>">
                            <?= $pct ?>% (<?= $a['score'] ?>/<?= $a['total_marks'] ?>)
                        </span>
                    </td>
                    <td style="color:var(--muted)"><?= gmdate('i:s', $a['time_taken_seconds']) ?></td>
                    <td style="color:var(--muted);font-size:12px"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td>
                    <td>
                        <a href="result.php?attempt=<?= $a['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        <a href="download-result.php?attempt=<?= $a['id'] ?>" class="btn btn-sm btn-outline">PDF</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>