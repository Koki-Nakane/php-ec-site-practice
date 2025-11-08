<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

final class TemplateRenderer
{
    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /**
     * @param array<string,mixed> $params
     */
    public function render(string $template, array $params = []): string
    {
        $path = $this->resolvePath($template);

        extract($params, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        $normalized = ltrim($template, '/');
        $path = $this->basePath . '/' . $normalized;

        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('テンプレートファイルが見つかりません: %s', $template));
        }

        return $path;
    }
}
