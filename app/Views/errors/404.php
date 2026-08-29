<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/assets/css/style.css?v=5">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="card p-5 text-center shadow-sm border-0" style="max-width: 480px;">
        <div class="display-1 text-primary fw-bold mb-2">404</div>
        <h3 class="fw-bold mb-2">Page Not Found</h3>
        <p class="text-muted mb-4">The page or resource you are looking for doesn't exist or has moved.</p>
        <a href="<?= defined('BASE_PATH') ? BASE_PATH : '' ?>/" class="btn btn-primary px-4 py-2">
            <i class="bi bi-house me-2"></i>Return Home
        </a>
    </div>
</body>
</html>
