<?php
declare(strict_types=1);

/**
 * Tiny front-controller router with {param} placeholders.
 * Routes are registered in public_html/index.php.
 * A handler is a closure or [ControllerClass, 'method'].
 */
class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $opts = []): void
    {
        $this->add('GET', $path, $handler, $opts);
    }

    public function post(string $path, $handler, array $opts = []): void
    {
        $this->add('POST', $path, $handler, $opts);
    }

    private function add(string $method, string $path, $handler, array $opts): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => trim($path, '/'),
            'handler' => $handler,
            'auth'    => !empty($opts['auth']),
            'admin'   => !empty($opts['admin']),
        ];
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = self::currentPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['auth'] && !Auth::check()) {
                flash('error', 'Please sign in to continue.');
                redirect('login');
            }
            if ($route['admin'] && !Auth::isAdmin()) {
                flash('error', 'You do not have permission to access that page.');
                redirect('dashboard');
            }

            $handler = $route['handler'];
            if (is_callable($handler)) {
                $handler(...array_values($params));
            } else {
                [$class, $action] = $handler;
                (new $class())->{$action}(...array_values($params));
            }
            return;
        }

        http_response_code(404);
        $layout = Auth::check() ? 'app' : 'auth';
        View::render('errors/404', ['title' => 'Page not found'], $layout);
    }

    /** Match a pattern like "products/{id}/edit" against the request path. */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === '' && $path === '') {
            return [];
        }
        $patternSegs = explode('/', $pattern);
        $pathSegs    = explode('/', $path);
        if (count($patternSegs) !== count($pathSegs)) {
            return null;
        }
        $params = [];
        foreach ($patternSegs as $i => $seg) {
            if (str_starts_with($seg, '{') && str_ends_with($seg, '}')) {
                $value = urldecode($pathSegs[$i]);
                // Numeric segments (ids) become ints so controller int type-hints hold.
                $params[trim($seg, '{}')] = ctype_digit($value) ? (int) $value : $value;
            } elseif ($seg !== $pathSegs[$i]) {
                return null;
            }
        }
        return $params;
    }

    /**
     * The requested path, normalized and with any base directory stripped.
     * '/' becomes '', '/login' becomes 'login'.
     */
    public static function currentPath(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = (string) config('app.base_path', '');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        return rawurldecode(trim($uri, '/'));
    }
}
