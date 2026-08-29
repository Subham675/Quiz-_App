<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index(Request $request): void
    {
        $categories = Model::fetchAll("
            SELECT c.*, 
                   (SELECT COUNT(*) FROM quizzes WHERE category_id = c.id AND deleted_at IS NULL) AS quiz_count
            FROM categories c
            WHERE c.deleted_at IS NULL
            ORDER BY c.name ASC
        ");

        $this->render('admin/manage-categories', [
            'pageTitle'  => 'Manage Categories',
            'activeNav'  => 'categories',
            'categories' => $categories,
            'error'      => $_SESSION['admin_cat_error'] ?? '',
            'success'    => $_SESSION['admin_cat_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_cat_error'], $_SESSION['admin_cat_success']);
    }

    public function create(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_cat_error'] = 'Session expired.';
            $this->redirect('/admin/categories');
        }

        $name = strip_tags(trim($request->input('name', '')));
        $slug = trim($request->input('slug', ''));
        $description = trim($request->input('description', ''));

        if (empty($name)) {
            $_SESSION['admin_cat_error'] = 'Category name is required.';
            $this->redirect('/admin/categories');
        }

        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        }

        Category::create($name, $slug, $description ?: null);

        $_SESSION['admin_cat_success'] = 'Category created successfully!';
        $this->redirect('/admin/categories');
    }

    public function update(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_cat_error'] = 'Session expired.';
            $this->redirect('/admin/categories');
        }

        $catId = (int)$id;
        $name = strip_tags(trim($request->input('name', '')));
        $slug = trim($request->input('slug', ''));
        $description = trim($request->input('description', ''));

        if (empty($name)) {
            $_SESSION['admin_cat_error'] = 'Category name is required.';
            $this->redirect('/admin/categories');
        }

        Category::update($catId, $name, $slug, $description ?: null);

        $_SESSION['admin_cat_success'] = 'Category updated successfully!';
        $this->redirect('/admin/categories');
    }

    public function delete(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['admin_cat_error'] = 'Session expired.';
            $this->redirect('/admin/categories');
        }

        $catId = (int)$id;
        Category::softDelete($catId);

        $_SESSION['admin_cat_success'] = 'Category deleted successfully!';
        $this->redirect('/admin/categories');
    }
}
