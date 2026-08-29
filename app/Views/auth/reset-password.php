<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — QuizApp</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=6">
    <style>
        .strength-meter { height: 4px; background-color: #e9ecef; border-radius: 2px; overflow: hidden; margin-top: 6px; }
        .strength-meter-bar { height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease; }
        .pass-hint { font-size: 12px; }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon text-white">
            <i class="bi bi-shield-lock fs-4"></i>
        </div>
        <div class="auth-logo">Set new password</div>
        <div class="auth-subtitle">Create a strong password for your account</div>

        <div class="alert alert-primary d-flex align-items-start py-2 px-3 mb-3 small" role="alert">
            <i class="bi bi-shield-check fs-5 me-2 flex-shrink-0 mt-1"></i>
            <div>
                <strong>Use a Strong Password</strong>
                <div class="text-secondary" style="font-size: 11.5px; line-height: 1.4;">
                    Must be at least 8 characters. Combine uppercase & lowercase letters, numbers, and special symbols.
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <?php if ($tokenValid): ?>
        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/reset-password">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="mb-3">
                <label class="form-label small fw-semibold">New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="newPassword" required minlength="8" placeholder="At least 8 characters" autofocus oninput="checkStrength()">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('newPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="strength-meter">
                    <div class="strength-meter-bar" id="strengthBar"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span id="strengthText" class="pass-hint text-muted">Password strength</span>
                    <span id="charCount" class="pass-hint text-muted">0/8 chars</span>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="confirm" id="confirmPassword" required minlength="8" placeholder="Re-enter new password" oninput="checkMatch()">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePass('confirmPassword', this)">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="matchText" class="pass-hint text-muted mt-1"></div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
        <?php elseif (empty($success)): ?>
            <div class="text-center mt-3">
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/forgot-password" class="btn btn-outline-primary w-100">Request New Reset Link</a>
            </div>
        <?php endif; ?>

        <p class="auth-divider"><a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login" class="text-decoration-none">Back to login</a></p>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const el = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (el.type === 'password') {
        el.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        el.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

function checkStrength() {
    const pass = document.getElementById('newPassword').value;
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    const count = document.getElementById('charCount');

    count.textContent = pass.length + '/8 chars';

    if (pass.length === 0) {
        bar.style.width = '0%';
        text.textContent = 'Password strength';
        text.className = 'pass-hint text-muted';
        return;
    }

    let score = 0;
    if (pass.length >= 8) score += 25;
    if (pass.length >= 12) score += 15;
    if (/[a-z]/.test(pass) && /[A-Z]/.test(pass)) score += 25;
    if (/\d/.test(pass)) score += 20;
    if (/[^a-zA-Z0-9]/.test(pass)) score += 15;

    score = Math.min(100, score);
    bar.style.width = score + '%';

    if (score < 40) {
        bar.style.backgroundColor = '#dc3545';
        text.textContent = 'Weak password';
        text.className = 'pass-hint text-danger fw-medium';
    } else if (score < 75) {
        bar.style.backgroundColor = '#ffc107';
        text.textContent = 'Medium strength';
        text.className = 'pass-hint text-warning fw-medium';
    } else {
        bar.style.backgroundColor = '#198754';
        text.textContent = 'Strong password';
        text.className = 'pass-hint text-success fw-medium';
    }
    checkMatch();
}

function checkMatch() {
    const pass = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmPassword').value;
    const matchText = document.getElementById('matchText');

    if (confirm.length === 0) {
        matchText.textContent = '';
        return;
    }

    if (pass === confirm) {
        matchText.textContent = '✓ Passwords match';
        matchText.className = 'pass-hint text-success fw-medium';
    } else {
        matchText.textContent = '✕ Passwords do not match';
        matchText.className = 'pass-hint text-danger fw-medium';
    }
}
</script>
</body>
</html>
