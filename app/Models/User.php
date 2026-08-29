<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    public static function findById(int $id): ?array
    {
        return self::fetchOne("SELECT * FROM users WHERE id = ? AND (is_deleted = 0 OR is_deleted IS NULL)", [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return self::fetchOne("SELECT * FROM users WHERE email = ? AND (is_deleted = 0 OR is_deleted IS NULL)", [strtolower(trim($email))]);
    }

    public static function create(string $name, string $email, string $passwordHash): int
    {
        $db = self::getDb();
        // Find lowest available ID (filling gaps if any)
        $nextId = (int)$db->query("
            SELECT MIN(seq) FROM (
                SELECT 1 AS seq
                UNION ALL
                SELECT id + 1 FROM users
            ) AS candidates
            WHERE seq NOT IN (SELECT id FROM users)
        ")->fetchColumn();

        $stmt = $db->prepare("INSERT INTO users (id, name, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nextId, $name, strtolower(trim($email)), $passwordHash]);
        return $nextId;
    }

    public static function updatePassword(int $userId, string $passwordHash): void
    {
        self::query("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?", [$passwordHash, $userId]);
    }

    public static function setResetToken(int $userId, string $token, string $expires): void
    {
        self::query("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?", [$token, $expires, $userId]);
    }

    public static function findByResetToken(string $token): ?array
    {
        return self::fetchOne("SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() AND (is_deleted = 0 OR is_deleted IS NULL)", [$token]);
    }

    public static function verifyEmail(int $userId): void
    {
        self::query("UPDATE users SET is_verified = 1 WHERE id = ?", [$userId]);
    }

    public static function delete(int $userId): void
    {
        self::query("DELETE FROM users WHERE id = ?", [$userId]);
    }

    public static function softDelete(int $userId): void
    {
        self::query("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ?", [$userId]);
    }

    public static function getAll(int $limit = 100, int $offset = 0): array
    {
        return self::fetchAll("
            SELECT u.*, 
                   (SELECT COUNT(*) FROM attempts WHERE user_id = u.id AND is_completed = 1) AS attempts_count,
                   (SELECT COUNT(*) FROM certificates WHERE user_id = u.id) AS certs_count
            FROM users u
            WHERE u.is_deleted = 0 OR u.is_deleted IS NULL
            ORDER BY u.id DESC
            LIMIT ? OFFSET ?
        ", [$limit, $offset]);
    }
}
