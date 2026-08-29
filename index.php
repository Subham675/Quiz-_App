<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';
require_once __DIR__ . '/includes/auth.php';

startSession();

// Load MVC Web Routes
require_once __DIR__ . '/routes/web.php';

// Dispatch Request
$request = new \App\Core\Request();
\App\Core\Router::dispatch($request);
