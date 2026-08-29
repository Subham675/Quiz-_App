<?php
namespace App\Models;

use App\Core\Model;

class Question extends Model
{
    public static function getByQuizId(int $quizId, bool $includeDeleted = false): array
    {
        $sql = "
            SELECT * FROM questions
            WHERE quiz_id = ? " . ($includeDeleted ? "" : "AND deleted_at IS NULL") . "
            ORDER BY order_index ASC, id ASC
        ";
        $questions = self::fetchAll($sql, [$quizId]);

        foreach ($questions as &$q) {
            $q['options'] = self::fetchAll("SELECT * FROM options WHERE question_id = ? ORDER BY id ASC", [$q['id']]);
        }
        return $questions;
    }

    public static function findById(int $id): ?array
    {
        $q = self::fetchOne("SELECT * FROM questions WHERE id = ? AND deleted_at IS NULL", [$id]);
        if ($q) {
            $q['options'] = self::fetchAll("SELECT * FROM options WHERE question_id = ? ORDER BY id ASC", [$id]);
        }
        return $q;
    }

    public static function create(int $quizId, string $text, int $marks = 1, string $difficulty = 'medium', ?string $tag = null, int $orderIndex = 0): int
    {
        self::query("
            INSERT INTO questions (quiz_id, question_text, marks, difficulty, tag, order_index)
            VALUES (?, ?, ?, ?, ?, ?)
        ", [$quizId, $text, $marks, $difficulty, $tag, $orderIndex]);
        return self::lastInsertId();
    }

    public static function saveOption(int $questionId, string $text, bool $isCorrect): int
    {
        self::query("INSERT INTO options (question_id, option_text, is_correct) VALUES (?, ?, ?)", [$questionId, $text, $isCorrect ? 1 : 0]);
        return self::lastInsertId();
    }

    public static function deleteOptions(int $questionId): void
    {
        self::query("DELETE FROM options WHERE question_id = ?", [$questionId]);
    }

    public static function softDelete(int $id): void
    {
        self::query("UPDATE questions SET deleted_at = NOW() WHERE id = ?", [$id]);
    }
}
