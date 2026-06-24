<?php
$pageTitle = 'Certificates';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT c.*, q.title AS quiz_title, a.score, a.total_marks
    FROM certificates c
    JOIN attempts a ON a.id = c.attempt_id
    JOIN quizzes q  ON q.id = a.quiz_id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
");
$stmt->execute([$userId]);
$certs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Certificates</div>
    <div class="page-subtitle">Earned by scoring 60% or higher on a quiz</div>
</div>

<?php if (empty($certs)): ?>
    <div class="card">
        <p style="color:var(--muted);font-size:13.5px">No certificates yet — pass a quiz with a score of 60% or higher to earn one. <a href="quiz-list.php">Browse quizzes</a></p>
    </div>
<?php else: ?>
    <div class="three-col">
        <?php foreach ($certs as $c):
            $pct = $c['total_marks'] > 0 ? round($c['score'] * 100 / $c['total_marks']) : 0;
        ?>
        <div class="card">
            <span class="badge badge-success">Passed · <?= $pct ?>%</span>
            <div class="card-title" style="margin-top:10px"><?= htmlspecialchars($c['quiz_title']) ?></div>
            <p style="font-size:12px;color:var(--muted);margin-bottom:14px">
                Issued <?= date('d M Y', strtotime($c['issued_at'])) ?><br>
                ID: <?= htmlspecialchars($c['unique_code']) ?>
            </p>
            <a href="/Quiz_app/<?= htmlspecialchars($c['cert_path']) ?>" target="_blank" class="btn btn-primary btn-sm" style="width:100%;justify-content:center">
                Download PDF
            </a>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>