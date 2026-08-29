<div class="mb-4">
    <h1 class="page-title">Admin Dashboard</h1>
    <p class="page-subtitle">Overview of platform metrics and performance</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Total Students</div>
                <div class="stat-value fs-2 fw-bold text-primary"><?= $totalUsers ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Active Quizzes</div>
                <div class="stat-value fs-2 fw-bold text-success"><?= $totalQuizzes ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Total Attempts</div>
                <div class="stat-value fs-2 fw-bold text-info"><?= $totalAttempts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Certs This Month</div>
                <div class="stat-value fs-2 fw-bold text-warning"><?= $totalCerts ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Attempts -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0">Recent Quiz Attempts</h5>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports" class="small text-decoration-none">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Quiz</th>
                                <th>Score</th>
                                <th>Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentAttempts as $a): 
                                $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($a['user_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($a['user_email']) ?></small>
                                </td>
                                <td class="fw-semibold"><?= htmlspecialchars($a['quiz_title']) ?></td>
                                <td>
                                    <span class="badge <?= $pct >= 60 ? 'bg-success' : 'bg-danger' ?>"><?= $pct ?>%</span>
                                </td>
                                <td class="text-muted small"><?= date('d M Y', strtotime($a['submitted_at'])) ?></td>
                                <td class="text-end">
                                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports/attempt/<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        Details
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Tools -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/quizzes" class="btn btn-outline-primary text-start">
                        <i class="bi bi-plus-circle me-2"></i>Create New Quiz
                    </a>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions" class="btn btn-outline-primary text-start">
                        <i class="bi bi-patch-question me-2"></i>Add Questions
                    </a>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator" class="btn btn-outline-primary text-start">
                        <i class="bi bi-stars me-2"></i>AI Quiz Generator
                    </a>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/categories" class="btn btn-outline-primary text-start">
                        <i class="bi bi-tags me-2"></i>Manage Categories
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
