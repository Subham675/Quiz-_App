<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../config/db.php';

$db     = getDB();
$userId = $_SESSION['user_id'];
$error  = '';
$success = '';

$userStmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

// ── Update name/email ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verifyCsrf();

    $name  = ucwords(strtolower(trim($_POST['name'] ?? '')));
    $email = trim($_POST['email'] ?? '');

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid name and email.';
    } else {
        $dupe = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $dupe->execute([$email, $userId]);
        if ($dupe->fetch()) {
            $error = 'That email is already in use by another account.';
        } else {
            $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")->execute([$name, $email, $userId]);
            $_SESSION['name']  = $name;
            $_SESSION['email'] = $email;
            $success = 'Profile updated.';
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
        }
    }
}

// ── Change password ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $pwStmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $pwStmt->execute([$userId]);
    $hash = $pwStmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
        $success = 'Password changed successfully.';
    }
}

// ── Stats ───────────────────────────────────────────────
$statsStmt = $db->prepare("
    SELECT
        COUNT(*) AS quizzes_taken,
        COALESCE(MAX(score * 100 / NULLIF(total_marks,0)), 0) AS best_score,
        COALESCE(AVG(score * 100 / NULLIF(total_marks,0)), 0) AS avg_score
    FROM attempts WHERE user_id = ? AND is_completed = 1
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();

$certCount = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
$certCount->execute([$userId]);
$certCount = $certCount->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-4">
    <h1 class="page-title">Profile</h1>
    <p class="page-subtitle">Manage your account details</p>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Quizzes taken</div>
                <div class="stat-value"><?= (int)$stats['quizzes_taken'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Best score</div>
                <div class="stat-value"><?= round($stats['best_score']) ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Avg score</div>
                <div class="stat-value"><?= round($stats['avg_score']) ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="stat-label">Certificates</div>
                <div class="stat-value"><?= (int)$certCount ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3">Account details</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Full name</label>
                        <input type="text" class="form-control" name="name" required value="<?= htmlspecialchars($user['name']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Email</label>
                        <input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Role</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Member since</label>
                        <input type="text" class="form-control bg-light" value="<?= date('d M Y', strtotime($user['created_at'])) ?>" disabled>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary w-100 mt-2">
                        Save changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title fw-semibold mb-3">Change password</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Current password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="current_password" id="cur_pw" required>
                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="cur_pw">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">New password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="new_pw" required minlength="8">
                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="new_pw">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text small">At least 8 characters</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Confirm new password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="confirm_password" id="conf_pw" required minlength="8">
                            <button class="btn btn-outline-secondary password-toggle" type="button" data-target="conf_pw">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="change_password" class="btn btn-primary w-100 mt-2">
                        Update password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const icon = btn.querySelector('i');
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        if (icon) {
            icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>