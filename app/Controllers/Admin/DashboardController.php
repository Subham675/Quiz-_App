<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;

class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $totalUsers    = (int)Model::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'user' AND (is_deleted = 0 OR is_deleted IS NULL)");
        $totalQuizzes  = (int)Model::fetchColumn("SELECT COUNT(*) FROM quizzes WHERE deleted_at IS NULL");
        $totalAttempts = (int)Model::fetchColumn("SELECT COUNT(*) FROM attempts WHERE is_completed = 1");
        $totalCerts    = (int)Model::fetchColumn("SELECT COUNT(*) FROM certificates WHERE MONTH(issued_at) = MONTH(NOW())");

        // Recent attempts
        $recentAttempts = Model::fetchAll("
            SELECT a.*, u.name AS user_name, u.email AS user_email, q.title AS quiz_title
            FROM attempts a
            JOIN users u   ON u.id = a.user_id
            JOIN quizzes q ON q.id = a.quiz_id
            WHERE a.is_completed = 1
            ORDER BY a.submitted_at DESC
            LIMIT 5
        ");

        // Flagged questions
        $flaggedQuestions = Model::fetchAll("
            SELECT q.*, qz.title AS quiz_title
            FROM questions q
            JOIN quizzes qz ON qz.id = q.quiz_id
            WHERE q.is_flagged = 1 AND q.deleted_at IS NULL
            ORDER BY q.id DESC
            LIMIT 5
        ");

        $this->render('admin/index', [
            'pageTitle'        => 'Admin Dashboard',
            'activeNav'        => 'dashboard',
            'totalUsers'       => $totalUsers,
            'totalQuizzes'     => $totalQuizzes,
            'totalAttempts'    => $totalAttempts,
            'totalCerts'       => $totalCerts,
            'recentAttempts'   => $recentAttempts,
            'flaggedQuestions' => $flaggedQuestions,
        ], 'admin');
    }
}
