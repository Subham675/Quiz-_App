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

<div class="mb-4">
    <h1 class="page-title">Certificates</h1>
    <p class="page-subtitle">Earned by scoring 60% or higher on a quiz</p>
</div>

<?php if (empty($certs)): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted small mb-0">No certificates yet — pass a quiz with a score of 60% or higher to earn one. <a href="quiz-list.php" class="text-decoration-none">Browse quizzes</a></p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($certs as $c):
            $pct = $c['total_marks'] > 0 ? round($c['score'] * 100 / $c['total_marks']) : 0;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div>
                        <span class="badge rounded-pill bg-success mb-2">Passed · <?= $pct ?>%</span>
                    </div>
                    <h6 class="card-title fw-semibold mt-1 mb-2"><?= htmlspecialchars($c['quiz_title']) ?></h6>
                    <p class="text-muted small mb-3">
                        Issued <?= date('d M Y', strtotime($c['issued_at'])) ?><br>
                        <span class="text-secondary">ID: <?= htmlspecialchars($c['unique_code']) ?></span>
                    </p>
                    <div class="mt-auto">
                        <a href="/Quiz_app/<?= htmlspecialchars($c['cert_path']) ?>" target="_blank" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-download me-1"></i> Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>