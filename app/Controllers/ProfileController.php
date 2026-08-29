<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Model;
use App\Models\User;

class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $user = User::findById($userId);

        $stats = Model::fetchOne("
            SELECT COUNT(*) AS total_attempts,
                   MAX(ROUND(score * 100 / NULLIF(total_marks,0))) AS best_score,
                   ROUND(AVG(score * 100 / NULLIF(total_marks,0))) AS avg_score
            FROM attempts 
            WHERE user_id = ? AND is_completed = 1
        ", [$userId]);

        $certCount = (int)Model::fetchColumn("SELECT COUNT(*) FROM certificates WHERE user_id = ?", [$userId]);

        $this->render('profile/index', [
            'pageTitle' => 'My Profile',
            'activeNav' => 'profile',
            'user'      => $user,
            'stats'     => $stats,
            'certCount' => $certCount,
            'error'     => $_SESSION['profile_error'] ?? '',
            'success'   => $_SESSION['profile_success'] ?? '',
        ], 'main');
        unset($_SESSION['profile_error'], $_SESSION['profile_success']);
    }

    public function update(Request $request): void
    {
        if (!$request->verifyCsrf()) {
            $_SESSION['profile_error'] = 'Session expired.';
            $this->redirect('/profile');
        }

        $userId = $_SESSION['user_id'];
        $name = strip_tags(trim($request->input('name', '')));
        $currentPass = (string)$request->input('current_password', '');
        $newPass     = (string)$request->input('new_password', '');
        $confirmPass = (string)$request->input('confirm_password', '');

        if (empty($name)) {
            $_SESSION['profile_error'] = 'Name cannot be empty.';
            $this->redirect('/profile');
        }

        Model::query("UPDATE users SET name = ? WHERE id = ?", [$name, $userId]);
        $_SESSION['name'] = $name;

        // If changing password
        if (!empty($currentPass) || !empty($newPass)) {
            $user = User::findById($userId);
            if (!password_verify($currentPass, $user['password'])) {
                $_SESSION['profile_error'] = 'Current password is incorrect.';
                $this->redirect('/profile');
            }

            if (strlen($newPass) < 8) {
                $_SESSION['profile_error'] = 'New password must be at least 8 characters.';
                $this->redirect('/profile');
            }

            if ($newPass !== $confirmPass) {
                $_SESSION['profile_error'] = 'New passwords do not match.';
                $this->redirect('/profile');
            }

            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            User::updatePassword($userId, $hash);
        }

        $_SESSION['profile_success'] = 'Profile updated successfully!';
        $this->redirect('/profile');
    }
}
