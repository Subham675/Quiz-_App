<?php
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$error = '';
$success = '';

// ── Toggle ban ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_ban'])) {
    verifyCsrf();
    $targetId = (int)$_POST['toggle_ban'];

    // Don't allow banning yourself or another admin by accident
    $check = $db->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$targetId]);
    $targetRole = $check->fetchColumn();

    if ($targetId === (int)$_SESSION['user_id']) {
        $success = "You can't ban your own account.";
    } elseif ($targetRole === 'admin') {
        $success = "You can't ban another admin.";
    } else {
        $db->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE id = ?")->execute([$targetId]);
        $success = 'User status updated.';
    }
}

// ── Delete user ─────────────────────────────────────────
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
        // CASCADE deletes handle attempts, answers, certificates automatically
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
        $success = 'User deleted permanently.';
    }
}

// ── Search ──────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT u.id, u.name, u.email, u.is_verified, u.is_banned, u.created_at,
           (SELECT COUNT(*) FROM attempts WHERE user_id = u.id AND is_completed = 1) AS attempts_count,
           (SELECT COUNT(*) FROM certificates WHERE user_id = u.id) AS certs_count
    FROM users u
    WHERE u.role = 'user'
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

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="page-title">Manage Users</div>
    <div class="page-subtitle"><?= count($users) ?> student<?= count($users) !== 1 ? 's' : '' ?> registered</div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <form method="GET" style="display:flex;gap:8px">
        <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" style="flex:1">
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if ($search): ?><a href="manage-users.php" class="btn btn-outline btn-sm">Clear</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <?php if (empty($users)): ?>
        <p style="color:var(--muted);font-size:13.5px">No users found.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead>
            <tr><th>Name</th><th>Email</th><th>Attempts</th><th>Certs</th><th>Status</th><th>Joined</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['attempts_count'] ?></td>
                <td><?= $u['certs_count'] ?></td>
                <td>
                    <?php if ($u['is_banned']): ?>
                        <span class="badge badge-danger">Banned</span>
                    <?php elseif (!$u['is_verified']): ?>
                        <span class="badge badge-warning">Unverified</span>
                    <?php else: ?>
                        <span class="badge badge-success">Active</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                <td style="white-space:nowrap">
                    <a href="student-report.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Report</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= $u['is_banned'] ? 'Unban' : 'Ban' ?> this user?');">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="submit" name="toggle_ban" value="<?= $u['id'] ?>"
                                class="btn btn-sm <?= $u['is_banned'] ? 'btn-outline' : 'btn-danger' ?>">
                            <?= $u['is_banned'] ? 'Unban' : 'Ban' ?>
                        </button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Permanently delete <?= htmlspecialchars($u['name'], ENT_QUOTES) ?>? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <button type="submit" name="delete_user" value="<?= $u['id'] ?>" class="btn btn-sm btn-danger">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>