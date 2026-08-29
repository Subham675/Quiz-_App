<div class="mb-4">
    <h1 class="page-title">My Attempts</h1>
    <p class="page-subtitle">Your complete quiz attempt history</p>
</div>

<?php if (empty($attempts)): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <i class="bi bi-clock-history text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No attempts yet</h5>
        <p class="text-muted">You haven't completed any quizzes yet.</p>
        <div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-primary">Browse Quizzes</a>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quiz Title</th>
                        <th>Category</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Date Taken</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): 
                        $pct = $a['total_marks'] > 0 ? round($a['score'] * 100 / $a['total_marks']) : 0;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($a['quiz_title']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['category_name'] ?? 'General') ?></span></td>
                        <td><?= number_format($a['score'], 2) ?> / <?= $a['total_marks'] ?></td>
                        <td>
                            <span class="badge <?= $pct >= 60 ? 'bg-success' : 'bg-danger' ?>"><?= $pct ?>%</span>
                        </td>
                        <td class="text-muted small"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quiz/result/<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">
                                View Result
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
