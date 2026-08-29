<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — QuizApp</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=6">
    <style>
        .otp-inputs { display:flex; gap:10px; justify-content:center; margin:20px 0; }
        .otp-inputs input {
            width:48px; height:56px; text-align:center;
            font-size:22px; font-weight:600;
            border:1px solid #ced4da; border-radius:8px;
            background:#fff; color:#212529;
        }
        .otp-inputs input:focus { border-color:#185FA5; outline:none; box-shadow:0 0 0 3px rgba(24,95,165,.15); }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo text-center">QuizApp</div>
        <p class="text-center text-muted small mb-3">Please enter the 6-digit verification code sent to your email</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/verify-otp" id="otpForm">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" name="otp[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off" <?= $i === 0 ? 'autofocus' : '' ?>>
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3" id="verifyBtn">Verify & Activate Account</button>
        </form>

        <p class="auth-divider"><a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login" class="text-decoration-none">Back to login</a></p>
    </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-inputs input');
inputs.forEach((input, index) => {
    input.addEventListener('input', () => {
        if (input.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
    });
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
            inputs[index - 1].focus();
        }
    });
    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const data = (e.clipboardData || window.clipboardData).getData('text').trim();
        if (/^\d{6}$/.test(data)) {
            data.split('').forEach((char, i) => { inputs[i].value = char; });
            inputs[5].focus();
        }
    });
});
</script>
</body>
</html>
