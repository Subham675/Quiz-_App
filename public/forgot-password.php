<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

startSession();
if (isLoggedIn()) { header('Location: ' . BASE_PATH . '/index.php'); exit; }

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

                $resetLink = rtrim($_ENV['APP_URL'] ?? '', '/') . "/public/reset-password.php?token={$token}";
                $body = "Hi {$user['name']},\n\nClick the link below to reset your password (valid for 1 hour):\n\n{$resetLink}\n\nIf you didn't request this, ignore this email.";

                try {
                    $mailer = getMailer();
                    $mailer->addAddress($email, $user['name']);
                    $mailer->Subject = 'Reset your QuizApp password';
                    $mailer->Body    = $body;
                    $mailer->send();
                } catch (Exception $e) {
                    error_log('Reset email failed: ' . $e->getMessage());
                }
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
    <title>Forgot Password — QuizApp</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=3">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="auth-logo">Forgot password?</div>
        <div class="auth-subtitle">Enter your email and we'll send a reset link</div>

        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required placeholder="you@email.com" autofocus>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Send reset link</button>
        </form>
        <?php endif; ?>

        <p class="auth-divider"><a href="login.php">Back to login</a></p>
    </div>
</div>
</body>
</html>