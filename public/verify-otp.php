<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

startSession();
if (isLoggedIn()) { header('Location: ' . BASE_PATH . '/index.php'); exit; }
if (empty($_SESSION['pending_user_id'])) { header('Location: register.php'); exit; }

$error = $success = '';
$userId = $_SESSION['pending_user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $rl = new RateLimiter(getDB());

    if ($rl->isBlocked('otp')) {
        $wait  = $rl->blockedSecondsRemaining('otp');
        $error = "Too many wrong OTP attempts. Try again in " . ceil($wait / 60) . " minute(s).";
    } else {
        $otp = (int) implode('', $_POST['otp'] ?? []);
        $result = verifyOTP($userId, $otp);

        if ($result === 'ok') {
            $rl->reset('otp');
            unset($_SESSION['pending_user_id']);
            $success = 'Email verified! You can now log in.';
            header('refresh:2;url=login.php');
        } elseif ($result === 'expired') {
            $error = 'OTP has expired. Please register again.';
        } elseif ($result === 'max_attempts') {
            $rl->recordFailure('otp', 5, 10, 30);
            $error = 'Too many wrong attempts. Your IP has been temporarily blocked.';
        } else {
            $rl->recordFailure('otp', 5, 10, 30);
            $error = 'Invalid OTP. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — QuizApp</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
    <style>
        .otp-inputs { display:flex; gap:10px; justify-content:center; margin:20px 0; }
        .otp-inputs input {
            width:48px; height:56px; text-align:center;
            font-size:22px; font-weight:600;
            border:1px solid var(--border);
            border-radius:var(--radius-md);
            background:var(--surface);
            color:var(--text);
        }
        .otp-inputs input:focus { border-color:var(--accent); outline:none; box-shadow:0 0 0 3px rgba(24,95,165,.1); }

        .timer-wrap { text-align:center; margin-bottom:16px; }
        .timer-circle {
            width:72px; height:72px; border-radius:50%;
            background:conic-gradient(#185FA5 360deg, #E4E6EA 0deg);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 8px; position:relative;
        }
        .timer-circle::after {
            content:''; width:54px; height:54px; border-radius:50%;
            background:#fff; position:absolute;
        }
        .timer-text { font-size:18px; font-weight:700; color:#111318; position:relative; z-index:1; }
        .timer-label { font-size:12px; color:#6B7280; }
        .timer-wrap.expired .timer-text { color:#E24B4A; }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-logo">QuizApp</div>
        <div class="auth-subtitle">Enter the 6-digit OTP sent to your email</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Countdown Timer -->
        <?php if (!$success && !$error): ?>
        <div class="timer-wrap" id="timerWrap">
            <div class="timer-circle" id="timerCircle">
                <span class="timer-text" id="timerText">1:00</span>
            </div>
            <div class="timer-label" id="timerLabel">OTP expires in</div>
        </div>
        <?php endif; ?>

                <form method="POST" id="otp-form">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <div class="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="text" name="otp[]" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
                Verify OTP
            </button>
        </form>

        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">
            Didn't receive it? <a href="register.php">Try again</a>
        </p>
    </div>
</div>
<script>
const inputs = document.querySelectorAll('.otp-inputs input');
inputs.forEach((el, i) => {
    el.addEventListener('input', () => {
        el.value = el.value.replace(/\D/g, '').slice(-1);
        if (el.value && i < inputs.length - 1) inputs[i+1].focus();
    });
    el.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !el.value && i > 0) inputs[i-1].focus();
    });
});
</script>

<script>
(function() {
    var secondsLeft = 60;
    var timerText   = document.getElementById('timerText');
    var timerCircle = document.getElementById('timerCircle');
    var timerLabel  = document.getElementById('timerLabel');
    var timerWrap   = document.getElementById('timerWrap');
    var submitBtn   = document.querySelector('button[type="submit"]');
    var inputs      = document.querySelectorAll('.otp-inputs input');

    function fmt(s) {
        return Math.floor(s/60) + ':' + (s%60).toString().padStart(2,'0');
    }

    function expired() {
        if (timerWrap) timerWrap.classList.add('expired');
        if (timerText) timerText.textContent = '0:00';
        if (timerLabel) timerLabel.textContent = 'OTP expired — register again';
        if (timerCircle) timerCircle.style.background = 'conic-gradient(#E24B4A 360deg, #E24B4A 0deg)';
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'OTP Expired'; submitBtn.style.opacity = '0.5'; }
        inputs.forEach(function(i){ i.disabled = true; });
    }

    if (!timerText) return;

    var interval = setInterval(function() {
        secondsLeft--;
        if (secondsLeft <= 0) { clearInterval(interval); expired(); return; }
        if (timerText) timerText.textContent = fmt(secondsLeft);
        var deg = Math.round((secondsLeft/60)*360);
        var color = secondsLeft > 20 ? '#185FA5' : '#E24B4A';
        if (timerCircle) timerCircle.style.background = 'conic-gradient(' + color + ' ' + deg + 'deg, #E4E6EA 0deg)';
        if (secondsLeft <= 20 && timerLabel) timerLabel.textContent = 'Hurry! OTP expires soon';
    }, 1000);
})();
</script>
</body>
</html>