<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'QuizApp') ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=6">
</head>
<body>

<!-- Mobile topbar -->
<nav class="navbar navbar-light bg-white border-bottom d-md-none sticky-top">
    <div class="container-fluid">
        <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand mb-0 fw-semibold">QuizApp</span>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/logout" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
</nav>

<!-- Sidebar offcanvas (mobile) -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title fw-semibold mb-0">QuizApp</h5>
            <small class="text-muted">Student Panel</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nav flex-column sidebar-nav">
            <div class="sidebar-section">Menu</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/dashboard" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-house me-2"></i>Dashboard</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="nav-link <?= ($activeNav ?? '') === 'quizzes' ? 'active' : '' ?>"><i class="bi bi-collection me-2"></i>Browse Quizzes</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/my-attempts" class="nav-link <?= ($activeNav ?? '') === 'my-attempts' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Attempts</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/leaderboard" class="nav-link <?= ($activeNav ?? '') === 'leaderboard' ? 'active' : '' ?>"><i class="bi bi-trophy me-2"></i>Leaderboard</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/certificates" class="nav-link <?= ($activeNav ?? '') === 'certificates' ? 'active' : '' ?>"><i class="bi bi-award me-2"></i>Certificates</a>
            <div class="sidebar-section">Tools</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="nav-link <?= ($activeNav ?? '') === 'practice' ? 'active' : '' ?>"><i class="bi bi-controller me-2"></i>Practice Mode</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/daily-quiz" class="nav-link <?= ($activeNav ?? '') === 'daily-quiz' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>Daily Quiz</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/weak-topics" class="nav-link <?= ($activeNav ?? '') === 'weak-topics' ? 'active' : '' ?>"><i class="bi bi-lightning-charge me-2"></i>Weak Topics</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/adaptive-quiz" class="nav-link <?= ($activeNav ?? '') === 'adaptive-quiz' ? 'active' : '' ?>"><i class="bi bi-bullseye me-2"></i>Adaptive Quiz</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/ai-practice" class="nav-link <?= ($activeNav ?? '') === 'ai-practice' ? 'active' : '' ?>"><i class="bi bi-robot me-2"></i>AI Practice</a>
            <div class="sidebar-section">Account</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/profile" class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>"><i class="bi bi-person me-2"></i>Profile</a>
        </nav>
        <div class="sidebar-footer border-top p-3 mt-auto">
            Logged in as <strong><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></strong><br>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/logout" class="text-danger small">Logout</a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Desktop Sidebar -->
        <aside class="col-md-3 col-lg-2 d-none d-md-flex flex-column sidebar p-0">
            <div class="sidebar-brand">
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/dashboard" class="text-decoration-none text-dark fw-bold fs-5">QuizApp</a>
            </div>
            <nav class="nav flex-column sidebar-nav flex-grow-1">
                <div class="sidebar-section">Menu</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/dashboard" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-house me-2"></i>Dashboard</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/quizzes" class="nav-link <?= ($activeNav ?? '') === 'quizzes' ? 'active' : '' ?>"><i class="bi bi-collection me-2"></i>Browse Quizzes</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/my-attempts" class="nav-link <?= ($activeNav ?? '') === 'my-attempts' ? 'active' : '' ?>"><i class="bi bi-clock-history me-2"></i>My Attempts</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/leaderboard" class="nav-link <?= ($activeNav ?? '') === 'leaderboard' ? 'active' : '' ?>"><i class="bi bi-trophy me-2"></i>Leaderboard</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/certificates" class="nav-link <?= ($activeNav ?? '') === 'certificates' ? 'active' : '' ?>"><i class="bi bi-award me-2"></i>Certificates</a>
                
                <div class="sidebar-section">Tools</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/practice" class="nav-link <?= ($activeNav ?? '') === 'practice' ? 'active' : '' ?>"><i class="bi bi-controller me-2"></i>Practice Mode</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/daily-quiz" class="nav-link <?= ($activeNav ?? '') === 'daily-quiz' ? 'active' : '' ?>"><i class="bi bi-calendar-check me-2"></i>Daily Quiz</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/weak-topics" class="nav-link <?= ($activeNav ?? '') === 'weak-topics' ? 'active' : '' ?>"><i class="bi bi-lightning-charge me-2"></i>Weak Topics</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/adaptive-quiz" class="nav-link <?= ($activeNav ?? '') === 'adaptive-quiz' ? 'active' : '' ?>"><i class="bi bi-bullseye me-2"></i>Adaptive Quiz</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/ai-practice" class="nav-link <?= ($activeNav ?? '') === 'ai-practice' ? 'active' : '' ?>"><i class="bi bi-robot me-2"></i>AI Practice</a>
                
                <div class="sidebar-section">Account</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/profile" class="nav-link <?= ($activeNav ?? '') === 'profile' ? 'active' : '' ?>"><i class="bi bi-person me-2"></i>Profile</a>
            </nav>
            <div class="sidebar-footer">
                <div class="text-truncate"><strong><?= htmlspecialchars($_SESSION['name'] ?? 'User') ?></strong></div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/logout" class="text-danger small text-decoration-none">Logout</a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="col-md-9 col-lg-10 ms-sm-auto main-content">
            <?= $content ?>
        </main>
    </div>
</div>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
