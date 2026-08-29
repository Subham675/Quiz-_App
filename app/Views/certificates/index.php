<div class="mb-4">
    <h1 class="page-title">My Certificates</h1>
    <p class="page-subtitle">Certificates earned by scoring 60% or higher on assessments</p>
</div>

<?php if (empty($certs)): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <i class="bi bi-award text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No certificates earned yet</h5>
        <p class="text-muted">Score 60% or higher on any quiz to earn your official completion certificate.</p>
        <div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-primary">Take a Quiz</a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($certs as $c): 
            $pct = $c['total_marks'] > 0 ? round($c['score'] * 100 / $c['total_marks']) : 0;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-6"><i class="bi bi-award me-1"></i>Verified</span>
                        <span class="text-muted small">Score: <strong><?= $pct ?>%</strong></span>
                    </div>
                    <h5 class="fw-bold mb-2"><?= htmlspecialchars($c['quiz_title']) ?></h5>
                    <div class="text-muted small mb-3">Issued: <?= date('d M Y', strtotime($c['issued_at'])) ?></div>
                    <div class="bg-light p-2 rounded text-muted small font-monospace mb-4">
                        ID: <?= htmlspecialchars($c['unique_code']) ?>
                    </div>
                    <div class="mt-auto">
                        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/<?= htmlspecialchars($c['cert_path']) ?>" class="btn btn-primary w-100" download>
                            <i class="bi bi-download me-1"></i>Download PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
