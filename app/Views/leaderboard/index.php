<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Leaderboard</h1>
        <p class="page-subtitle">Top performers and student rankings</p>
    </div>
    <div class="btn-group">
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/leaderboard?timeframe=all" class="btn btn-sm <?= ($timeframe ?? '') === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">All Time</a>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/leaderboard?timeframe=month" class="btn btn-sm <?= ($timeframe ?? '') === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Month</a>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/leaderboard?timeframe=week" class="btn btn-sm <?= ($timeframe ?? '') === 'week' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Week</a>
    </div>
</div>

<?php if (empty($leaders)): ?>
    <div class="card text-center p-5 shadow-sm border-0">
        <i class="bi bi-trophy text-muted display-4 mb-3"></i>
        <h5 class="fw-bold">No ranking data yet</h5>
        <p class="text-muted">Take quizzes to be the first on the leaderboard!</p>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">Rank</th>
                        <th>Student</th>
                        <th>Quizzes Taken</th>
                        <th>Total Score</th>
                        <th>Accuracy</th>
                        <th>Certificates</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leaders as $rank => $u): ?>
                    <tr class="<?= $u['id'] == $_SESSION['user_id'] ? 'table-primary' : '' ?>">
                        <td>
                            <?php if ($rank === 0): ?>
                                <span class="badge bg-warning text-dark fs-6">🥇 1</span>
                            <?php elseif ($rank === 1): ?>
                                <span class="badge bg-secondary text-white fs-6">🥈 2</span>
                            <?php elseif ($rank === 2): ?>
                                <span class="badge bg-danger text-white fs-6">🥉 3</span>
                            <?php else: ?>
                                <span class="fw-bold text-muted ms-2">#<?= $rank + 1 ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($u['name']) ?> <?= $u['id'] == $_SESSION['user_id'] ? '<span class="badge bg-primary ms-1">You</span>' : '' ?></div>
                        </td>
                        <td><?= (int)$u['total_quizzes'] ?></td>
                        <td class="fw-bold text-primary"><?= number_format($u['total_score'], 1) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= (int)$u['avg_pct'] ?>%</span></td>
                        <td><span class="badge bg-warning-subtle text-warning border border-warning-subtle">🏆 <?= (int)$u['certs'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
