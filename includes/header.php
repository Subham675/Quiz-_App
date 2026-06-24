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
    <link rel="stylesheet" href="/Quiz_app/assets/css/style.css?v=4">
</head>
<body>

<!-- Mobile topbar -->
<header class="topbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-logo">QuizApp</div>
    <a href="/Quiz_app/public/logout.php" class="topbar-logout">Logout</a>
</header>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        QuizApp
        <span><?= $isAdmin ? 'Admin Panel' : 'Student Panel' ?></span>
    </div>

    <?php if ($isAdmin): ?>
    <div class="nav-section">Main</div>
    <a href="/Quiz_app/admin/index.php"            class="nav-item <?= $currentPage === 'index'            ? 'active' : '' ?>">Dashboard</a>
    <a href="/Quiz_app/admin/manage-quizzes.php"   class="nav-item <?= $currentPage === 'manage-quizzes'   ? 'active' : '' ?>">Quizzes</a>
    <a href="/Quiz_app/admin/manage-questions.php" class="nav-item <?= $currentPage === 'manage-questions' ? 'active' : '' ?>">Questions</a>
    <a href="/Quiz_app/admin/manage-categories.php"class="nav-item <?= $currentPage === 'manage-categories'? 'active' : '' ?>">Categories</a>
    <div class="nav-section">Users</div>
    <a href="/Quiz_app/admin/manage-users.php"     class="nav-item <?= $currentPage === 'manage-users'     ? 'active' : '' ?>">Users</a>
    <a href="/Quiz_app/admin/reports.php"          class="nav-item <?= $currentPage === 'reports'          ? 'active' : '' ?>">Reports</a>
    <div class="nav-section">Tools</div>
    <a href="/Quiz_app/admin/ai-generator.php"     class="nav-item <?= $currentPage === 'ai-generator'     ? 'active' : '' ?>">AI Generator</a>

    <?php else: ?>
    <div class="nav-section">Menu</div>
    <a href="/Quiz_app/public/dashboard.php"   class="nav-item <?= $currentPage === 'dashboard'   ? 'active' : '' ?>">Dashboard</a>
    <a href="/Quiz_app/public/quiz-list.php"   class="nav-item <?= $currentPage === 'quiz-list'   ? 'active' : '' ?>">Browse Quizzes</a>
    <a href="/Quiz_app/public/my-attempts.php" class="nav-item <?= $currentPage === 'my-attempts' ? 'active' : '' ?>">My Attempts</a>
    <a href="/Quiz_app/public/leaderboard.php" class="nav-item <?= $currentPage === 'leaderboard' ? 'active' : '' ?>">Leaderboard</a>
    <a href="/Quiz_app/public/certificates.php"class="nav-item <?= $currentPage === 'certificates'? 'active' : '' ?>">Certificates</a>
    <div class="nav-section">Tools</div>
    <a href="/Quiz_app/public/ai-practice.php" class="nav-item <?= $currentPage === 'ai-practice' ? 'active' : '' ?>">AI Practice</a>
    <div class="nav-section">Account</div>
    <a href="/Quiz_app/public/profile.php"     class="nav-item <?= $currentPage === 'profile'     ? 'active' : '' ?>">Profile</a>
    <?php endif; ?>

    <div class="sidebar-footer">
        Logged in as <strong><?= htmlspecialchars($_SESSION['name']) ?></strong><br>
        <a href="/Quiz_app/public/logout.php" style="color:var(--danger);font-size:12px">Logout</a>
    </div>
</aside>

<!-- Main content -->
<main class="main">
<script>
(function(){
    const btn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    function open(){sidebar.classList.add('open');overlay.classList.add('show');document.body.style.overflow='hidden';}
    function close(){sidebar.classList.remove('open');overlay.classList.remove('show');document.body.style.overflow='';}
    if(btn) btn.addEventListener('click', open);
    if(overlay) overlay.addEventListener('click', close);
    sidebar.querySelectorAll('.nav-item').forEach(a => a.addEventListener('click', close));
})();
</script>