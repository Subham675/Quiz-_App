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

<div class="mb-4">
    <h1 class="page-title">My Attempts</h1>
    <p class="page-subtitle">All the quizzes you've completed</p>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($attempts)): ?>
            <p class="text-muted small mb-0">You haven't attempted any quizzes yet. <a href="quiz-list.php" class="text-decoration-none">Browse quizzes</a></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Quiz</th>
                            <th>Score</th>
                            <th>Time taken</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attempts as $a):
                        $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
                    ?>
                        <tr>
                            <td class="fw-medium"><?= htmlspecialchars($a['title']) ?></td>
                            <td>
                                <span class="badge rounded-pill <?= $pct >= 60 ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $pct ?>% (<?= $a['score'] ?>/<?= $a['total_marks'] ?>)
                                </span>
                            </td>
                            <td class="text-muted small"><?= gmdate('i:s', $a['time_taken_seconds']) ?></td>
                            <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td>
                            <td class="text-nowrap">
                                <a href="result.php?attempt=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="download-result.php?attempt=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">PDF</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>