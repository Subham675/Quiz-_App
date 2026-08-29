<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — QuizApp</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=6">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon text-white">
            <i class="bi bi-key fs-4"></i>
        </div>
        <div class="auth-logo">Forgot password?</div>
        <div class="auth-subtitle">Enter your email and we'll send a password reset link</div>

        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/forgot-password">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email address</label>
                <input type="email" class="form-control" name="email" required placeholder="you@email.com" autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send reset link</button>
        </form>
        <?php endif; ?>

        <p class="auth-divider"><a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login" class="text-decoration-none">Back to login</a></p>
    </div>
</div>
</body>
</html>
