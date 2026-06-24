<?php
/**
 * IP-based rate limiter using the database.
 * Blocks an IP for $blockMinutes after $maxAttempts within $windowMinutes.
 *
 * Usage:
 *   $rl = new RateLimiter(getDB());
 *
 *   if ($rl->isBlocked('login')) {
 *       $remaining = $rl->blockedSecondsRemaining('login');
 *       die("Too many attempts. Try again in {$remaining} seconds.");
 *   }
 *
 *   if (loginFailed()) {
 *       $rl->recordFailure('login');
 *   } else {
 *       $rl->reset('login'); // clear on success
 *   }
 */

class RateLimiter
{
    private PDO $db;
    private string $ip;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ip = $this->getRealIp();
    }

    private function getRealIp(): string
    {
        foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Check if this IP is currently blocked for a given action.
     */
    public function isBlocked(string $action): bool
    {
        $stmt = $this->db->prepare("
            SELECT blocked_until FROM rate_limits
            WHERE ip = ? AND action = ? AND blocked_until IS NOT NULL AND blocked_until > NOW()
        ");
        $stmt->execute([$this->ip, $action]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * How many seconds until the block expires (0 if not blocked).
     */
    public function blockedSecondsRemaining(string $action): int
    {
        $stmt = $this->db->prepare("
            SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until)
            FROM rate_limits WHERE ip = ? AND action = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$this->ip, $action]);
        return max(0, (int)$stmt->fetchColumn());
    }

    /**
     * Record a failed attempt. Blocks the IP if $maxAttempts is exceeded.
     *
     * @param string $action        e.g. 'login', 'register', 'otp'
     * @param int    $maxAttempts   block after this many attempts
     * @param int    $windowMinutes count attempts within this window
     * @param int    $blockMinutes  block for this long once limit hit
     */
    public function recordFailure(
        string $action,
        int $maxAttempts  = 5,
        int $windowMinutes = 10,
        int $blockMinutes  = 15
    ): void {
        // Clear old attempts outside the window first
        $this->db->prepare("
            UPDATE rate_limits
            SET attempts = 0, blocked_until = NULL
            WHERE ip = ? AND action = ?
              AND blocked_until IS NULL
              AND last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ")->execute([$this->ip, $action, $windowMinutes]);

        // Upsert attempt counter
        $this->db->prepare("
            INSERT INTO rate_limits (ip, action, attempts, last_attempt)
            VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                attempts = attempts + 1,
                last_attempt = NOW()
        ")->execute([$this->ip, $action]);

        // Get current count
        $stmt = $this->db->prepare("SELECT attempts FROM rate_limits WHERE ip = ? AND action = ?");
        $stmt->execute([$this->ip, $action]);
        $count = (int)$stmt->fetchColumn();

        // Block if over limit
        if ($count >= $maxAttempts) {
            $this->db->prepare("
                UPDATE rate_limits
                SET blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                WHERE ip = ? AND action = ?
            ")->execute([$blockMinutes, $this->ip, $action]);
        }
    }

    /**
     * Reset (clear) the attempt counter for an action on success.
     */
    public function reset(string $action): void
    {
        $this->db->prepare("
            DELETE FROM rate_limits WHERE ip = ? AND action = ?
        ")->execute([$this->ip, $action]);
    }

    public function getIp(): string
    {
        return $this->ip;
    }
}