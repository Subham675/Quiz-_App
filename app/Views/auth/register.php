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
    font-family:'Inter',sans-serif;
    background:linear-gradient(135deg, var(--blue-50) 0%, var(--blue-100) 40%, var(--white) 100%);
    min-height:100vh; display:flex; align-items:center;
    justify-content:center; overflow:hidden; padding:20px;
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
    0%,100% { box-shadow:0 25px 60px -15px rgba(37,99,235,0.2), 0 10px 30px -10px rgba(0,0,0,0.1); }
    50%      { box-shadow:0 30px 70px -15px rgba(37,99,235,0.3), 0 15px 40px -10px rgba(0,0,0,0.15); }
}
@keyframes fadeInStagger { 0%{opacity:0;transform:translateY(20px)} 100%{opacity:1;transform:translateY(0)} }

.card-container {
    display:flex; width:100%; max-width:780px;
    background:var(--white); border-radius:var(--radius-xl);
    overflow:hidden; position:relative; z-index:1;
    animation: popUpFromBottom 1.2s cubic-bezier(0.16,1,0.3,1) forwards,
               shadowPulse 4s ease-in-out 1.5s infinite;
    box-shadow:0 25px 60px -15px rgba(37,99,235,0.2),
               0 10px 30px -10px rgba(0,0,0,0.1),
               0 0 0 1px rgba(37,99,235,0.05);
}

.brand-panel {
    flex:0 0 300px;
    background:linear-gradient(145deg, var(--blue-500) 0%, var(--blue-600) 50%, var(--blue-700) 100%);
    padding:36px 32px; display:flex; flex-direction:column;
    justify-content:space-between; position:relative; overflow:hidden;
}
.brand-content { position:relative; z-index:1; }
.logo-icon { margin-bottom:20px; }
.brand-title { font-size:1.6rem; font-weight:800; color:var(--white); letter-spacing:-0.5px; margin-bottom:10px; }
.brand-subtitle { font-size:0.9rem; color:rgba(255,255,255,0.95); font-weight:600; line-height:1.5; margin-bottom:16px; }
.brand-tagline { font-size:0.8rem; color:rgba(255,255,255,0.65); line-height:1.6; }
.brand-footer p { font-size:0.78rem; color:rgba(255,255,255,0.5); }

.form-panel { flex:1; padding:32px; display:flex; align-items:center; justify-content:center; background:var(--white); }
.form-wrapper { width:100%; max-width:360px; }

.form-header { margin-bottom:20px; }
.form-header h2 { font-size:1.35rem; font-weight:700; color:var(--gray-700); letter-spacing:-0.3px; margin-bottom:6px; }
.form-header p  { font-size:0.82rem; color:var(--gray-400); }

.php-error { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c; border-radius:var(--radius-md); padding:10px 14px; font-size:0.82rem; margin-bottom:16px; font-weight:500; }

.input-group { margin-bottom:12px; }
.input-group label { display:block; font-size:0.75rem; font-weight:600; color:var(--gray-600); margin-bottom:4px; letter-spacing:0.2px; }
.input-wrapper { position:relative; display:flex; align-items:center; }
.input-icon { position:absolute; left:14px; width:18px; height:18px; color:var(--gray-400); pointer-events:none; z-index:2; }
.input-wrapper input {
    width:100%; height:42px; padding:0 40px 0 42px;
    border:1.5px solid var(--gray-200); border-radius:var(--radius-md);
    font-size:0.875rem; font-family:'Inter',sans-serif; color:var(--gray-700);
    background:var(--gray-50); outline:none; transition:all var(--transition-fast);
}
.input-wrapper input:focus { border-color:var(--blue-500); background:var(--white); box-shadow:0 0 0 3.5px rgba(59,130,246,0.12); }

.toggle-password {
    position:absolute; right:12px; background:none; border:none;
    color:var(--gray-400); cursor:pointer; padding:4px; display:flex;
    align-items:center; justify-content:center;
}

.btn-primary {
    width:100%; height:44px; background:linear-gradient(135deg, var(--blue-500) 0%, var(--blue-600) 100%);
    color:var(--white); border:none; border-radius:var(--radius-md);
    font-size:0.875rem; font-weight:600; font-family:'Inter',sans-serif;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;
    box-shadow:0 4px 14px rgba(37,99,235,0.3); transition:all var(--transition-fast); margin-top:16px;
}
.btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(37,99,235,0.4); }

.form-footer { margin-top:18px; text-align:center; }
.form-footer p { font-size:0.82rem; color:var(--gray-500); }
.form-footer a { color:var(--blue-600); font-weight:600; text-decoration:none; }
.form-footer a:hover { text-decoration:underline; }

@media(max-width:680px) {
    .card-container { flex-direction:column; max-width:400px; }
    .brand-panel { flex:none; padding:28px 24px 20px; }
    .form-panel { padding:24px; }
}
    </style>
</head>
<body>
<div class="card-container">
    <div class="brand-panel">
        <div class="brand-content">
            <div class="logo-icon">
                <svg width="42" height="42" viewBox="0 0 48 48" fill="none">
                    <rect width="48" height="48" rx="14" fill="rgba(255,255,255,0.2)"/>
                    <path d="M16 24L22 30L32 18" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="brand-title">QuizApp</h1>
            <p class="brand-subtitle">Create your account</p>
            <p class="brand-tagline">Start your assessment journey, earn certificates, and rank on leaderboards.</p>
        </div>
        <div class="brand-footer">
            <p>&copy; <?= date('Y') ?> QuizApp Academy</p>
        </div>
    </div>

    <div class="form-panel">
        <div class="form-wrapper">
            <div class="form-header">
                <h2>Join QuizApp</h2>
                <p>Fill in your details to get started</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="php-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/register">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <div class="input-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <input type="text" name="name" placeholder="John Doe" required autofocus maxlength="100">
                    </div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" name="email" placeholder="you@example.com" required maxlength="150">
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input type="password" name="password" id="regPassword" placeholder="Min 8 characters" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePass('regPassword', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><path d="M10 16l2 2 4-4"/></svg>
                        <input type="password" name="confirm" id="regConfirm" placeholder="Re-enter password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePass('regConfirm', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    <span>Create Account</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </form>

            <div class="form-footer">
                <p>Already have an account? <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/login">Sign In</a></p>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass(id, btn) {
    const el = document.getElementById(id);
    if (el.type === 'password') {
        el.type = 'text';
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
    } else {
        el.type = 'password';
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
    }
}
</script>
</body>
</html>
