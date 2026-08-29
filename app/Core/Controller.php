<?php
namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $path): void
    {
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            header('Location: ' . $path);
        } else {
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $base . '/' . ltrim($path, '/'));
        }
        exit;
    }

    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_PATH') ? BASE_PATH : '/');
        header('Location: ' . $referer);
        exit;
    }
}
