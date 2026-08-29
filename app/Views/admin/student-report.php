<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Student Performance Report</h1>
            <p class="page-subtitle"><?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['email']) ?>)</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Users
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 text-center">
            <div class="stat-label text-muted small">Total Attempts</div>
            <div class="stat-value fs-2 fw-bold text-primary"><?= count($attempts) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 text-center">
            <div class="stat-label text-muted small">Certificates</div>
            <div class="stat-value fs-2 fw-bold text-warning"><?= $certCount ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3 shadow-sm border-0 text-center">
            <div class="stat-label text-muted small">Status</div>
            <div class="stat-value fs-5 fw-bold <?= empty($student['is_banned']) ? 'text-success' : 'text-danger' ?>">
                <?= empty($student['is_banned']) ? 'Active Account' : 'Suspended' ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Attempt History</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quiz Title</th>
                        <th>Category</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Date</th>
                        <th class="text-end">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): 
                        $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
                    ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($a['quiz_title']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['category_name'] ?? 'General') ?></span></td>
                        <td><?= number_format($a['score'], 2) ?> / <?= $a['total_marks'] ?></td>
                        <td><span class="badge <?= $pct >= 60 ? 'bg-success' : 'bg-danger' ?>"><?= $pct ?>%</span></td>
                        <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports/attempt/<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                View Breakdown
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
