<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Quiz;
use App\Models\Category;
use App\Models\Question;

class AiGeneratorController extends Controller
{
    public function index(Request $request): void
    {
        $quizzes = Quiz::allActive();
        $categories = Category::all();

        $this->render('admin/ai-generator', [
            'pageTitle'  => 'AI Quiz & Question Generator',
            'activeNav'  => 'ai-generator',
            'quizzes'    => $quizzes,
            'categories' => $categories,
            'error'      => $_SESSION['admin_ai_error'] ?? '',
            'success'    => $_SESSION['admin_ai_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_ai_error'], $_SESSION['admin_ai_success']);
    }

    public function generate(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_ai_error'] = 'Session expired. Please try again.';
            $this->redirect('/admin/ai-generator');
        }

        $quizId     = (int)$request->input('quiz_id', 0);
        $quizTitle  = trim((string)$request->input('quiz_title', ''));
        $topic      = trim((string)$request->input('topic', ''));
        $count      = min(50, max(1, (int)$request->input('count', 5)));
        $difficulty = (string)$request->input('difficulty', 'medium');
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'medium';
        }

        if (empty($topic) || (empty($quizTitle) && $quizId <= 0)) {
            $_SESSION['admin_ai_error'] = 'Please specify a target quiz and enter a topic.';
            $this->redirect('/admin/ai-generator');
        }

        // If quiz_id is not set or title is entered, find or create the quiz
        if ($quizId <= 0 && !empty($quizTitle)) {
            $existingQuiz = Quiz::findByTitle($quizTitle);
            if ($existingQuiz) {
                $quizId = (int)$existingQuiz['id'];
            } else {
                // Find best matching category or fallback to first category
                $categories = Category::all();
                $categoryId = !empty($categories) ? (int)$categories[0]['id'] : 1;

                foreach ($categories as $cat) {
                    if (stripos($quizTitle, $cat['name']) !== false || stripos($topic, $cat['name']) !== false) {
                        $categoryId = (int)$cat['id'];
                        break;
                    }
                }

                $quizId = Quiz::create([
                    'title'              => $quizTitle,
                    'description'        => "AI generated quiz on {$topic}",
                    'category_id'        => $categoryId,
                    'time_limit_minutes' => 10,
                    'negative_marking'   => 0.00,
                    'is_ai_generated'    => 1,
                ]);
            }
        }

        if ($quizId <= 0) {
            $_SESSION['admin_ai_error'] = 'Could not resolve or create the target quiz.';
            $this->redirect('/admin/ai-generator');
        }

        require_once __DIR__ . '/../../../includes/gemini.php';

        try {
            $questions = generateQuizQuestions($topic, $count, $difficulty);
        } catch (\Exception $e) {
            $_SESSION['admin_ai_error'] = 'AI Generation Error: ' . $e->getMessage();
            $this->redirect('/admin/ai-generator');
        }

        if (empty($questions) || !is_array($questions)) {
            $_SESSION['admin_ai_error'] = 'Failed to generate questions. Please try a different topic or fewer questions.';
            $this->redirect('/admin/ai-generator');
        }

        $insertedCount = 0;
        foreach ($questions as $q) {
            if (!empty($q['question']) && !empty($q['options']) && is_array($q['options'])) {
                $qId = Question::create($quizId, $q['question'], (int)($q['marks'] ?? 1), $difficulty, $topic);
                foreach ($q['options'] as $opt) {
                    Question::saveOption($qId, $opt['text'], !empty($opt['correct']));
                }
                $insertedCount++;
            }
        }

        // Update total marks for the quiz
        Quiz::recalculateTotalMarks($quizId);

        $_SESSION['admin_ai_success'] = "Successfully generated and added {$insertedCount} questions to '{$quizTitle}'!";
        $this->redirect('/admin/questions?quiz_id=' . $quizId);
    }

    public function suggestConcepts(Request $request): void
    {
        $topic = trim((string)$request->input('topic', $request->input('q', '')));
        $quiz  = trim((string)$request->input('quiz', ''));

        if ($topic === '') {
            $this->json(['success' => true, 'concepts' => []]);
            return;
        }

        require_once __DIR__ . '/../../../includes/gemini.php';

        $concepts = generateRelatedConcepts($topic, $quiz);

        // Fallback local dictionary if API response is empty
        if (empty($concepts)) {
            $localPool = [
                'javascript' => ['Async/Await & Promises', 'Event Loop & Concurrency', 'Closures & Scope', 'DOM Manipulation', 'ES6 Modules & Syntax', 'Prototypes & Classes'],
                'python'     => ['Lists, Tuples & Dicts', 'List Comprehensions', 'Functions & Decorators', 'OOP & Classes', 'File I/O & Exceptions', 'Generators & Iterators'],
                'physics'    => ['Newton\'s Laws of Motion', 'Optics & Wave Propagation', 'Thermodynamics & Heat', 'Electromagnetism', 'Work, Energy & Power', 'Gravitation & Orbits'],
                'chemistry'  => ['Organic Reaction Mechanisms', 'Periodic Table Trends', 'Chemical Bonding & Hybridization', 'Acids, Bases & pH', 'Stoichiometry & Mole Concept', 'Thermodynamics & Equilibrium'],
                'biology'    => ['Photosynthesis & Respiration', 'Cell Division (Mitosis & Meiosis)', 'DNA Structure & Replication', 'Genetics & Mendel\'s Laws', 'Human Circulatory & Nervous System', 'Ecology & Food Webs'],
                'history'    => ['World War I & II Causes', 'Ancient Civilizations & Artifacts', 'Indian Freedom Struggle (1857-1947)', 'Mughal Empire Administration', 'Cold War Dynamics', 'Industrial Revolution'],
                'polity'     => ['Fundamental Rights & Duties', 'Directive Principles of State Policy', 'Supreme Court Judicial Review', 'Parliamentary Procedures', 'Constitutional Amendments', 'Federal Structure'],
                'math'       => ['Quadratic Equations & Roots', 'Calculus (Derivatives & Integrals)', 'Probability & Permutations', 'Trigonometric Identities', 'Coordinate Geometry', 'Matrices & Determinants']
            ];

            $topicLower = strtolower($topic);
            foreach ($localPool as $key => $items) {
                if (str_contains($topicLower, $key)) {
                    $concepts = $items;
                    break;
                }
            }
        }

        $this->json([
            'success'  => true,
            'topic'    => $topic,
            'concepts' => array_values(array_unique($concepts))
        ]);
    }
}
