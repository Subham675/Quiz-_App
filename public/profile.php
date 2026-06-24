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

<div class="page-header">
    <div class="page-title">Profile</div>
    <div class="page-subtitle">Manage your account details</div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="stat-grid" style="margin-bottom:24px">
    <div class="card">
        <div class="stat-label">Quizzes taken</div>
        <div class="stat-value"><?= (int)$stats['quizzes_taken'] ?></div>
    </div>
    <div class="card">
        <div class="stat-label">Best score</div>
        <div class="stat-value"><?= round($stats['best_score']) ?>%</div>
    </div>
    <div class="card">
        <div class="stat-label">Avg score</div>
        <div class="stat-value"><?= round($stats['avg_score']) ?>%</div>
    </div>
    <div class="card">
        <div class="stat-label">Certificates</div>
        <div class="stat-value"><?= (int)$certCount ?></div>
    </div>
</div>

<div class="two-col" style="grid-template-columns: 1fr 1fr">
    <div class="card">
        <div class="card-title">Account details</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label>Full name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
            </div>

            <div class="form-group">
                <label>Role</label>
                <input type="text" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled style="background:var(--bg);color:var(--muted)">
            </div>

            <div class="form-group">
                <label>Member since</label>
                <input type="text" value="<?= date('d M Y', strtotime($user['created_at'])) ?>" disabled style="background:var(--bg);color:var(--muted)">
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary" style="width:100%;justify-content:center">
                Save changes
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">Change password</div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group">
                <label>Current password</label>
                <div class="password-field">
                    <input type="password" name="current_password" id="cur_pw" required>
                    <button type="button" class="password-toggle" data-target="cur_pw" aria-label="Show password">
                        <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><path d="M1 1l22 22"></path></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>New password</label>
                <div class="password-field">
                    <input type="password" name="new_password" id="new_pw" required minlength="8">
                    <button type="button" class="password-toggle" data-target="new_pw" aria-label="Show password">
                        <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><path d="M1 1l22 22"></path></svg>
                    </button>
                </div>
                <div class="form-hint">At least 8 characters</div>
            </div>

            <div class="form-group">
                <label>Confirm new password</label>
                <div class="password-field">
                    <input type="password" name="confirm_password" id="conf_pw" required minlength="8">
                    <button type="button" class="password-toggle" data-target="conf_pw" aria-label="Show password">
                        <svg class="icon-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg class="icon-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.6 18.6 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.6 18.6 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><path d="M1 1l22 22"></path></svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="change_password" class="btn btn-primary" style="width:100%;justify-content:center">
                Update password
            </button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.classList.toggle('is-visible', !showing);
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>