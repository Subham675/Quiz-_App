<?php
// Router for PHP built-in web server
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Normalize /Quiz_app or /Quiz-_App prefix (case-insensitive)
if (preg_match('#^/quiz[-_]?app(/.*)?$#i', $uri, $m)) {
    $uri = $m[1] ?? '/';
    if ($uri === '') $uri = '/';
}

$file = __DIR__ . $uri;

// Directory access defaults to index.php
if (is_dir($file)) {
    $file = rtrim($file, '/') . '/index.php';
}

// If exact file exists (like .php, .css, .js, images)
if (file_exists($file) && !is_dir($file)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        chdir(dirname($file));
        require $file;
        exit;
    }

    // Static asset MIME types
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

    $mime = $mimes[$ext] ?? 'application/octet-stream';
    header("Content-Type: {$mime}");
    readfile($file);
    exit;
}

// If extension was omitted e.g. /public/login -> /public/login.php
if (file_exists($file . '.php')) {
    chdir(dirname($file . '.php'));
    require $file . '.php';
    exit;
}

http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($uri);
exit;
