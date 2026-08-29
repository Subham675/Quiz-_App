<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Attempt;
use App\Models\User;

class ReportController extends Controller
{
    public function index(Request $request): void
    {
        $totalAttempts = (int)Model::fetchColumn("SELECT COUNT(*) FROM attempts WHERE is_completed = 1");
        $avgScore      = (float)Model::fetchColumn("SELECT COALESCE(AVG(score * 100 / NULLIF(total_marks,0)), 0) FROM attempts WHERE is_completed = 1");
        $passRate      = (float)Model::fetchColumn("SELECT COALESCE(AVG(CASE WHEN score * 100 / NULLIF(total_marks,0) >= 60 THEN 100 ELSE 0 END), 0) FROM attempts WHERE is_completed = 1");
        $totalCerts    = (int)Model::fetchColumn("SELECT COUNT(*) FROM certificates");

        // Quiz breakdown
        $quizBreakdown = Model::fetchAll("
            SELECT q.id, q.title, c.name AS category_name,
                   COUNT(a.id) AS total_attempts,
                   ROUND(AVG(a.score * 100 / NULLIF(a.total_marks,0))) AS avg_score,
                   ROUND(AVG(CASE WHEN a.score * 100 / NULLIF(a.total_marks,0) >= 60 THEN 100 ELSE 0 END)) AS pass_rate
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            LEFT JOIN attempts a ON a.quiz_id = q.id AND a.is_completed = 1
            WHERE q.deleted_at IS NULL
            GROUP BY q.id
            ORDER BY total_attempts DESC
        ");

        $this->render('admin/reports', [
            'pageTitle'      => 'Reports & Analytics',
            'activeNav'      => 'reports',
            'totalAttempts'  => $totalAttempts,
            'avgScore'       => $avgScore,
            'passRate'       => $passRate,
            'totalCerts'     => $totalCerts,
            'quizBreakdown'  => $quizBreakdown,
        ], 'admin');
    }

    public function student(Request $request, string $id): void
    {
        $userId = (int)$id;
        $student = User::findById($userId);
        if (!$student) {
            $this->redirect('/admin/users');
        }

        $attempts = Model::fetchAll("
            SELECT a.*, q.title AS quiz_title, c.name AS category_name
            FROM attempts a
            JOIN quizzes q ON q.id = a.quiz_id
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE a.user_id = ? AND a.is_completed = 1
            ORDER BY a.submitted_at DESC
        ", [$userId]);

        $certCount = (int)Model::fetchColumn("SELECT COUNT(*) FROM certificates WHERE user_id = ?", [$userId]);

        $this->render('admin/student-report', [
            'pageTitle' => 'Student Report — ' . $student['name'],
            'activeNav' => 'users',
            'student'   => $student,
            'attempts'  => $attempts,
            'certCount' => $certCount,
        ], 'admin');
    }

    public function attempt(Request $request, string $id): void
    {
        $attemptId = (int)$id;
        $attempt = Attempt::findById($attemptId);
        if (!$attempt) {
            $this->redirect('/admin/reports');
        }

        $details = Attempt::getAnswers($attemptId);

        $this->render('admin/attempt-detail', [
            'pageTitle' => 'Attempt Details #' . $attemptId,
            'activeNav' => 'reports',
            'attempt'   => $attempt,
            'details'   => $details,
        ], 'admin');
    }
}
