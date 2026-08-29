<div class="mb-4">
    <h1 class="page-title">Manage Users</h1>
    <p class="page-subtitle">View and moderate registered students</p>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Verified</th>
                    <th>Attempts</th>
                    <th>Certificates</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= $u['id'] ?></td>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($u['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                    </td>
                    <td><span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <span class="badge <?= $u['is_verified'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $u['is_verified'] ? 'Verified' : 'Unverified' ?>
                        </span>
                    </td>
                    <td><?= (int)($u['attempts_count'] ?? 0) ?></td>
                    <td><?= (int)($u['certs_count'] ?? 0) ?></td>
                    <td>
                        <?php if (!empty($u['is_banned'])): ?>
                            <span class="badge bg-danger">Suspended</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users/report/<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                            <i class="bi bi-file-earmark-person"></i> Report
                        </a>
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users/ban/<?= $u['id'] ?>" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit" class="btn btn-sm <?= !empty($u['is_banned']) ? 'btn-outline-success' : 'btn-outline-warning' ?>">
                                <?= !empty($u['is_banned']) ? 'Unban' : 'Ban' ?>
                            </button>
                        </form>
                        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users/delete/<?= $u['id'] ?>" class="d-inline" onsubmit="return confirm('Delete user?');">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
