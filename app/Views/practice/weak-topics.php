<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-title">Weak Topics Analysis</h1>
            <p class="page-subtitle">Tags and topics where your accuracy is under 70%</p>
        </div>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Practice
        </a>
    </div>
</div>

<?php if (empty($weakTopics)): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <i class="bi bi-patch-check text-success display-4 mb-3"></i>
        <h5 class="fw-bold">No weak topics detected! 🎉</h5>
        <p class="text-muted">You are performing with high accuracy across all attempted topics. Keep up the great work!</p>
        <div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="btn btn-primary">Take More Quizzes</a>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Topic / Tag</th>
                        <th>Category</th>
                        <th>Attempts</th>
                        <th>Correct</th>
                        <th>Accuracy</th>
                        <th class="text-end">Recommended Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weakTopics as $t): ?>
                    <tr>
                        <td class="fw-bold"><i class="bi bi-tag me-1 text-primary"></i><?= htmlspecialchars($t['tag']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['category_name'] ?? 'General') ?></span></td>
                        <td><?= (int)$t['times_attempted'] ?></td>
                        <td class="text-success"><?= (int)$t['times_correct'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 6px;">
                                    <div class="progress-bar bg-danger" style="width: <?= (int)$t['accuracy'] ?>%;"></div>
                                </div>
                                <span class="badge bg-danger"><?= (int)$t['accuracy'] ?>%</span>
                            </div>
                        </td>
                        <td class="text-end">
                            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/ai-practice?topic=<?= urlencode($t['tag']) ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-robot me-1"></i>Practice AI
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
