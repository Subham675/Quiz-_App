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
        $timeLimitSeconds = isset($data['time_limit_seconds']) 
            ? (int)$data['time_limit_seconds'] 
            : (int)($data['time_limit_minutes'] ?? 10) * 60;

        self::query("
            INSERT INTO quizzes (title, description, category_id, time_limit_seconds, negative_marking, starts_at, ends_at, is_ai_generated)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['title'],
            $data['description'] ?? null,
            !empty($data['category_id']) ? (int)$data['category_id'] : null,
            $timeLimitSeconds > 0 ? $timeLimitSeconds : 600,
            $data['negative_marking'] ?? 0.00,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            !empty($data['is_ai_generated']) ? 1 : 0,
        ]);
        return self::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $timeLimitSeconds = isset($data['time_limit_seconds']) 
            ? (int)$data['time_limit_seconds'] 
            : (int)($data['time_limit_minutes'] ?? 10) * 60;

        self::query("
            UPDATE quizzes
            SET title = ?, description = ?, category_id = ?, time_limit_seconds = ?, negative_marking = ?, starts_at = ?, ends_at = ?
            WHERE id = ?
        ", [
            $data['title'],
            $data['description'] ?? null,
            !empty($data['category_id']) ? (int)$data['category_id'] : null,
            $timeLimitSeconds > 0 ? $timeLimitSeconds : 600,
            $data['negative_marking'] ?? 0.00,
            $data['starts_at'] ?? null,
            $data['ends_at'] ?? null,
            $id
        ]);
    }

    public static function softDelete(int $id): void
    {
        self::query("UPDATE quizzes SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function findByTitle(string $title): ?array
    {
        return self::fetchOne("
            SELECT * FROM quizzes
            WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) AND deleted_at IS NULL
            LIMIT 1
        ", [$title]);
    }

    public static function recalculateTotalMarks(int $quizId): void
    {
        self::query("
            UPDATE quizzes
            SET total_marks = (SELECT COALESCE(SUM(marks), 0) FROM questions WHERE quiz_id = ? AND deleted_at IS NULL)
            WHERE id = ?
        ", [$quizId, $quizId]);
    }
}
