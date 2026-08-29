<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;

class LeaderboardController extends Controller
{
    public function index(Request $request): void
    {
        $timeframe = $request->input('timeframe', 'all');
        $timeFilter = "";
        if ($timeframe === 'month') {
            $timeFilter = "AND a.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        } elseif ($timeframe === 'week') {
            $timeFilter = "AND a.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }

        $leaders = Model::fetchAll("
            SELECT u.id, u.name, u.email,
                   COUNT(a.id) AS total_quizzes,
                   SUM(a.score) AS total_score,
                   SUM(a.total_marks) AS max_marks,
                   ROUND(AVG(a.score * 100 / NULLIF(a.total_marks,0))) AS avg_pct,
                   (SELECT COUNT(*) FROM certificates WHERE user_id = u.id) AS certs
            FROM users u
            JOIN attempts a ON a.user_id = u.id AND a.is_completed = 1 {$timeFilter}
            WHERE u.is_deleted = 0 OR u.is_deleted IS NULL
            GROUP BY u.id
            HAVING total_quizzes > 0
            ORDER BY total_score DESC, avg_pct DESC
            LIMIT 50
        ");

        $this->render('leaderboard/index', [
            'pageTitle' => 'Student Leaderboard',
            'activeNav' => 'leaderboard',
            'leaders'   => $leaders,
            'timeframe' => $timeframe,
        ], 'main');
    }
}
