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
        $userId = $_SESSION['user_id'] ?? 0;
        $today = date('Y-m-d');

        $this->render('practice/daily-quiz', [
            'pageTitle' => 'Daily Challenge Quiz',
            'activeNav' => 'daily-quiz',
            'today'     => $today,
        ], 'main');
    }

    public function weakTopics(Request $request): void
    {
        $userId = $_SESSION['user_id'] ?? 0;

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
            'initialTopic' => trim((string)$request->input('topic', '')),
        ], 'main');
    }

    public function generateAi(Request $request): void
    {
        require_once __DIR__ . '/../../includes/gemini.php';

        $topic = trim((string)$request->input('topic', ''));
        $count = (int)$request->input('count', 5);
        $count = max(1, min($count, 10));
        $difficulty = (string)$request->input('difficulty', 'medium');
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'medium';
        }

        if ($topic === '') {
            $this->json(['success' => false, 'error' => 'Please enter or select a topic to practice.'], 400);
            return;
        }

        try {
            $questions = generateQuizQuestions($topic, $count, $difficulty);
            $this->json([
                'success'    => true,
                'topic'      => $topic,
                'count'      => count($questions),
                'difficulty' => ucfirst($difficulty),
                'questions'  => $questions,
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error'   => $e->getMessage() ?: 'Could not generate questions. Please try again with a different topic.',
            ], 500);
        }
    }

    public function suggestTopics(Request $request): void
    {
        $query = strtolower(trim((string)$request->input('q', '')));

        $popularTopics = [
            ['name' => 'Photography', 'category' => 'Art & Media', 'icon' => 'bi-camera'],
            ['name' => 'Photosynthesis & Plant Biology', 'category' => 'Science', 'icon' => 'bi-flower1'],
            ['name' => 'Photovoltaic Cells & Solar Energy', 'category' => 'Technology', 'icon' => 'bi-sun'],
            ['name' => 'Python Programming & Data Structures', 'category' => 'Technology', 'icon' => 'bi-code-slash'],
            ['name' => 'Physics: Classical Mechanics & Optics', 'category' => 'Science', 'icon' => 'bi-atom'],
            ['name' => 'Philosophy & Ethical Dilemmas', 'category' => 'Humanities', 'icon' => 'bi-lightbulb'],
            ['name' => 'Psychology: Cognitive Science & Memory', 'category' => 'Social Sciences', 'icon' => 'bi-brain'],
            ['name' => 'World History & Ancient Civilizations', 'category' => 'History', 'icon' => 'bi-bank'],
            ['name' => 'Indian Constitution & Fundamental Rights', 'category' => 'Law & Polity', 'icon' => 'bi-shield-check'],
            ['name' => 'Machine Learning & Neural Networks', 'category' => 'AI & Technology', 'icon' => 'bi-cpu'],
            ['name' => 'Organic Chemistry & Chemical Reactions', 'category' => 'Science', 'icon' => 'bi-droplet-half'],
            ['name' => 'Astronomy & Black Holes', 'category' => 'Space', 'icon' => 'bi-moon-stars'],
            ['name' => 'Economics: Inflation & Stock Markets', 'category' => 'Business', 'icon' => 'bi-graph-up-arrow'],
            ['name' => 'World Geography: Capitals & Mountain Ranges', 'category' => 'Geography', 'icon' => 'bi-globe-americas'],
            ['name' => 'Environmental Science & Climate Change', 'category' => 'Ecology', 'icon' => 'bi-tree'],
            ['name' => 'Medicine & Human Anatomy', 'category' => 'Health', 'icon' => 'bi-heart-pulse'],
            ['name' => 'Sports & Olympic Records', 'category' => 'Sports', 'icon' => 'bi-trophy'],
            ['name' => 'Cybersecurity & Cryptography', 'category' => 'Technology', 'icon' => 'bi-lock'],
            ['name' => 'English Literature & Famous Authors', 'category' => 'Literature', 'icon' => 'bi-book'],
            ['name' => 'Music Theory & Classical Composers', 'category' => 'Art & Music', 'icon' => 'bi-music-note-beamed'],
            ['name' => 'SQL Databases & Query Optimization', 'category' => 'Technology', 'icon' => 'bi-database'],
            ['name' => 'World War II & 20th Century Politics', 'category' => 'History', 'icon' => 'bi-flag'],
            ['name' => 'Cellular Biology & Genetics', 'category' => 'Science', 'icon' => 'bi-virus'],
            ['name' => 'Macroeconomics & Global Trade', 'category' => 'Economics', 'icon' => 'bi-currency-dollar'],
            ['name' => 'Web Development (HTML, CSS, JavaScript)', 'category' => 'Technology', 'icon' => 'bi-window'],
        ];

        // Also fetch any dynamic categories from database
        try {
            $dbCategories = Category::all();
            foreach ($dbCategories as $cat) {
                $popularTopics[] = [
                    'name' => $cat['name'],
                    'category' => 'Quiz Category',
                    'icon' => 'bi-folder',
                ];
            }
        } catch (\Exception $e) {}

        if ($query === '') {
            $this->json(['suggestions' => array_slice($popularTopics, 0, 8)]);
            return;
        }

        $filtered = [];
        foreach ($popularTopics as $item) {
            if (stripos($item['name'], $query) !== false || stripos($item['category'], $query) !== false) {
                $filtered[] = $item;
            }
        }

        // De-duplicate by name
        $seen = [];
        $unique = [];
        foreach ($filtered as $item) {
            $key = strtolower($item['name']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        $this->json(['suggestions' => array_slice($unique, 0, 8)]);
    }
}
