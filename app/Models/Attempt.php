<?php
namespace App\Models;

use App\Core\Model;

class Attempt extends Model
{
    public static function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "
            SELECT a.*, q.title AS quiz_title, q.id AS quiz_id, q.negative_marking, q.time_limit_minutes,
                   u.name AS user_name, u.email AS user_email
            FROM attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            JOIN users u   ON u.id = a.user_id
            WHERE a.id = ?
        ";
        $params = [$id];
        if ($userId !== null) {
            $sql .= " AND a.user_id = ?";
            $params[] = $userId;
        }
        return self::fetchOne($sql, $params);
    }

    public static function getActive(int $userId, int $quizId): ?array
    {
        return self::fetchOne("
            SELECT * FROM attempts
            WHERE user_id = ? AND quiz_id = ? AND is_completed = 0
            ORDER BY started_at DESC LIMIT 1
        ", [$userId, $quizId]);
    }

    public static function start(int $userId, int $quizId): int
    {
        self::query("
            INSERT INTO attempts (user_id, quiz_id, score, total_marks, time_taken_seconds, tab_switch_count, is_completed, started_at)
            VALUES (?, ?, 0, 0, 0, 0, 0, NOW())
        ", [$userId, $quizId]);
        return self::lastInsertId();
    }

    public static function getAnswers(int $attemptId): array
    {
        return self::fetchAll("
            SELECT q.id AS question_id, q.question_text, q.marks,
                   aa.selected_option_id, aa.is_correct, aa.explanation,
                   o_sel.option_text AS selected_text,
                   o_correct.option_text AS correct_text
            FROM attempt_answers aa
            JOIN questions q ON q.id = aa.question_id
            LEFT JOIN options o_sel     ON o_sel.id = aa.selected_option_id
            LEFT JOIN options o_correct ON o_correct.question_id = q.id AND o_correct.is_correct = 1
            WHERE aa.attempt_id = ?
            ORDER BY q.order_index ASC, q.id ASC
        ", [$attemptId]);
    }

    public static function getUserHistory(int $userId): array
    {
        return self::fetchAll("
            SELECT a.*, q.title AS quiz_title, c.name AS category_name
            FROM attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE a.user_id = ? AND a.is_completed = 1
            ORDER BY a.submitted_at DESC
        ", [$userId]);
    }
}
