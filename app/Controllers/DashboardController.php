<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $db = Model::getDb();

        $streakInfo = updateUserStreak($userId, $db);

        // Quick stats
        $totalAttempts = (int)Model::fetchColumn("SELECT COUNT(*) FROM attempts WHERE user_id = ? AND is_completed = 1", [$userId]);
        $bestScore     = (int)Model::fetchColumn("SELECT MAX(ROUND(score*100/NULLIF(total_marks,0))) FROM attempts WHERE user_id = ? AND is_completed = 1", [$userId]);
        $avgScore      = (int)Model::fetchColumn("SELECT ROUND(AVG(score*100/NULLIF(total_marks,0))) FROM attempts WHERE user_id = ? AND is_completed = 1", [$userId]);
        $totalCerts    = (int)Model::fetchColumn("SELECT COUNT(*) FROM certificates WHERE user_id = ?", [$userId]);

        // Recommended quizzes
        $recommended = Model::fetchAll("
            SELECT q.*, c.name AS category_name,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS question_count
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.deleted_at IS NULL
              AND (q.starts_at IS NULL OR q.starts_at <= NOW())
              AND (q.ends_at   IS NULL OR q.ends_at   >= NOW())
              AND q.id NOT IN (SELECT quiz_id FROM attempts WHERE user_id = ? AND is_completed = 1)
            ORDER BY q.created_at DESC
            LIMIT 4
        ", [$userId]);

        // Recent attempts
        $recentAttempts = Model::fetchAll("
            SELECT a.*, q.title AS quiz_title, c.name AS category_name
            FROM attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE a.user_id = ? AND a.is_completed = 1
            ORDER BY a.submitted_at DESC
            LIMIT 5
        ", [$userId]);

        $this->render('dashboard/index', [
            'pageTitle'      => 'Student Dashboard',
            'activeNav'      => 'dashboard',
            'streakInfo'     => $streakInfo,
            'totalAttempts'  => $totalAttempts,
            'bestScore'      => $bestScore,
            'avgScore'       => $avgScore,
            'totalCerts'     => $totalCerts,
            'recommended'    => $recommended,
            'recentAttempts' => $recentAttempts,
        ], 'main');
    }
}
