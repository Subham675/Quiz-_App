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

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $baseUrl = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : "{$scheme}://{$host}" . BASE_PATH;
                $resetLink = $baseUrl . '/public/reset-password.php?token=' . urlencode($token);

                $htmlBody = "
                    <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px'>
                        <h2 style='color:#111827;margin-top:0'>Reset your password</h2>
                        <p style='color:#4b5563;font-size:15px'>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                        <p style='color:#4b5563;font-size:14px'>We received a request to reset your QuizApp password. Click the button below to choose a new password (valid for 1 hour):</p>
                        <div style='text-align:center;margin:28px 0'>
                            <a href='{$resetLink}' style='background:#185FA5;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:6px;font-weight:600;display:inline-block'>Reset Password</a>
                        </div>
                        <p style='color:#6b7280;font-size:13px'>Or copy and paste this link in your browser:<br><a href='{$resetLink}' style='color:#185FA5;word-break:break-all'>{$resetLink}</a></p>
                        <hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0'>
                        <p style='color:#9ca3af;font-size:12px;margin-bottom:0'>If you did not request a password reset, you can safely ignore this email.</p>
                    </div>
                ";

                try {
                    $mailer = getMailer();
                    $mailer->addAddress($email, $user['name']);
                    $mailer->Subject = 'Reset your QuizApp password';
                    $mailer->Body    = $htmlBody;
                    $mailer->AltBody = "Hi {$user['name']},\n\nClick the link below to reset your password (valid for 1 hour):\n\n{$resetLink}\n\nIf you didn't request this, ignore this email.";
                    $mailer->send();
                } catch (Exception $e) {
                    error_log('Reset email failed: ' . $e->getMessage());
                }
            }

            // Always show success (don't reveal if email exists)
            $rl->recordFailure('forgot', 5, 10, 15);
            $success = 'If that email is registered, a password reset link has been sent to your inbox.';
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
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css?v=5">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon text-white">
            <i class="bi bi-key fs-4"></i>
        </div>
        <div class="auth-logo">Forgot password?</div>
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

        <p class="auth-divider"><a href="<?= BASE_PATH ?>/public/login.php" class="text-decoration-none">Back to login</a></p>
    </div>
</div>
<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>