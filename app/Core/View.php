<?php

namespace Lops2\Core;

class View
{
    private static string $viewsPath;

    public static function init(string $path): void
    {
        self::$viewsPath = rtrim($path, '/');
    }

    /**
     * Render a view inside a layout.
     *
     * @param string      $template  e.g. 'dashboard/index'
     * @param array       $data      Variables extracted into the template scope
     * @param string|null $layout    'app' | 'auth' | null (no layout)
     */
    public static function render(string $template, array $data = [], ?string $layout = 'app'): void
    {
        // Load global helpers that views depend on (idempotent due to require_once)
        require_once LOPS2_ROOT . '/libs/icons.php';
        // Capture the inner view
        $viewFile = self::$viewsPath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: {$viewFile}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = self::$viewsPath . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layoutFile}");
        }

        extract($data, EXTR_SKIP);
        include $layoutFile;
    }

    public static function partial(string $name, array $data = []): void
    {
        $file = self::$viewsPath . '/partials/' . $name . '.php';
        if (!is_file($file)) {
            return;
        }
        extract($data, EXTR_SKIP);
        include $file;
    }

    public static function path(): string
    {
        return self::$viewsPath;
    }
}
