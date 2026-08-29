<?php
namespace App\Core;

class Router
{
    private static array $routes = [];

    public static function get(string $path, $handler, array $middleware = []): void
    {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    public static function post(string $path, $handler, array $middleware = []): void
    {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    public static function any(string $path, $handler, array $middleware = []): void
    {
        self::addRoute('GET', $path, $handler, $middleware);
        self::addRoute('POST', $path, $handler, $middleware);
    }

    private static function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $pattern = self::pathToRegex($path);
        self::$routes[] = [
            'method'     => $method,
            'path'       => $path,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    private static function pathToRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public static function dispatch(Request $request): void
    {
        $uri    = $request->getUri();
        $method = $request->getMethod();

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                $request->setParams($params);

                // Run Middleware checks
                foreach ($route['middleware'] as $mw) {
                    self::runMiddleware($mw);
                }

                // Execute handler
                self::executeHandler($route['handler'], $request, $params);
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        require_once __DIR__ . '/../Views/errors/404.php';
    }

    private static function runMiddleware(string $mw): void
    {
        if ($mw === 'auth') {
            if (!isLoggedIn()) {
                header('Location: ' . BASE_PATH . '/login');
                exit;
            }
        } elseif ($mw === 'admin') {
            if (!isAdmin()) {
                header('Location: ' . BASE_PATH . '/dashboard');
                exit;
            }
        } elseif ($mw === 'guest') {
            if (isLoggedIn()) {
                if (isAdmin()) {
                    header('Location: ' . BASE_PATH . '/admin');
                } else {
                    header('Location: ' . BASE_PATH . '/dashboard');
                }
                exit;
            }
        }
    }

    private static function executeHandler($handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $request, $params);
            return;
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerClass, $method] = explode('@', $handler);
            $fullClass = 'App\\Controllers\\' . $controllerClass;

            if (!class_exists($fullClass)) {
                die("Controller not found: {$fullClass}");
            }

            $controller = new $fullClass();
            if (!method_exists($controller, $method)) {
                die("Method {$method} does not exist on controller {$fullClass}");
            }

            $controller->$method($request, ...array_values($params));
            return;
        }

        die('Invalid route handler specified.');
    }
}
