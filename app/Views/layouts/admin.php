<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Panel — QuizApp') ?></title>
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
        <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebarOffcanvas" aria-label="Open menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <span class="navbar-brand mb-0 fw-semibold">QuizApp Admin</span>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/logout" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
</nav>

<!-- Sidebar offcanvas (mobile) -->
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="adminSidebarOffcanvas">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title fw-semibold mb-0">QuizApp</h5>
            <small class="text-danger fw-semibold">Administrator</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nav flex-column sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/quizzes" class="nav-link <?= ($activeNav ?? '') === 'quizzes' ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Quizzes</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions" class="nav-link <?= ($activeNav ?? '') === 'questions' ? 'active' : '' ?>"><i class="bi bi-patch-question me-2"></i>Questions</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/categories" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>"><i class="bi bi-tags me-2"></i>Categories</a>
            
            <div class="sidebar-section">Users & Reports</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Manage Users</a>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports" class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>"><i class="bi bi-bar-chart me-2"></i>Reports & Analytics</a>
            
            <div class="sidebar-section">AI Tools</div>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator" class="nav-link <?= ($activeNav ?? '') === 'ai-generator' ? 'active' : '' ?>"><i class="bi bi-stars me-2"></i>AI Quiz Generator</a>
        </nav>
        <div class="sidebar-footer border-top p-3 mt-auto">
            Logged in as <strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong><br>
            <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/logout" class="text-danger small">Logout</a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Desktop Sidebar -->
        <aside class="col-md-3 col-lg-2 d-none d-md-flex flex-column sidebar p-0">
            <div class="sidebar-brand">
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin" class="text-decoration-none text-dark fw-bold fs-5">QuizApp <span class="badge bg-danger fs-6 fw-normal ms-1">Admin</span></a>
            </div>
            <nav class="nav flex-column sidebar-nav flex-grow-1">
                <div class="sidebar-section">Main</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin" class="nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/quizzes" class="nav-link <?= ($activeNav ?? '') === 'quizzes' ? 'active' : '' ?>"><i class="bi bi-journal-check me-2"></i>Quizzes</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/questions" class="nav-link <?= ($activeNav ?? '') === 'questions' ? 'active' : '' ?>"><i class="bi bi-patch-question me-2"></i>Questions</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/categories" class="nav-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>"><i class="bi bi-tags me-2"></i>Categories</a>
                
                <div class="sidebar-section">Users & Reports</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/users" class="nav-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><i class="bi bi-people me-2"></i>Manage Users</a>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/reports" class="nav-link <?= ($activeNav ?? '') === 'reports' ? 'active' : '' ?>"><i class="bi bi-bar-chart me-2"></i>Reports & Analytics</a>
                
                <div class="sidebar-section">AI Tools</div>
                <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/admin/ai-generator" class="nav-link <?= ($activeNav ?? '') === 'ai-generator' ? 'active' : '' ?>"><i class="bi bi-stars me-2"></i>AI Quiz Generator</a>
            </nav>
            <div class="sidebar-footer">
                <div class="text-truncate"><strong><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></strong></div>
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
