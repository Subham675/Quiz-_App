<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

startSession();
if (isLoggedIn()) { 
    header('Location: ' . BASE_PATH . '/index.php'); 
    exit; 
}

$db = getDB();
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = $success = '';
$tokenValid = false;
$user = null;

if (!empty($token)) {
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $tokenValid = true;
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
} else {
    $error = 'No reset token provided. Please request a new password reset link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    verifyCsrf();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (empty($password) || empty($confirm)) {
        $error = 'Please fill in both password fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $update = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $update->execute([$hash, $user['id']]);

        $success = 'Your password has been successfully reset! Redirecting to login...';
        header('refresh:2;url=' . BASE_PATH . '/public/login.php');
        $tokenValid = false; // Hide form after success
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — QuizApp</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=5">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon text-white">
            <i class="bi bi-shield-lock fs-4"></i>
        </div>
        <div class="auth-logo">Set new password</div>
        <div class="auth-subtitle">Create a strong password of at least 8 characters</div>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($tokenValid): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3">
                <label class="form-label small fw-semibold">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="newPassword" required minlength="8" placeholder="At least 8 characters" autofocus>
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('newPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="confirm" id="confirmPassword" required minlength="8" placeholder="Re-enter new password">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('confirmPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
        <?php elseif (!$success): ?>
            <div class="text-center mt-3">
                <a href="<?= BASE_PATH ?>/public/forgot-password.php" class="btn btn-outline-primary w-100">Request New Reset Link</a>
            </div>
        <?php endif; ?>

        <p class="auth-divider"><a href="<?= BASE_PATH ?>/public/login.php" class="text-decoration-none">Back to login</a></p>
    </div>
</div>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}
</script>
</body>
</html>