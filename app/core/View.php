<?php
declare(strict_types=1);

/**
 * Minimal view renderer. Renders a view file into $content, then wraps it in a
 * layout. Pass 'auth' as the layout for pre-login pages.
 */
class View
{
    public static function render(string $name, array $data = [], ?string $layout = 'app'): void
    {
        $data['title'] = $data['title'] ?? (string) config('app.name');
        extract($data, EXTR_SKIP);

        ob_start();
        include APP_PATH . '/views/' . $name . '.php';
        $content = ob_get_clean();

        $layoutFile = APP_PATH . '/views/layouts/' . ($layout ?? 'app') . '.php';
        include $layoutFile;
    }
}
