<?php
namespace App\Models;

use App\Core\Model;

class Quiz extends Model
{
    public static function allActive(): array
    {
        return self::fetchAll("
            SELECT q.*, c.name AS category_name,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS question_count
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.deleted_at IS NULL
              AND (q.starts_at IS NULL OR q.starts_at <= NOW())
              AND (q.ends_at   IS NULL OR q.ends_at   >= NOW())
            ORDER BY q.created_at DESC
        ");
    }

    public static function allWithUserAttempt(int $userId): array
    {
        return self::fetchAll("
            SELECT q.*, c.name AS category_name,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS question_count,
                   (SELECT id FROM attempts WHERE quiz_id = q.id AND user_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1) AS attempt_id
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.deleted_at IS NULL
              AND (q.starts_at IS NULL OR q.starts_at <= NOW())
              AND (q.ends_at   IS NULL OR q.ends_at   >= NOW())
            ORDER BY q.created_at DESC
        ", [$userId]);
    }

    public static function findById(int $id): ?array
    {
        return self::fetchOne("
            SELECT q.*, c.name AS category_name
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.id = ? AND q.deleted_at IS NULL
        ", [$id]);
    }

    public static function create(array $data): int
    {
        self::query("
            INSERT INTO quizzes (title, description, category_id, time_limit_minutes, negative_marking, starts_at, ends_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['title'],
            $data['description'] ?? null,
            $data['category_id'] ?: null,
            $data['time_limit_minutes'] ?? 10,
            $data['negative_marking'] ?? 0.00,
            $data['starts_at'] ?: null,
            $data['ends_at'] ?: null,
        ]);
        return self::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        self::query("
            UPDATE quizzes
            SET title = ?, description = ?, category_id = ?, time_limit_minutes = ?, negative_marking = ?, starts_at = ?, ends_at = ?
            WHERE id = ?
        ", [
            $data['title'],
            $data['description'] ?? null,
            $data['category_id'] ?: null,
            $data['time_limit_minutes'] ?? 10,
            $data['negative_marking'] ?? 0.00,
            $data['starts_at'] ?: null,
            $data['ends_at'] ?: null,
            $id
        ]);
    }

    public static function softDelete(int $id): void
    {
        self::query("UPDATE quizzes SET deleted_at = NOW() WHERE id = ?", [$id]);
    }
}
