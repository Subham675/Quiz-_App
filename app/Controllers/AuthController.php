<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\User;
use App\Core\Model;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->render('auth/login', [
            'error'   => $_SESSION['login_error'] ?? '',
            'success' => $_SESSION['login_success'] ?? '',
        ], null);
        unset($_SESSION['login_error'], $_SESSION['login_success']);
    }

    public function login(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['login_error'] = 'Invalid form session. Please try again.';
            $this->redirect('/login');
        }

        $db = Model::getDb();
        $rl = new \RateLimiter($db);

        if ($rl->isBlocked('login')) {
            $wait = ceil($rl->blockedSecondsRemaining('login') / 60);
            $_SESSION['login_error'] = "Too many failed attempts. Try again in {$wait} minute(s).";
            $this->redirect('/login');
        }

        $email    = strtolower(trim($request->input('email', '')));
        $password = (string)$request->input('password', '');

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Please enter both email and password.';
            $this->redirect('/login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isFakeEmail($email)) {
            $_SESSION['login_error'] = 'Invalid email address or fake email domain.';
            $this->redirect('/login');
        }

        $user = User::findByEmail($email);

        if (!$user) {
            $rl->recordFailure('login', 5, 10, 15);
            $_SESSION['login_error'] = 'No account found with this email. Please register.';
            $this->redirect('/login');
        }

        if (!password_verify($password, $user['password'])) {
            $rl->recordFailure('login', 5, 10, 15);
            $_SESSION['login_error'] = 'Incorrect password. Please try again.';
            $this->redirect('/login');
        }

        if (!$user['is_verified']) {
            $_SESSION['pending_user_id'] = $user['id'];
            $_SESSION['login_error'] = 'Your email is not verified yet.';
            $this->redirect('/verify-otp');
        }

        if (!empty($user['is_banned'])) {
            $_SESSION['login_error'] = 'Your account has been suspended. Please contact support.';
            $this->redirect('/login');
        }

        $rl->reset('login');
        loginUser($user);

        if ($user['role'] === 'admin') {
            $this->redirect('/admin');
        } else {
            $this->redirect('/dashboard');
        }
    }

    public function showRegister(Request $request): void
    {
        $this->render('auth/register', [
            'error'   => $_SESSION['reg_error'] ?? '',
            'success' => $_SESSION['reg_success'] ?? '',
        ], null);
        unset($_SESSION['reg_error'], $_SESSION['reg_success']);
    }

    public function register(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['reg_error'] = 'Session expired. Please try again.';
            $this->redirect('/register');
        }

        $db = Model::getDb();
        $rl = new \RateLimiter($db);

        if ($rl->isBlocked('register')) {
            $wait = ceil($rl->blockedSecondsRemaining('register') / 60);
            $_SESSION['reg_error'] = "Too many registration attempts. Please wait {$wait} minute(s).";
            $this->redirect('/register');
        }

        $name     = strip_tags(trim($request->input('name', '')));
        $email    = strtolower(trim($request->input('email', '')));
        $password = (string)$request->input('password', '');
        $confirm  = (string)$request->input('confirm', '');

        if (!$name || !$email || !$password) {
            $_SESSION['reg_error'] = 'All fields are required.';
            $this->redirect('/register');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isFakeEmail($email) || !isDeliverableEmail($email)) {
            $_SESSION['reg_error'] = 'Invalid email address or undeliverable email mailbox.';
            $this->redirect('/register');
        }

        if (strlen($password) < 8) {
            $_SESSION['reg_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/register');
        }

        if ($password !== $confirm) {
            $_SESSION['reg_error'] = 'Passwords do not match.';
            $this->redirect('/register');
        }

        if (User::findByEmail($email)) {
            $_SESSION['reg_error'] = 'This email is already registered.';
            $this->redirect('/register');
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $otp    = generateOTP();
        $userId = User::create($name, $email, $hash);

        saveOTP($userId, $otp);

        $smtpUser = $_ENV['MAIL_USER'] ?? $_ENV['SMTP_USER'] ?? '';
        $smtpPass = $_ENV['MAIL_PASS'] ?? $_ENV['SMTP_PASS'] ?? '';

        if (empty($smtpUser) || empty($smtpPass)) {
            User::verifyEmail($userId);
            $_SESSION['login_success'] = 'Account registered! You can now log in.';
            $this->redirect('/login');
        }

        if (sendOTPEmail($email, $name, $otp)) {
            $_SESSION['pending_user_id'] = $userId;
            $rl->reset('register');
            $this->redirect('/verify-otp');
        } else {
            User::delete($userId);
            $rl->recordFailure('register', 5, 10, 15);
            $_SESSION['reg_error'] = 'Failed to send OTP verification email. Please try again.';
            $this->redirect('/register');
        }
    }

    public function showVerifyOtp(Request $request): void
    {
        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId) {
            $this->redirect('/register');
        }

        $this->render('auth/verify-otp', [
            'userId'  => $userId,
            'error'   => $_SESSION['otp_error'] ?? '',
            'success' => $_SESSION['otp_success'] ?? '',
        ], null);
        unset($_SESSION['otp_error'], $_SESSION['otp_success']);
    }

    public function verifyOtp(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['otp_error'] = 'Session expired. Please try again.';
            $this->redirect('/verify-otp');
        }

        $userId = $_SESSION['pending_user_id'] ?? null;
        if (!$userId) {
            $this->redirect('/register');
        }

        $db = Model::getDb();
        $rl = new \RateLimiter($db);

        if ($rl->isBlocked('otp')) {
            $wait = ceil($rl->blockedSecondsRemaining('otp') / 60);
            $_SESSION['otp_error'] = "Too many wrong attempts. Wait {$wait} minute(s).";
            $this->redirect('/verify-otp');
        }

        $otpInput = $request->input('otp', []);
        $otp = is_array($otpInput) ? (int)implode('', $otpInput) : (int)$otpInput;

        $result = verifyOTP($userId, $otp);

        if ($result === 'ok') {
            $rl->reset('otp');
            unset($_SESSION['pending_user_id']);
            $_SESSION['login_success'] = 'Email successfully verified! Please log in.';
            $this->redirect('/login');
        } elseif ($result === 'expired') {
            $_SESSION['otp_error'] = 'OTP has expired. Please register again.';
            $this->redirect('/verify-otp');
        } else {
            $rl->recordFailure('otp', 5, 10, 30);
            $_SESSION['otp_error'] = 'Invalid OTP. Please check your code and try again.';
            $this->redirect('/verify-otp');
        }
    }

    public function showForgotPassword(Request $request): void
    {
        $this->render('auth/forgot-password', [
            'error'   => $_SESSION['forgot_error'] ?? '',
            'success' => $_SESSION['forgot_success'] ?? '',
        ], null);
        unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
    }

    public function forgotPassword(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['forgot_error'] = 'Session expired.';
            $this->redirect('/forgot-password');
        }

        $db = Model::getDb();
        $rl = new \RateLimiter($db);

        if ($rl->isBlocked('forgot')) {
            $wait = ceil($rl->blockedSecondsRemaining('forgot') / 60);
            $_SESSION['forgot_error'] = "Too many requests. Please wait {$wait} minute(s).";
            $this->redirect('/forgot-password');
        }

        $email = strtolower(trim($request->input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isFakeEmail($email)) {
            $_SESSION['forgot_error'] = 'Please enter a valid email address.';
            $this->redirect('/forgot-password');
        }

        $user = User::findByEmail($email);
        if ($user && $user['is_verified']) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            User::setResetToken($user['id'], $token, $expires);

            $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = !empty($_ENV['APP_URL']) ? rtrim($_ENV['APP_URL'], '/') : "{$scheme}://{$host}" . (defined('BASE_PATH') ? BASE_PATH : '');
            $resetLink = $baseUrl . '/reset-password?token=' . urlencode($token);

            $htmlBody = "
                <div style='font-family:sans-serif;max-width:480px;margin:auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px'>
                    <h2 style='color:#111827;margin-top:0'>Reset your password</h2>
                    <p style='color:#4b5563;font-size:15px'>Hi <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
                    <p style='color:#4b5563;font-size:14px'>Click below to set a new password (valid for 1 hour):</p>
                    <div style='text-align:center;margin:28px 0'>
                        <a href='{$resetLink}' style='background:#185FA5;color:#ffffff;padding:12px 28px;text-decoration:none;border-radius:6px;font-weight:600;display:inline-block'>Reset Password</a>
                    </div>
                    <p style='color:#6b7280;font-size:13px'>Or copy and paste this link:<br><a href='{$resetLink}' style='color:#185FA5;word-break:break-all'>{$resetLink}</a></p>
                </div>
            ";

            try {
                $mailer = getMailer();
                $mailer->addAddress($email, $user['name']);
                $mailer->Subject = 'Reset your QuizApp password';
                $mailer->Body    = $htmlBody;
                $mailer->AltBody = "Reset your password: {$resetLink}";
                $mailer->send();
            } catch (\Exception $e) {
                error_log('Reset email failed: ' . $e->getMessage());
            }
        }

        $rl->recordFailure('forgot', 5, 10, 15);
        $_SESSION['forgot_success'] = 'If that email is registered, a password reset link has been sent to your inbox.';
        $this->redirect('/forgot-password');
    }

    public function showResetPassword(Request $request): void
    {
        $token = trim($request->input('token', ''));
        $user  = !empty($token) ? User::findByResetToken($token) : null;

        $this->render('auth/reset-password', [
            'token'      => $token,
            'tokenValid' => (bool)$user,
            'error'      => $user ? ($_SESSION['reset_error'] ?? '') : 'This password reset link is invalid or has expired.',
            'success'    => $_SESSION['reset_success'] ?? '',
        ], null);
        unset($_SESSION['reset_error'], $_SESSION['reset_success']);
    }

    public function resetPassword(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['reset_error'] = 'Session expired.';
            $this->back();
        }

        $token    = trim($request->input('token', ''));
        $password = (string)$request->input('password', '');
        $confirm  = (string)$request->input('confirm', '');

        $user = User::findByResetToken($token);
        if (!$user) {
            $_SESSION['reset_error'] = 'This password reset link has expired.';
            $this->redirect('/forgot-password');
        }

        if (strlen($password) < 8) {
            $_SESSION['reset_error'] = 'Password must be at least 8 characters.';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        if ($password !== $confirm) {
            $_SESSION['reset_error'] = 'Passwords do not match.';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        User::updatePassword($user['id'], $hash);

        $_SESSION['login_success'] = 'Password reset successfully! Please log in with your new password.';
        $this->redirect('/login');
    }

    public function logout(): void
    {
        logoutUser();
        $this->redirect('/login');
    }
}
