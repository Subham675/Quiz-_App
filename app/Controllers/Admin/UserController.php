<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $users = User::getAll();

        $this->render('admin/manage-users', [
            'pageTitle' => 'Manage Users',
            'activeNav' => 'users',
            'users'     => $users,
            'error'     => $_SESSION['admin_user_error'] ?? '',
            'success'   => $_SESSION['admin_user_success'] ?? '',
        ], 'admin');
        unset($_SESSION['admin_user_error'], $_SESSION['admin_user_success']);
    }

    public function toggleBan(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $this->redirect('/admin/users');
        }

        $userId = (int)$id;
        Model::query("UPDATE users SET is_banned = 1 - is_banned WHERE id = ? AND role != 'admin'", [$userId]);

        $_SESSION['admin_user_success'] = 'User status updated.';
        $this->redirect('/admin/users');
    }

    public function delete(Request $request, string $id): void
    {
        if (!$request->verifyCsrf()) {
            $this->redirect('/admin/users');
        }

        $userId = (int)$id;
        User::softDelete($userId);

        $_SESSION['admin_user_success'] = 'User deleted successfully.';
        $this->redirect('/admin/users');
    }
}
