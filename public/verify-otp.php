<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

startSession();
if (isLoggedIn()) { header('Location: /Quiz_app/index.php'); exit; }
if (empty($_SESSION['pending_user_id'])) { header('Location: register.php'); exit; }

$error = $success = '';
$userId = $_SESSION['pending_user_id'];

// Get OTP expiry time to pass to JS
$db = getDB();
$expStmt = $db->prepare("SELECT otp_expires_at FROM users WHERE id = ?");
$expStmt->execute([$userId]);
$otpExpiresAt = $expStmt->fetchColumn();
$secondsLeft = max(0, strtotime($otpExpiresAt) - time());

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
    <link rel="stylesheet" href="/Quiz_app/assets/css/style.css?v=4">
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

        .timer-wrap {
            text-align: center;
            margin-bottom: 16px;
        }
        .timer-circle {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: conic-gradient(var(--accent) 0deg, var(--border) 0deg);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 8px;
            transition: background .1s;
            position: relative;
        }
        .timer-circle::after {
            content: '';
            width: 54px; height: 54px;
            border-radius: 50%;
            background: var(--surface);
            position: absolute;
        }
        .timer-text {
            font-size: 18px; font-weight: 700;
            color: var(--text);
            position: relative; z-index: 1;
        }
        .timer-label {
            font-size: 12px; color: var(--muted);
        }
        .timer-wrap.expired .timer-circle { background: conic-gradient(var(--danger) 360deg, var(--danger) 0deg); }
        .timer-wrap.expired .timer-text { color: var(--danger); }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.06 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7a2 2 0 0 1 1.72 2.01z"/>
            </svg>
        </div>
        <div class="auth-logo">Verify your email</div>
        <div class="auth-subtitle">Enter the 6-digit OTP sent to your email</div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Countdown timer -->
        <?php if (!$success && !$error): ?>
        <div class="timer-wrap" id="timerWrap">
            <div class="timer-circle" id="timerCircle">
                <span class="timer-text" id="timerText">1:30</span>
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
            <button type="submit" id="submitBtn" class="btn btn-primary" style="width:100%;justify-content:center">
                Verify OTP
            </button>
        </form>

        <p style="text-align:center;margin-top:16px;font-size:13px;color:var(--muted)">
            Didn't receive it? <a href="register.php">Register again</a>
        </p>
    </div>
</div>
<script>
// OTP input auto-advance
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

// Countdown timer
(function() {
    const totalSeconds = 60;
    if (totalSeconds <= 0) {
        expired();
        return;
    }

    let secondsLeft = totalSeconds;
    const timerText  = document.getElementById('timerText');
    const timerCircle= document.getElementById('timerCircle');
    const timerLabel = document.getElementById('timerLabel');
    const timerWrap  = document.getElementById('timerWrap');
    const submitBtn  = document.getElementById('submitBtn');

    function formatTime(s) {
        const m = Math.floor(s / 60);
        const sec = (s % 60).toString().padStart(2, '0');
        return m + ':' + sec;
    }

    function updateCircle(s) {
        const pct = s / totalSeconds;
        const deg = Math.round(pct * 360);
        const color = s > 30 ? 'var(--accent)' : 'var(--danger)';
        if (timerCircle) {
            timerCircle.style.background = `conic-gradient(${color} ${deg}deg, var(--border) 0deg)`;
        }
    }

    function expired() {
        if (timerWrap) timerWrap.classList.add('expired');
        if (timerText) timerText.textContent = '0:00';
        if (timerLabel) timerLabel.textContent = 'OTP expired — register again';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'OTP Expired';
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }
        inputs.forEach(i => i.disabled = true);
    }

    if (timerText) timerText.textContent = formatTime(secondsLeft);
    updateCircle(secondsLeft);

    const interval = setInterval(() => {
        secondsLeft--;
        if (secondsLeft <= 0) {
            clearInterval(interval);
            expired();
            return;
        }
        if (timerText) timerText.textContent = formatTime(secondsLeft);
        updateCircle(secondsLeft);
        if (secondsLeft <= 30 && timerLabel) {
            timerLabel.textContent = 'Hurry! OTP expires soon';
        }
    }, 1000);
})();
</script>
</body>
</html>