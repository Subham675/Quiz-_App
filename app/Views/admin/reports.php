<div class="mb-4">
    <h1 class="page-title">Reports & Analytics</h1>
    <p class="page-subtitle">Performance breakdown and examination analytics</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Total Exam Attempts</div>
                <div class="stat-value fs-2 fw-bold text-primary"><?= $totalAttempts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Average Score</div>
                <div class="stat-value fs-2 fw-bold text-info"><?= round($avgScore) ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Pass Rate (≥60%)</div>
                <div class="stat-value fs-2 fw-bold text-success"><?= round($passRate) ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Total Certificates</div>
                <div class="stat-value fs-2 fw-bold text-warning"><?= $totalCerts ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Quiz Performance Breakdown</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quiz</th>
                        <th>Category</th>
                        <th>Attempts</th>
                        <th>Avg Score</th>
                        <th>Pass Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quizBreakdown as $qb): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($qb['title']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($qb['category_name'] ?? 'General') ?></span></td>
                        <td><?= (int)$qb['total_attempts'] ?></td>
                        <td><?= (int)$qb['avg_score'] ?>%</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar <?= (int)$qb['pass_rate'] >= 60 ? 'bg-success' : 'bg-warning' ?>" style="width: <?= (int)$qb['pass_rate'] ?>%;"></div>
                                </div>
                                <span><?= (int)$qb['pass_rate'] ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
