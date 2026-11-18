<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, '/\\');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderPartial($template, $data);

        if ($layout === null) {
            return $content;
        }

        $data['content'] = $content;
        return $this->renderPartial($layout, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $file = $this->viewsPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Template tidak ditemukan: ' . $template);
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $csrf = Csrf::token();
        $flashes = $data['flashes'] ?? Session::pullFlashes();
        $data['csrf'] = $csrf;
        $data['flashes'] = $flashes;
        $data['e'] = $e;

        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }

        return (string) ob_get_clean();
    }
}
