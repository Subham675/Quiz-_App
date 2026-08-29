<?php
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db      = getDB();
$error   = '';
$success = '';

// ── Soft delete (mark as deleted, keep data) ────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    verifyCsrf();
    $targetId = (int)$_POST['delete_user'];

    $check = $db->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$targetId]);
    $targetRole = $check->fetchColumn();

    if ($targetId === (int)$_SESSION['user_id']) {
        $error = "You can't delete your own account.";
    } elseif ($targetRole === 'admin') {
        $error = "You can't delete another admin account.";
    } else {
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $db->beginTransaction();

            // Step 1: Delete the user
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);

            // Step 2: Get all users with id > deletedId, descending
            // (descending prevents duplicate key conflicts during shift)
            $toShift = $db->prepare("SELECT id FROM users WHERE id > ? ORDER BY id DESC");
            $toShift->execute([$targetId]);
            $shiftIds = $toShift->fetchAll(PDO::FETCH_COLUMN);

            // Step 3: Shift each user's ID down by 1
            // Update FK tables first, then users table
            $fkTables = [
                ['attempts',       'user_id'],
                ['certificates',   'user_id'],
                ['daily_sessions', 'user_id'],
            ];

            foreach ($shiftIds as $oldId) {
                $newId = $oldId - 1;

                // Update all foreign key references
                foreach ($fkTables as [$table, $col]) {
                    $db->prepare("UPDATE `$table` SET `$col` = ? WHERE `$col` = ?")
                       ->execute([$newId, $oldId]);
                }

                // Update the user row itself
                $db->prepare("UPDATE users SET id = ? WHERE id = ?")
                   ->execute([$newId, $oldId]);
            }

            // Step 4: Reset AUTO_INCREMENT to max(id) + 1
            $maxId = (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM users")->fetchColumn();
            $db->exec("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));

            $db->commit();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");

            $success = 'User deleted and all IDs renumbered sequentially.';

        } catch (Exception $e) {
            $db->rollBack();
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            error_log('Renumber error: ' . $e->getMessage());
            $error = 'Delete failed. Please try again.';
        }
    }
}

// ── Restore deleted user ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_user'])) {
    verifyCsrf();
    $targetId = (int)$_POST['restore_user'];
    $db->prepare("UPDATE users SET is_deleted = 0, is_banned = 0 WHERE id = ?")
       ->execute([$targetId]);
    $success = 'User restored successfully.';
}

// ── Toggle ban ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ban'])) {
    verifyCsrf();
    $targetId = (int)$_POST['toggle_ban'];

    $check = $db->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$targetId]);
    $targetRole = $check->fetchColumn();

    if ($targetId === (int)$_SESSION['user_id']) {
        $error = "You can't ban your own account.";
    } elseif ($targetRole === 'admin') {
        $error = "You can't ban another admin.";
    } else {
        $db->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE id = ? AND is_deleted = 0")
           ->execute([$targetId]);
        $success = 'User status updated.';
    }
}

// ── Search ──────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$showDeleted = isset($_GET['show_deleted']);

// Active users
$sql = "
    SELECT u.id, u.name, u.email, u.is_verified, u.is_banned, u.is_deleted, u.created_at,
           (SELECT COUNT(*) FROM attempts WHERE user_id = u.id AND is_completed = 1) AS attempts_count,
           (SELECT COUNT(*) FROM certificates WHERE user_id = u.id) AS certs_count
    FROM users u
    WHERE u.role = 'user' AND u.is_deleted = 0
";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Deleted users
$deleted = $db->query("
    SELECT id, name, email, created_at FROM users
    WHERE role = 'user' AND is_deleted = 1
    ORDER BY id DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Manage Users</h1>
    <p class="page-subtitle"><?= count($users) ?> active user<?= count($users) !== 1 ? 's' : '' ?><?= count($deleted) > 0 ? ' · ' . count($deleted) . ' removed' : '' ?></p>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="text" class="form-control form-control-sm" name="search" placeholder="Search by name or email..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Search</button>
            <?php if ($search): ?>
                <a href="manage-users.php" class="btn btn-outline-secondary btn-sm text-nowrap">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Active Users Table -->
<div class="card mb-4">
    <div class="card-body">
        <?php if (empty($users)): ?>
            <p class="text-muted small mb-0">No users found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Attempts</th>
                        <th>Certs</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td class="text-muted small fw-semibold"><?= $i + 1 ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['attempts_count'] ?></td>
                        <td><?= $u['certs_count'] ?></td>
                        <td>
                            <?php if ($u['is_banned']): ?>
                                <span class="badge rounded-pill bg-danger">Banned</span>
                            <?php elseif (!$u['is_verified']): ?>
                                <span class="badge rounded-pill bg-warning text-dark">Unverified</span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td class="text-nowrap">
                            <a href="student-report.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">Report</a>

                            <!-- Ban / Unban -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('<?= $u['is_banned'] ? 'Unban' : 'Ban' ?> this user?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="submit" name="toggle_ban" value="<?= $u['id'] ?>"
                                        class="btn btn-sm <?= $u['is_banned'] ? 'btn-outline-secondary' : 'btn-outline-danger' ?>">
                                    <?= $u['is_banned'] ? 'Unban' : 'Ban' ?>
                                </button>
                            </form>

                            <!-- Soft Delete -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Remove <?= htmlspecialchars($u['name'], ENT_QUOTES) ?>? Their data will be kept and they can be restored.')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="submit" name="delete_user" value="<?= $u['id'] ?>"
                                        class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Deleted / Removed Users -->
<?php if (!empty($deleted)): ?>
<div class="card">
    <div class="card-body">
        <h6 class="card-title text-muted fw-semibold mb-3">🗑 Removed Users (<?= count($deleted) ?>)</h6>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>Removed on</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($deleted as $i => $u): ?>
                    <tr class="opacity-75">
                        <td class="small text-muted"><?= $i + 1 ?></td>
                        <td class="text-decoration-line-through text-muted"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <!-- Restore -->
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Restore <?= htmlspecialchars($u['name'], ENT_QUOTES) ?>?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <button type="submit" name="restore_user" value="<?= $u['id'] ?>"
                                        class="btn btn-sm btn-primary">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>