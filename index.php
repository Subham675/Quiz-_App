<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: /Quiz_app/admin/index.php');
    } else {
        header('Location: /Quiz_app/public/dashboard.php');
    }
} else {
    header('Location: /Quiz_app/public/login.php');
}
exit;
