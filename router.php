<?php
// Router for PHP built-in web server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Normalize /Quiz_app or /Quiz-_App prefix (case-insensitive)
if (preg_match('#^/quiz[-_]?app(/.*)?$#i', $uri, $m)) {
    $uri = $m[1] ?? '/';
    if ($uri === '') $uri = '/';
}

$file = __DIR__ . $uri;

// If static asset exists (CSS, JS, images, fonts, PDF)
if (file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'pdf'   => 'application/pdf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];

    if (isset($mimes[$ext])) {
        header("Content-Type: {$mimes[$ext]}");
        readfile($file);
        exit;
    }
}

// Forward everything to Front Controller
require __DIR__ . '/index.php';
