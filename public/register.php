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
    if ($rl->isBlocked('register')) {
        $error = 'Too many registration attempts from this connection. Please wait ' . ceil($rl->blockedSecondsRemaining('register') / 60) . ' minute(s).';
    } else {
    $name     = ucwords(strtolower(trim($_POST['name'] ?? '')));
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (isFakeEmail($email)) {
        $error = "We couldn't deliver mail to that address. Please check it for typos or use a different email.";
    } elseif (!isDeliverableEmail($email)) {
        $error = "That email address doesn't appear to be deliverable. Please double-check it for typos.";
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Count this as an attempt now, regardless of outcome below -- this is
        // what actually limits OTP-spam against someone else's real address,
        // since we can't tell at submit time whether the mailbox is real or not.
        $rl->recordFailure('register', 5, 15, 30);

        $db  = getDB();
        $chk = $db->prepare("SELECT id, is_verified, created_at FROM users WHERE email = ?");
        $chk->execute([$email]);
        $existing = $chk->fetch();

        // A signup that was never verified shouldn't permanently squat the email --
        // otherwise a typo'd or fake address (or someone else's real address typed
        // by mistake) blocks that person from ever registering it for real later.
        if ($existing && !$existing['is_verified'] && strtotime($existing['created_at']) < strtotime('-15 minutes')) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$existing['id']]);
            $existing = null;
        }

        if ($existing) {
            $error = $existing['is_verified']
                ? 'This email is already registered.'
                : 'A verification email was already sent to this address recently. Check your inbox (and spam folder), or try again in a few minutes.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $otp  = generateOTP();
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hash]);
                $userId = $db->lastInsertId();
                saveOTP($userId, $otp);
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log('Registration error: ' . $e->getMessage());
                $error = 'Registration failed. Please try again.';
                goto render;
            }
            if (sendOTPEmail($email, $name, $otp)) {
                $_SESSION['pending_user_id'] = $userId;
                // Backup cookie in case session drops over proxy/ngrok
                setcookie('pending_uid', $userId, time() + 300, '/', '', false, true);
                header('Location: ' . BASE_PATH . '/public/verify-otp.php');
                exit;
            } else {
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                $error = 'Could not send verification email. Please try again.';
            }
        }
        render:
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — QuizApp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
:root {
    --blue-50: #edf4ff; --blue-100: #d9e8ff; --blue-200: #bcd7ff;
    --blue-300: #94bfff; --blue-400: #6aa3ff; --blue-500: #4388f5;
    --blue-600: #336fd9; --blue-700: #2a5dc2; --blue-800: #224da6;
    --white: #ffffff; --gray-50: #f8fafc; --gray-100: #f1f5f9;
    --gray-200: #e2e8f0; --gray-300: #cbd5e1; --gray-400: #94a3b8;
    --gray-500: #64748b; --gray-600: #475569; --gray-700: #334155;
    --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
    --radius-md: 12px; --radius-lg: 16px; --radius-xl: 24px;
    --transition-fast: 0.2s ease; --transition-normal: 0.3s ease;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, var(--blue-50) 0%, var(--blue-100) 40%, var(--white) 100%);
    min-height: 100vh; display: flex; align-items: center;
    justify-content: center; overflow: hidden; padding: 20px;
}

@keyframes popUpFromBottom {
    0%   { opacity:0; transform:translateY(200px) scale(0.9); filter:blur(10px); }
    30%  { opacity:0.3; filter:blur(5px); }
    60%  { opacity:0.7; filter:blur(1px); }
    80%  { transform:translateY(-6px) scale(1.005); filter:blur(0); }
    90%  { transform:translateY(3px) scale(0.999); }
    100% { opacity:1; transform:translateY(0) scale(1); filter:blur(0); }
}
@keyframes shadowPulse {
    0%,100% { box-shadow: 0 25px 60px -15px rgba(37,99,235,0.2), 0 10px 30px -10px rgba(0,0,0,0.1); }
    50%      { box-shadow: 0 30px 70px -15px rgba(37,99,235,0.3), 0 15px 40px -10px rgba(0,0,0,0.15); }
}
@keyframes fadeInStagger {
    0%   { opacity:0; transform:translateY(20px); }
    100% { opacity:1; transform:translateY(0); }
}
@keyframes float {
    0%,100% { transform:translate(0,0) rotate(0deg); }
    50%      { transform:translate(-10px,10px) rotate(-2deg); }
}
@keyframes spin { to { transform:rotate(360deg); } }
@keyframes toastIn {
    from { opacity:0; transform:translateX(80px) scale(0.95); }
    to   { opacity:1; transform:translateX(0) scale(1); }
}
@keyframes toastOut {
    to { opacity:0; transform:translateX(80px) scale(0.95); }
}
@keyframes slideOut {
    to { opacity:0; transform:translateY(-30px) scale(0.97); }
}
@keyframes slideIn {
    from { opacity:0; transform:translateY(30px) scale(0.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}

.card-container {
    display: flex; width: 100%; max-width: 780px;
    background: var(--white); border-radius: var(--radius-xl);
    overflow: hidden; position: relative; z-index: 1;
    animation: popUpFromBottom 3.0s cubic-bezier(0.16,1,0.3,1) forwards,
               shadowPulse 4s ease-in-out 3.5s infinite;
    box-shadow: 0 25px 60px -15px rgba(37,99,235,0.2),
                0 10px 30px -10px rgba(0,0,0,0.1),
                0 0 0 1px rgba(37,99,235,0.05);
}

/* Left blue panel */
.brand-panel {
    flex: 0 0 300px;
    background: linear-gradient(145deg, var(--blue-500) 0%, var(--blue-600) 50%, var(--blue-700) 100%);
    padding: 36px 32px; display: flex; flex-direction: column;
    justify-content: space-between; position: relative; overflow: hidden;
}
.brand-panel::before,
.brand-panel::after { display:none; }
.brand-content { position:relative; z-index:1; animation:fadeInStagger 0.8s ease 0.4s both; }
.logo-icon { margin-bottom:20px; animation:fadeInStagger 0.7s ease 0.5s both; }
.logo-icon svg { filter:drop-shadow(0 4px 12px rgba(0,0,0,0.15)); }
.brand-title { font-size:1.6rem; font-weight:800; color:var(--white); letter-spacing:-0.5px; margin-bottom:10px; animation:fadeInStagger 0.7s ease 0.6s both; }
.brand-subtitle { font-size:0.9rem; color:rgba(255,255,255,0.95); font-weight:600; line-height:1.5; margin-bottom:16px; animation:fadeInStagger 0.7s ease 0.7s both; }
.brand-tagline { font-size:0.8rem; color:rgba(255,255,255,0.65); font-weight:400; line-height:1.6; animation:fadeInStagger 0.7s ease 0.8s both; }
.brand-footer { position:relative; z-index:1; animation:fadeInStagger 0.7s ease 0.9s both; }
.brand-footer p { font-size:0.78rem; color:rgba(255,255,255,0.5); }

/* Right form panel */
.form-panel {
    flex:1; padding:32px; display:flex; align-items:center;
    justify-content:center; background:var(--white); position:relative;
}
.form-wrapper { width:100%; max-width:360px; animation:fadeInStagger 0.8s ease 0.5s both; }
.form-wrapper.hidden { display:none; }
.form-wrapper.slide-out { animation:slideOut 0.35s ease forwards; }
.form-wrapper.slide-in  { animation:slideIn 0.45s cubic-bezier(0.16,1,0.3,1) forwards; }

.form-header { margin-bottom:22px; }
.form-header h2 { font-size:1.35rem; font-weight:700; color:var(--gray-700); letter-spacing:-0.3px; margin-bottom:6px; }
.form-header p  { font-size:0.82rem; color:var(--gray-400); }

/* PHP error box */
.php-error {
    background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
    border-radius:var(--radius-md); padding:10px 14px;
    font-size:0.82rem; margin-bottom:16px; font-weight:500;
}

.input-group { margin-bottom:14px; }
.input-group label { display:block; font-size:0.75rem; font-weight:600; color:var(--gray-600); margin-bottom:6px; letter-spacing:0.2px; }
.input-wrapper { position:relative; display:flex; align-items:center; }
.input-icon { position:absolute; left:14px; width:18px; height:18px; color:var(--gray-400); pointer-events:none; transition:color var(--transition-fast); z-index:2; }
.input-wrapper input {
    width:100%; padding:10px 12px 10px 40px;
    font-family:'Inter',sans-serif; font-size:0.85rem; color:var(--gray-700);
    background:var(--gray-50); border:2px solid var(--gray-200);
    border-radius:var(--radius-md); outline:none; transition:all var(--transition-normal);
}
.input-wrapper input::placeholder { color:var(--gray-400); }
.input-wrapper input:hover { border-color:var(--blue-300); background:var(--white); }
.input-wrapper input:focus { border-color:var(--blue-500); background:var(--white); box-shadow:0 0 0 4px rgba(59,130,246,0.1); }
.input-wrapper.focused .input-icon { color:var(--blue-500); }
.toggle-password {
    position:absolute; right:12px; background:none; border:none;
    cursor:pointer; color:var(--gray-400); padding:4px;
    display:flex; align-items:center; justify-content:center;
    border-radius:6px; transition:all var(--transition-fast); z-index:2;
}
.toggle-password:hover { color:var(--blue-500); background:var(--blue-50); }
.input-wrapper input[type="password"],
.input-wrapper input[type="text"] { padding-right:40px; }

.input-wrapper.error input { border-color:#ef4444; background:#fef2f2; }
.input-wrapper.error .input-icon { color:#ef4444; }
.error-message { font-size:0.75rem; color:#ef4444; margin-top:4px; font-weight:500; animation:fadeInStagger 0.3s ease both; }

.btn-primary {
    width:100%; padding:12px 20px; display:flex; align-items:center;
    justify-content:center; gap:10px; font-family:'Inter',sans-serif;
    font-size:0.95rem; font-weight:600; color:var(--white);
    background:linear-gradient(135deg, var(--blue-500) 0%, var(--blue-600) 100%);
    border:none; border-radius:var(--radius-md); cursor:pointer;
    transition:all var(--transition-normal); position:relative; overflow:hidden;
    box-shadow:0 4px 15px -3px rgba(37,99,235,0.4); margin-top:4px;
}
.btn-primary::before {
    content:''; position:absolute; top:0; left:-100%; width:100%; height:100%;
    background:linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition:left 0.6s ease;
}
.btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 25px -5px rgba(37,99,235,0.5); background:linear-gradient(135deg, var(--blue-600) 0%, var(--blue-700) 100%); }
.btn-primary:hover::before { left:100%; }
.btn-primary:active { transform:translateY(0); }
.btn-primary svg { transition:transform var(--transition-fast); }
.btn-primary:hover svg { transform:translateX(4px); }
.btn-primary.loading { pointer-events:none; opacity:0.85; }
.btn-primary.loading span { opacity:0; }
.btn-primary.loading::after { content:''; position:absolute; width:22px; height:22px; border:3px solid rgba(255,255,255,0.3); border-top-color:white; border-radius:50%; animation:spin 0.7s linear infinite; }
.btn-primary.success { background:linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow:0 4px 15px -3px rgba(16,185,129,0.4); }

.form-footer { text-align:center; margin-top:20px; padding-top:16px; border-top:1px solid var(--gray-200); display:flex; flex-direction:column; align-items:center; }
.form-footer p { font-size:0.88rem; color:var(--gray-500); }
.form-footer a { color:var(--blue-600); text-decoration:none; font-weight:700; transition:all var(--transition-fast); position:relative; }
.form-footer a::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:2px; background:var(--blue-600); border-radius:1px; transition:width var(--transition-normal); }
.form-footer a:hover { color:var(--blue-700); }
.form-footer a:hover::after { width:100%; }

.toast {
    position:fixed; top:30px; right:30px; padding:16px 24px;
    background:var(--white); border-radius:var(--radius-md);
    box-shadow:var(--shadow-xl); border-left:4px solid var(--blue-500);
    display:flex; align-items:center; gap:12px;
    font-size:0.9rem; font-weight:500; color:var(--gray-700);
    z-index:100; animation:toastIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards;
}
.toast.success { border-left-color:#10b981; }
.toast.hiding  { animation:toastOut 0.4s ease forwards; }

@media (max-width: 860px) {
    .card-container { flex-direction:column; max-width:480px; }
    .brand-panel { flex:none; padding:32px 28px; }
    .form-panel  { padding:32px 28px; }
}
@media (max-width: 480px) {
    body { padding:12px; align-items:flex-start; padding-top:40px; }
    .brand-panel { padding:24px 20px; }
    .form-panel  { padding:24px 20px; }
    .brand-title { font-size:1.5rem; }
}
    </style>
</head>
<body>
<div class="card-container" id="cardContainer">

    <!-- Left blue panel -->
    <div class="brand-panel">
        <div class="brand-content">
            <div class="logo-icon">
                <svg width="44" height="44" viewBox="0 0 48 48" fill="none">
                    <circle cx="24" cy="24" r="22" stroke="white" stroke-width="2.5" fill="rgba(255,255,255,0.15)"/>
                    <text x="24" y="30" text-anchor="middle" fill="white" font-size="22" font-weight="700" font-family="Inter">Q</text>
                </svg>
            </div>
            <h1 class="brand-title">Quiz App</h1>
            <p class="brand-subtitle">Sharpen your skills. Prove your expertise. Rise to the top.</p>
            <p class="brand-tagline">Join 50,000+ learners who trust Quiz App to boost their knowledge and ace every challenge.</p>
        </div>
        <div class="brand-footer"><p>🔒 Your data is safe & encrypted</p></div>
    </div>

    <!-- Right form panel -->
    <div class="form-panel">

        <!-- Register form -->
        <div class="form-wrapper" id="signupForm">
            <div class="form-header">
                <h2>Create Your Account</h2>
                <p>Join thousands of quiz enthusiasts today</p>
            </div>

            <?php if ($error): ?>
                <div class="php-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="registerForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div class="input-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" name="name" placeholder="Enter your name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 4L12 13 2 4"/></svg>
                        <input type="email" name="email" placeholder="Enter your email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" id="password" placeholder="Min 8 characters" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M10 16l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <input type="password" name="confirm" id="confirmPassword" placeholder="Re-enter password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="signupBtn">
                    <span>Create Account</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Sign In</a></p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
    } else {
        input.type = 'password';
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
    }
}

document.querySelectorAll('.input-wrapper input').forEach(input => {
    input.addEventListener('focus', () => input.closest('.input-wrapper').classList.add('focused'));
    input.addEventListener('blur',  () => input.closest('.input-wrapper').classList.remove('focused'));
});

function showToast(message, type = 'success') {
    document.querySelectorAll('.toast').forEach(t => t.remove());
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="${type === 'success' ? '#10b981' : '#3b82f6'}" stroke-width="2.5" width="22" height="22"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('hiding'); setTimeout(() => toast.remove(), 400); }, 3000);
}

function clearErrors(form) {
    form.querySelectorAll('.input-wrapper.error').forEach(w => w.classList.remove('error'));
    form.querySelectorAll('.error-message').forEach(m => m.remove());
}
function showError(input, message) {
    const wrapper = input.closest('.input-wrapper');
    wrapper.classList.add('error');
    const existing = wrapper.parentElement.querySelector('.error-message');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'error-message';
    div.textContent = message;
    wrapper.parentElement.appendChild(div);
}

document.getElementById('registerForm').addEventListener('submit', function(e) {
    // Let PHP handle submission — only do client-side UX loading state
    clearErrors(this);
    const name = this.querySelector('[name="name"]');
    const email = this.querySelector('[name="email"]');
    const pw    = this.querySelector('[name="password"]');
    const conf  = this.querySelector('[name="confirm"]');
    let valid = true;
    if (name.value.trim().length < 2)              { showError(name, 'Please enter your full name'); valid = false; }
    if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) { showError(email, 'Please enter a valid email'); valid = false; }
    if (pw.value.length < 8)                       { showError(pw, 'Password must be at least 8 characters'); valid = false; }
    if (pw.value !== conf.value)                   { showError(conf, 'Passwords do not match'); valid = false; }
    if (!valid) { e.preventDefault(); return; }
    const btn = document.getElementById('signupBtn');
    btn.classList.add('loading');
});
</script>
</body>
</html>