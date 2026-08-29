<?php
namespace App\Core;

class View
{
    public static function render(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        extract($data);

        // Capture view content
        $viewFile = __DIR__ . '/../Views/' . ltrim($viewPath, '/') . '.php';
        if (!file_exists($viewFile)) {
            die("View file not found: {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If no layout is requested, output content directly
        if ($layout === null) {
            echo $content;
            return;
        }

        // Render layout
        $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            die("Layout file not found: {$layoutFile}");
        }

        require $layoutFile;
    }
}
