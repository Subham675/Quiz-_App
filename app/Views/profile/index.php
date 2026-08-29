<div class="mb-4">
    <h1 class="page-title">My Profile</h1>
    <p class="page-subtitle">Manage your account and view your achievements</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile Info & Edit -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Account Settings</h5>
                <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/profile">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small class="text-muted">Email address cannot be changed directly.</small>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">Change Password (Optional)</h6>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="At least 8 characters">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Student Stats -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-4">Learning Statistics</h5>
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-primary"><?= (int)($stats['total_attempts'] ?? 0) ?></div>
                            <div class="text-muted small">Total Quizzes Taken</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-success"><?= (int)($stats['best_score'] ?? 0) ?>%</div>
                            <div class="text-muted small">Highest Score</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-info"><?= (int)($stats['avg_score'] ?? 0) ?>%</div>
                            <div class="text-muted small">Average Score</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded">
                            <div class="fs-3 fw-bold text-warning"><?= $certCount ?></div>
                            <div class="text-muted small">Certificates Earned</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
