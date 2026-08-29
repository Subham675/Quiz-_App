<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Quiz;
use App\Models\Category;

class QuizController extends Controller
{
    public function index(Request $request): void
    {
        $quizzes = Model::fetchAll("
            SELECT q.*, c.name AS category_name,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND deleted_at IS NULL) AS question_count,
                   (SELECT COUNT(*) FROM attempts WHERE quiz_id = q.id AND is_completed = 1) AS attempt_count
            FROM quizzes q
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE q.deleted_at IS NULL
            ORDER BY q.created_at DESC
        ");

        $categories = Category::all();

        $this->render('admin/manage-quizzes', [
            'pageTitle'  => 'Manage Quizzes',
            'activeNav'  => 'quizzes',
            'quizzes'    => $quizzes,
            'categories' => $categories,
            'error'      => $_SESSION['admin_quiz_error'] ?? '',
            'success'    => $_SESSION['admin_quiz_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_quiz_error'], $_SESSION['admin_quiz_success']);
    }

    public function create(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_quiz_error'] = 'Session expired.';
            $this->redirect('/admin/quizzes');
        }

        $title = strip_tags(trim($request->input('title', '')));
        $description = trim($request->input('description', ''));
        $categoryId = (int)$request->input('category_id') ?: null;
        $timeLimit = max(1, (int)$request->input('time_limit_minutes', 10));
        $negativeMarking = (float)$request->input('negative_marking', 0.00);
        $startsAt = $request->input('starts_at') ?: null;
        $endsAt = $request->input('ends_at') ?: null;

        if (empty($title)) {
            $_SESSION['admin_quiz_error'] = 'Quiz title is required.';
            $this->redirect('/admin/quizzes');
        }

        Quiz::create([
            'title'              => $title,
            'description'        => $description,
            'category_id'        => $categoryId,
            'time_limit_minutes' => $timeLimit,
            'negative_marking'   => $negativeMarking,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
        ]);

        $_SESSION['admin_quiz_success'] = 'Quiz created successfully!';
        $this->redirect('/admin/quizzes');
    }

    public function update(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_quiz_error'] = 'Session expired.';
            $this->redirect('/admin/quizzes');
        }

        $quizId = (int)$id;
        $title = strip_tags(trim($request->input('title', '')));
        $description = trim($request->input('description', ''));
        $categoryId = (int)$request->input('category_id') ?: null;
        $timeLimit = max(1, (int)$request->input('time_limit_minutes', 10));
        $negativeMarking = (float)$request->input('negative_marking', 0.00);
        $startsAt = $request->input('starts_at') ?: null;
        $endsAt = $request->input('ends_at') ?: null;

        if (empty($title)) {
            $_SESSION['admin_quiz_error'] = 'Quiz title is required.';
            $this->redirect('/admin/quizzes');
        }

        Quiz::update($quizId, [
            'title'              => $title,
            'description'        => $description,
            'category_id'        => $categoryId,
            'time_limit_minutes' => $timeLimit,
            'negative_marking'   => $negativeMarking,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
        ]);

        $_SESSION['admin_quiz_success'] = 'Quiz updated successfully!';
        $this->redirect('/admin/quizzes');
    }

    public function delete(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_quiz_error'] = 'Session expired.';
            $this->redirect('/admin/quizzes');
        }

        $quizId = (int)$id;
        Quiz::softDelete($quizId);

        $_SESSION['admin_quiz_success'] = 'Quiz deleted successfully!';
        $this->redirect('/admin/quizzes');
    }
}
