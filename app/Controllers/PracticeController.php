<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Category;

class PracticeController extends Controller
{
    public function practice(Request $request): void
    {
        $categories = Category::all();
        $this->render('practice/practice', [
            'pageTitle'  => 'Practice Mode',
            'activeNav'  => 'practice',
            'categories' => $categories,
        ], 'main');
    }

    public function daily(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $today = date('Y-m-d');

        $this->render('practice/daily-quiz', [
            'pageTitle' => 'Daily Challenge Quiz',
            'activeNav' => 'daily-quiz',
            'today'     => $today,
        ], 'main');
    }

    public function weakTopics(Request $request): void
    {
        $userId = $_SESSION['user_id'];

        $weakTopics = Model::fetchAll("
            SELECT q.tag, c.name AS category_name,
                   COUNT(aa.id) AS times_attempted,
                   SUM(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END) AS times_correct,
                   ROUND(SUM(CASE WHEN aa.is_correct = 1 THEN 1 ELSE 0 END) * 100 / COUNT(aa.id)) AS accuracy
            FROM attempt_answers aa
            JOIN attempts a ON a.id = aa.attempt_id
            JOIN questions q ON q.id = aa.question_id
            JOIN quizzes qz ON qz.id = a.quiz_id
            LEFT JOIN categories c ON c.id = qz.category_id
            WHERE a.user_id = ? AND a.is_completed = 1 AND q.tag IS NOT NULL AND q.tag != ''
            GROUP BY q.tag, c.name
            HAVING accuracy < 70
            ORDER BY accuracy ASC, times_attempted DESC
            LIMIT 10
        ", [$userId]);

        $this->render('practice/weak-topics', [
            'pageTitle'  => 'Weak Topics Analysis',
            'activeNav'  => 'weak-topics',
            'weakTopics' => $weakTopics,
        ], 'main');
    }

    public function adaptive(Request $request): void
    {
        $this->render('practice/adaptive-quiz', [
            'pageTitle' => 'Adaptive AI Quiz',
            'activeNav' => 'adaptive-quiz',
        ], 'main');
    }

    public function aiPractice(Request $request): void
    {
        $categories = Category::all();
        $this->render('practice/ai-practice', [
            'pageTitle'  => 'AI Practice Generator',
            'activeNav'  => 'ai-practice',
            'categories' => $categories,
        ], 'main');
    }
}
