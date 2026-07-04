<?php

namespace Lops2\Core;

class Router
{
    private static array $routes = [];

    public static function get(string $pattern, string $handler): void
    {
        self::$routes['GET'][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    public static function post(string $pattern, string $handler): void
    {
        self::$routes['POST'][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    /** Run: match the current request URI and call the controller. */
    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        // Strip APP_BASE from URI so routes are defined without the prefix.
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $base = rtrim(APP_BASE, '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        foreach (self::$routes[$method] ?? [] as $route) {
            $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = explode('@', $route['handler']);
                $fqn = '\\Lops2\\Controllers\\' . $class;
                (new $fqn())->$action($params);
                return;
            }
        }

        // 404
        render_error_page(404, 'The page you requested doesn\'t exist. Double-check the link, or head back to the dashboard.');
    }
}
