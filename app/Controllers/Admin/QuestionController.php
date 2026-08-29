<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Question;
use App\Models\Quiz;

class QuestionController extends Controller
{
    public function index(Request $request): void
    {
        $quizId = (int)$request->input('quiz_id', 0);
        $quizzes = Quiz::allActive();

        $sql = "
            SELECT q.*, qz.title AS quiz_title
            FROM questions q
            JOIN quizzes qz ON qz.id = q.quiz_id
            WHERE q.deleted_at IS NULL
        ";
        $params = [];
        if ($quizId > 0) {
            $sql .= " AND q.quiz_id = ?";
            $params[] = $quizId;
        }
        $sql .= " ORDER BY q.id DESC LIMIT 100";

        $questions = Model::fetchAll($sql, $params);
        foreach ($questions as &$q) {
            $q['options'] = Model::fetchAll("SELECT * FROM options WHERE question_id = ? ORDER BY id ASC", [$q['id']]);
        }

        $this->render('admin/manage-questions', [
            'pageTitle'      => 'Manage Questions',
            'activeNav'      => 'questions',
            'questions'      => $questions,
            'quizzes'        => $quizzes,
            'selectedQuizId' => $quizId,
            'error'          => $_SESSION['admin_q_error'] ?? '',
            'success'        => $_SESSION['admin_q_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_q_error'], $_SESSION['admin_q_success']);
    }

    public function create(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_q_error'] = 'Session expired.';
            $this->redirect('/admin/questions');
        }

        $quizId     = (int)$request->input('quiz_id');
        $text       = trim($request->input('question_text', ''));
        $marks      = max(1, (int)$request->input('marks', 1));
        $difficulty = $request->input('difficulty', 'medium');
        $tag        = trim($request->input('tag', ''));
        $options    = $request->input('options', []);
        $correctOpt = (int)$request->input('correct_option', -1);

        if ($quizId <= 0 || empty($text)) {
            $_SESSION['admin_q_error'] = 'Quiz selection and question text are required.';
            $this->redirect('/admin/questions');
        }

        if (count($options) < 2 || $correctOpt < 0) {
            $_SESSION['admin_q_error'] = 'Please provide at least 2 options and mark one correct.';
            $this->redirect('/admin/questions');
        }

        $qId = Question::create($quizId, $text, $marks, $difficulty, $tag ?: null);

        foreach ($options as $idx => $optText) {
            $optText = trim($optText);
            if ($optText !== '') {
                Question::saveOption($qId, $optText, $idx === $correctOpt);
            }
        }

        $_SESSION['admin_q_success'] = 'Question added successfully!';
        $this->redirect('/admin/questions?quiz_id=' . $quizId);
    }

    public function edit(Request $request, string $id): void
    {
        $qId = (int)$id;
        $question = Question::findById($qId);
        if (!$question) {
            $this->redirect('/admin/questions');
        }

        $quizzes = Quiz::allActive();

        $this->render('admin/edit-question', [
            'pageTitle' => 'Edit Question',
            'activeNav' => 'questions',
            'question'  => $question,
            'quizzes'   => $quizzes,
            'error'     => $_SESSION['admin_q_error'] ?? '',
            'success'   => $_SESSION['admin_q_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_q_error'], $_SESSION['admin_q_success']);
    }

    public function update(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_q_error'] = 'Session expired.';
            $this->redirect('/admin/questions');
        }

        $qId        = (int)$id;
        $quizId     = (int)$request->input('quiz_id');
        $text       = trim($request->input('question_text', ''));
        $marks      = max(1, (int)$request->input('marks', 1));
        $difficulty = $request->input('difficulty', 'medium');
        $tag        = trim($request->input('tag', ''));
        $options    = $request->input('options', []);
        $correctOpt = (int)$request->input('correct_option', -1);

        Model::query("
            UPDATE questions 
            SET quiz_id = ?, question_text = ?, marks = ?, difficulty = ?, tag = ?
            WHERE id = ?
        ", [$quizId, $text, $marks, $difficulty, $tag ?: null, $qId]);

        Question::deleteOptions($qId);

        foreach ($options as $idx => $optText) {
            $optText = trim($optText);
            if ($optText !== '') {
                Question::saveOption($qId, $optText, $idx === $correctOpt);
            }
        }

        $_SESSION['admin_q_success'] = 'Question updated successfully!';
        $this->redirect('/admin/questions?quiz_id=' . $quizId);
    }

    public function delete(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_q_error'] = 'Session expired.';
            $this->redirect('/admin/questions');
        }

        $qId = (int)$id;
        Question::softDelete($qId);

        $_SESSION['admin_q_success'] = 'Question deleted successfully!';
        $this->redirect('/admin/questions');
    }

    public function unflag(Request $request, string $id): void
    {
        $qId = (int)$id;
        Model::query("UPDATE questions SET is_flagged = 0, flag_reason = NULL WHERE id = ?", [$qId]);
        $this->back();
    }
}
