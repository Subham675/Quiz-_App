<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isAdmin     = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'QuizApp' ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- App overrides -->
    <link rel="stylesheet" href="/Quiz_app/assets/css/style.css?v=5">
</head>
<body>

<!-- Mobile topbar -->
<nav class="navbar navbar-light bg-white border-bottom d-md-none sticky-top">
    <div class="container-fluid">
        <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand mb-0 fw-semibold">QuizApp</span>
        <a href="/Quiz_app/public/logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
</nav>

<!-- Sidebar offcanvas (mobile) -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title fw-semibold mb-0">QuizApp</h5>
            <small class="text-muted"><?= $isAdmin ? 'Admin Panel' : 'Student Panel' ?></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nav flex-column sidebar-nav">
            <?php if ($isAdmin): ?>
            <div class="sidebar-section">Main</div>
            <a href="/Quiz_app/admin/index.php"            class="nav-link <?= $currentPage === 'index'            ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="/Quiz_app/admin/manage-quizzes.php"   class="nav-link <?= $currentPage === 'manage-quizzes'   ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Quizzes</a>
            <a href="/Quiz_app/admin/manage-questions.php" class="nav-link <?= $currentPage === 'manage-questions' ? 'active' : '' ?>"><i class="bi bi-patch-question me-2"></i>Questions</a>
            <a href="/Quiz_app/admin/manage-categories.php"class="nav-link <?= $currentPage === 'manage-categories'? 'active' : '' ?>"><i class="bi bi-tags me-2"></i>Categories</a>
            <div class="sidebar-section">Users</div>
            <a href="/Quiz_app/admin/manage-users.php"     class="nav-link <?= $currentPage === 'manage-users'     ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Users</a>
            <a href="/Quiz_app/admin/reports.php"          class="nav-link <?= $currentPage === 'reports'          ? 'active' : '' ?>"><i class="bi bi-bar-chart me-2"></i>Reports</a>
            <div class="sidebar-section">Tools</div>
            <a href="/Quiz_app/admin/ai-generator.php"     class="nav-link <?= $currentPage === 'ai-generator'     ? 'active' : '' ?>"><i class="bi bi-stars me-2"></i>AI Generator</a>
            <?php else: ?>
            <div class="sidebar-section">Menu</div>
            <a href="/Quiz_app/public/dashboard.php"   class="nav-link <?= $currentPage === 'dashboard'   ? 'active' : '' ?>"><i class="bi bi-house me-2"></i>Dashboard</a>
            <a href="/Quiz_app/public/quiz-list.php"   class="nav-link <?= $currentPage === 'quiz-list'   ? 'active' : '' ?>"><i class="bi bi-collection me-2"></i>Browse Quizzes</a>
            <a href="/Quiz_app/public/my-attempts.php" class="nav-link <?= $currentPage === 'my-attempts' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Attempts</a>
            <a href="/Quiz_app/public/leaderboard.php" class="nav-link <?= $currentPage === 'leaderboard' ? 'active' : '' ?>"><i class="bi bi-trophy me-2"></i>Leaderboard</a>
            <a href="/Quiz_app/public/certificates.php"class="nav-link <?= $currentPage === 'certificates'? 'active' : '' ?>"><i class="bi bi-award me-2"></i>Certificates</a>
            <div class="sidebar-section">Tools</div>
            <a href="/Quiz_app/public/adaptive-quiz.php" class="nav-link <?= $currentPage === 'adaptive-quiz' ? 'active' : '' ?>"><i class="bi bi-bullseye me-2"></i>Adaptive Quiz</a>
            <a href="/Quiz_app/public/ai-practice.php" class="nav-link <?= $currentPage === 'ai-practice' ? 'active' : '' ?>"><i class="bi bi-robot me-2"></i>AI Practice</a>
            <div class="sidebar-section">Account</div>
            <a href="/Quiz_app/public/profile.php"     class="nav-link <?= $currentPage === 'profile'     ? 'active' : '' ?>"><i class="bi bi-person me-2"></i>Profile</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer border-top p-3 mt-auto">
            Logged in as <strong><?= htmlspecialchars($_SESSION['name']) ?></strong><br>
            <?php if (!$isAdmin):
                $db = getDB();
                $sStreak = getUserStreak($_SESSION['user_id'], $db);
            ?>
            <?php if ($sStreak > 0): ?>
                <span class="text-warning small fw-semibold">🔥 <?= $sStreak ?> day<?= $sStreak > 1 ? 's' : '' ?> streak</span><br>
            <?php endif; ?>
            <?php endif; ?>
            <a href="/Quiz_app/public/logout.php" class="text-danger small">Logout</a>
        </div>
    </div>
</div>

<div class="d-flex">

<!-- Desktop sidebar -->
<aside class="sidebar d-none d-md-flex flex-column bg-white border-end" style="width:230px;min-height:100vh;position:sticky;top:0;height:100vh;flex-shrink:0;">
    <div class="p-3 border-bottom">
        <div class="fw-semibold fs-6">QuizApp</div>
        <small class="text-muted"><?= $isAdmin ? 'Admin Panel' : 'Student Panel' ?></small>
    </div>

    <nav class="nav flex-column sidebar-nav flex-grow-1 overflow-auto">
        <?php if ($isAdmin): ?>
        <div class="sidebar-section">Main</div>
        <a href="/Quiz_app/admin/index.php"            class="nav-link <?= $currentPage === 'index'            ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a href="/Quiz_app/admin/manage-quizzes.php"   class="nav-link <?= $currentPage === 'manage-quizzes'   ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Quizzes</a>
        <a href="/Quiz_app/admin/manage-questions.php" class="nav-link <?= $currentPage === 'manage-questions' ? 'active' : '' ?>"><i class="bi bi-patch-question me-2"></i>Questions</a>
        <a href="/Quiz_app/admin/manage-categories.php"class="nav-link <?= $currentPage === 'manage-categories'? 'active' : '' ?>"><i class="bi bi-tags me-2"></i>Categories</a>
        <div class="sidebar-section">Users</div>
        <a href="/Quiz_app/admin/manage-users.php"     class="nav-link <?= $currentPage === 'manage-users'     ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Users</a>
        <a href="/Quiz_app/admin/reports.php"          class="nav-link <?= $currentPage === 'reports'          ? 'active' : '' ?>"><i class="bi bi-bar-chart me-2"></i>Reports</a>
        <div class="sidebar-section">Tools</div>
        <a href="/Quiz_app/admin/ai-generator.php"     class="nav-link <?= $currentPage === 'ai-generator'     ? 'active' : '' ?>"><i class="bi bi-stars me-2"></i>AI Generator</a>
        <?php else: ?>
        <div class="sidebar-section">Menu</div>
        <a href="/Quiz_app/public/dashboard.php"   class="nav-link <?= $currentPage === 'dashboard'   ? 'active' : '' ?>"><i class="bi bi-house me-2"></i>Dashboard</a>
        <a href="/Quiz_app/public/quiz-list.php"   class="nav-link <?= $currentPage === 'quiz-list'   ? 'active' : '' ?>"><i class="bi bi-collection me-2"></i>Browse Quizzes</a>
        <a href="/Quiz_app/public/my-attempts.php" class="nav-link <?= $currentPage === 'my-attempts' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Attempts</a>
        <a href="/Quiz_app/public/leaderboard.php" class="nav-link <?= $currentPage === 'leaderboard' ? 'active' : '' ?>"><i class="bi bi-trophy me-2"></i>Leaderboard</a>
        <a href="/Quiz_app/public/certificates.php"class="nav-link <?= $currentPage === 'certificates'? 'active' : '' ?>"><i class="bi bi-award me-2"></i>Certificates</a>
        <div class="sidebar-section">Tools</div>
        <a href="/Quiz_app/public/adaptive-quiz.php" class="nav-link <?= $currentPage === 'adaptive-quiz' ? 'active' : '' ?>"><i class="bi bi-bullseye me-2"></i>Adaptive Quiz</a>
        <a href="/Quiz_app/public/ai-practice.php" class="nav-link <?= $currentPage === 'ai-practice' ? 'active' : '' ?>"><i class="bi bi-robot me-2"></i>AI Practice</a>
        <div class="sidebar-section">Account</div>
        <a href="/Quiz_app/public/profile.php"     class="nav-link <?= $currentPage === 'profile'     ? 'active' : '' ?>"><i class="bi bi-person me-2"></i>Profile</a>
        <?php endif; ?>
    </nav>

    <div class="border-top p-3 small text-muted">
        Logged in as <strong><?= htmlspecialchars($_SESSION['name']) ?></strong><br>
        <?php if (!$isAdmin):
            if (!isset($db)) $db = getDB();
            if (!isset($sStreak)) $sStreak = getUserStreak($_SESSION['user_id'], $db);
        ?>
        <?php if ($sStreak > 0): ?>
            <span class="text-warning fw-semibold">🔥 <?= $sStreak ?> day<?= $sStreak > 1 ? 's' : '' ?> streak</span><br>
        <?php endif; ?>
        <?php endif; ?>
        <a href="/Quiz_app/public/logout.php" class="text-danger">Logout</a>
    </div>
</aside>

<!-- Main content -->
<main class="flex-grow-1 p-4">