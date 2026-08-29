<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

startSession();
if (isLoggedIn()) { header('Location: /Quiz_app/index.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $rl = new RateLimiter(getDB());
    if ($rl->isBlocked('forgot')) {
        $error = 'Too many requests. Please wait ' . ceil($rl->blockedSecondsRemaining('forgot') / 60) . ' minute(s).';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? AND is_verified = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?")
                   ->execute([$token, $expires, $user['id']]);

                $resetLink = "http://localhost/Quiz_app/public/reset-password.php?token={$token}";
                $body = "Hi {$user['name']},\n\nClick the link below to reset your password (valid for 1 hour):\n\n{$resetLink}\n\nIf you didn't request this, ignore this email.";

                sendMail($email, $user['name'], 'Reset your QuizApp password', $body);
            }

            // Always show success (don't reveal if email exists)
            $rl->recordFailure('forgot', 5, 10, 15);
            $success = 'If that email is registered, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — QuizApp</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/Quiz_app/assets/css/style.css?v=5">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon text-white">
            <i class="bi bi-shield-lock fs-4"></i>
        </div>
        <div class="auth-logo">Reset password</div>
        <div class="auth-subtitle">Enter your email and we'll send a reset link</div>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email address</label>
                <input type="email" class="form-control" name="email" required placeholder="you@email.com" autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send reset link</button>
        </form>
        <?php endif; ?>

        <p class="auth-divider"><a href="login.php" class="text-decoration-none">Back to login</a></p>
    </div>
</div>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>