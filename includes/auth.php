<?php
require_once __DIR__ . '/../config/db.php';

// ── Session ──────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,   // auto-enables on HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        // Harden session
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    startSession();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/public/login.php');
        exit;
    }
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: ' . BASE_PATH . '/public/dashboard.php');
        exit;
    }
}

function loginUser(array $user): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
}

function logoutUser(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_PATH . '/public/login.php');
    exit;
}

// ── Email validation ─────────────────────────────────
// Catches typo'd/non-existent domains and known disposable-email
// providers before we waste an OTP send on them. This can NOT confirm
// a specific mailbox (e.g. "abc@gmail.com") really belongs to someone --
// only Gmail itself knows that. Real proof of ownership only ever
// happens when the user types in the OTP we emailed them.
function isFakeEmail(string $email): bool {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return true;
    $domain = strtolower(trim($parts[1]));

    // Domain has no mail server (and no fallback A/AAAA record) configured at all
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A') && !checkdnsrr($domain, 'AAAA')) {
        return true;
    }

    // Known disposable/temp-mail providers
    static $disposable = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'throwawaymail.com', 'yopmail.com', 'trashmail.com',
        'getnada.com', 'fakeinbox.com', 'sharklasers.com', 'maildrop.cc',
        'dispostable.com', 'mintemail.com', 'mailnesia.com',
    ];
    return in_array($domain, $disposable, true);
}

// ── OTP ──────────────────────────────────────────────
function generateOTP(): int {
    return random_int(100000, 999999);
}

function saveOTP(int $userId, int $otp): void {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE users
        SET otp_code = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
            otp_attempts = 0, last_otp_request = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$otp, $userId]);
}

function verifyOTP(int $userId, int $otp): string {
    $db   = getDB();
    $stmt = $db->prepare("SELECT otp_code, otp_expires_at, otp_attempts FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row  = $stmt->fetch();

    if (!$row) return 'invalid';
    if ($row['otp_attempts'] >= 5) return 'max_attempts';
    if (new DateTime() > new DateTime($row['otp_expires_at'])) return 'expired';
    if ((int)$row['otp_code'] !== $otp) {
        $db->prepare("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?")->execute([$userId]);
        return 'wrong';
    }

    $db->prepare("UPDATE users SET is_verified = 1, otp_code = NULL WHERE id = ?")->execute([$userId]);
    return 'ok';
}

// ── CSRF ─────────────────────────────────────────────
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
}

// ── Rate limiting ─────────────────────────────────────
function checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): bool {
    startSession();
    $now   = time();
    $store = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'start' => $now];
    if ($now - $store['start'] > $windowSeconds) {
        $store = ['count' => 0, 'start' => $now];
    }
    $store['count']++;
    $_SESSION['rate_limits'][$key] = $store;
    return $store['count'] <= $maxAttempts;
}