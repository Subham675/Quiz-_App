<div class="mb-4">
    <h1 class="page-title">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Student') ?>!</h1>
    <p class="page-subtitle">Here's your learning progress at a glance</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Quizzes Taken</div>
                <div class="stat-value fs-2 fw-bold text-primary"><?= $totalAttempts ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Best Score</div>
                <div class="stat-value fs-2 fw-bold text-success"><?= $bestScore ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Average Score</div>
                <div class="stat-value fs-2 fw-bold text-info"><?= $avgScore ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="stat-label text-muted small fw-semibold">Certificates</div>
                <div class="stat-value fs-2 fw-bold text-warning"><?= $totalCerts ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title fw-bold mb-0">Recommended Quizzes</h6>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="small text-decoration-none">View all</a>
                </div>
                <?php if (empty($recommended)): ?>
                    <p class="text-muted small mb-0">You've completed all available quizzes or none are active right now.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recommended as $q): ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($q['title']) ?></div>
                                <div class="text-muted" style="font-size:12px;">
                                    <?= (int)$q['question_count'] ?> questions · <?= (int)$q['time_limit_minutes'] ?> min
                                    <?= !empty($q['category_name']) ? ' · ' . htmlspecialchars($q['category_name']) : '' ?>
                                </div>
                            </div>
                            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/take/<?= $q['id'] ?>" class="btn btn-sm btn-outline-primary">Start</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="card-title fw-bold mb-0">Recent Attempts</h6>
                    <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/my-attempts" class="small text-decoration-none">History</a>
                </div>
                <?php if (empty($recentAttempts)): ?>
                    <p class="text-muted small mb-0">No quiz attempts yet. Start one from the list!</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentAttempts as $a): 
                            $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
                        ?>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small"><?= htmlspecialchars($a['quiz_title']) ?></div>
                                <div class="text-muted" style="font-size:12px;">
                                    <?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge <?= $pct >= 60 ? 'bg-success' : 'bg-danger' ?>"><?= $pct ?>%</span>
                                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/result/<?= $a['id'] ?>" class="btn btn-sm btn-link p-0 ms-2 text-decoration-none">View</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
