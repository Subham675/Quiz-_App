<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\Attempt;
use App\Models\Category;
use App\Models\Certificate;

class QuizController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $catSlug = $request->input('category');
        $search  = trim($request->input('search', ''));

        $sql = "
            SELECT q.*, c.name AS category_name, c.slug AS category_slug,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS question_count,
                   (SELECT id FROM attempts WHERE quiz_id = q.id AND user_id = ? AND is_completed = 1 ORDER BY submitted_at DESC LIMIT 1) AS attempt_id
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.deleted_at IS NULL
              AND (q.starts_at IS NULL OR q.starts_at <= NOW())
              AND (q.ends_at   IS NULL OR q.ends_at   >= NOW())
        ";
        $params = [$userId];

        if (!empty($catSlug)) {
            $sql .= " AND c.slug = ?";
            $params[] = $catSlug;
        }

        if (!empty($search)) {
            $sql .= " AND (q.title LIKE ? OR q.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $sql .= " ORDER BY q.created_at DESC";

        $quizzes = Model::fetchAll($sql, $params);
        $categories = Category::all();

        $this->render('quiz/list', [
            'pageTitle'  => 'Browse Quizzes',
            'activeNav'  => 'quizzes',
            'quizzes'    => $quizzes,
            'categories' => $categories,
            'selectedCategory' => $catSlug,
            'search'     => $search,
        ], 'main');
    }

    public function take(Request $request, string $id): void
    {
        $quizId = (int)$id;
        $userId = $_SESSION['user_id'];

        $quiz = Quiz::findById($quizId);
        if (!$quiz) {
            $this->redirect('/quizzes');
        }

        // Check if already completed (single attempt rule)
        $completedAttempt = Model::fetchOne("
            SELECT id FROM attempts 
            WHERE user_id = ? AND quiz_id = ? AND is_completed = 1 
            ORDER BY submitted_at DESC LIMIT 1
        ", [$userId, $quizId]);

        if ($completedAttempt) {
            $this->redirect('/quiz/result/' . $completedAttempt['id'] . '?already=1');
        }

        // Get or start active attempt
        $attempt = Attempt::getActive($userId, $quizId);
        if (!$attempt) {
            $attemptId = Attempt::start($userId, $quizId);
            $attempt = Attempt::findById($attemptId);
        } else {
            $attemptId = (int)$attempt['id'];
        }

        $questions = Question::getByQuizId($quizId);
        if (empty($questions)) {
            $_SESSION['quiz_error'] = 'This quiz does not have any questions yet.';
            $this->redirect('/quizzes');
        }

        // Timer calculation
        $timeLimitSeconds = ((int)$quiz['time_limit_minutes']) * 60;
        $serverStartTime = strtotime($attempt['started_at'] ?? 'now');
        $serverElapsed = max(0, time() - $serverStartTime);
        $remainingSeconds = max(0, $timeLimitSeconds - $serverElapsed);

        $this->render('quiz/take', [
            'pageTitle'        => $quiz['title'] . ' — Taking Quiz',
            'quiz'             => $quiz,
            'attempt'          => $attempt,
            'attemptId'        => $attemptId,
            'questions'        => $questions,
            'remainingSeconds' => $remainingSeconds,
            'timeLimitSeconds' => $timeLimitSeconds,
        ], null); // Take quiz has full-screen layout
    }

    public function pingTabSwitch(Request $request, string $id): void
    {
        $attemptId = (int)$id;
        $userId = $_SESSION['user_id'];

        Model::query("
            UPDATE attempts 
            SET tab_switch_count = tab_switch_count + 1 
            WHERE id = ? AND user_id = ? AND is_completed = 0
        ", [$attemptId, $userId]);

        $this->json(['status' => 'ok']);
    }

    public function submit(Request $request, string $id): void
    {
        $attemptId = (int)$id;
        $userId = $_SESSION['user_id'];

        if (!$request->verifyCsrf()) {
            $this->redirect('/quizzes');
        }

        $attempt = Attempt::findById($attemptId, $userId);
        if (!$attempt || !empty($attempt['is_completed'])) {
            $this->redirect('/quiz/result/' . $attemptId);
        }

        $quizId = (int)$attempt['quiz_id'];
        $quiz = Quiz::findById($quizId);
        $questions = Question::getByQuizId($quizId);

        $timeLimit = ((int)$quiz['time_limit_minutes']) * 60;
        $serverStartTime = strtotime($attempt['started_at'] ?? 'now');
        $serverElapsed = max(1, time() - $serverStartTime);
        $timedOut = ($serverElapsed > ($timeLimit + 15));
        $finalTimeTaken = min($serverElapsed, $timeLimit);

        $answers = $request->input('answers', []);
        $negativeMarking = (float)($quiz['negative_marking'] ?? 0.00);

        $rawScore = 0.0;
        $deductions = 0.0;
        $totalMarks = 0;

        $db = Model::getDb();
        $db->beginTransaction();

        $ansStmt = $db->prepare("
            INSERT INTO attempt_answers (attempt_id, question_id, selected_option_id, is_correct)
            VALUES (?, ?, ?, ?)
        ");

        $updStats = $db->prepare("
            UPDATE questions 
            SET times_attempted = times_attempted + 1, times_correct = times_correct + ?
            WHERE id = ?
        ");

        foreach ($questions as $q) {
            $totalMarks += $q['marks'];
            $selectedId = isset($answers[$q['id']]) ? (int)$answers[$q['id']] : null;

            $isCorrect = false;
            if ($selectedId) {
                $check = $db->prepare("SELECT is_correct FROM options WHERE id = ? AND question_id = ?");
                $check->execute([$selectedId, $q['id']]);
                $isCorrect = (bool)$check->fetchColumn();
            }

            if ($isCorrect) {
                $rawScore += $q['marks'];
            } elseif ($selectedId && $negativeMarking > 0) {
                $deductions += ($q['marks'] * $negativeMarking);
            }

            $ansStmt->execute([$attemptId, $q['id'], $selectedId ?: null, $isCorrect ? 1 : 0]);
            $updStats->execute([$isCorrect ? 1 : 0, $q['id']]);
        }

        $finalScore = max(0, $rawScore - $deductions);

        $db->prepare("
            UPDATE attempts
            SET score = ?, total_marks = ?, time_taken_seconds = ?, is_completed = 1, submitted_at = NOW()
            WHERE id = ?
        ")->execute([$finalScore, $totalMarks, $finalTimeTaken, $attemptId]);

        $db->commit();

        $this->redirect('/quiz/result/' . $attemptId . ($timedOut ? '?timeout=1' : ''));
    }

    public function result(Request $request, string $attemptId): void
    {
        $id = (int)$attemptId;
        $userId = $_SESSION['user_id'];

        $attempt = Attempt::findById($id, $userId);
        if (!$attempt) {
            $this->redirect('/my-attempts');
        }

        $pct = $attempt['total_marks'] > 0 ? round($attempt['score'] * 100 / $attempt['total_marks']) : 0;
        $passed = ($pct >= Certificate::PASS_PERCENT);

        $certificate = null;
        if ($passed) {
            $certificate = Certificate::generateIfEligible($id);
        }

        $details = Attempt::getAnswers($id);

        $this->render('quiz/result', [
            'pageTitle'    => 'Quiz Result — ' . $attempt['quiz_title'],
            'attempt'      => $attempt,
            'details'      => $details,
            'pct'          => $pct,
            'passed'       => $passed,
            'certificate'  => $certificate,
            'timedOut'     => (bool)$request->input('timeout'),
            'already'      => (bool)$request->input('already'),
        ], 'main');
    }

    public function attempts(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $history = Attempt::getUserHistory($userId);

        $this->render('quiz/my-attempts', [
            'pageTitle' => 'My Attempts',
            'activeNav' => 'my-attempts',
            'attempts'  => $history,
        ], 'main');
    }
}
