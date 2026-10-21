<?php

declare(strict_types=1);

namespace CloudDeploy\Support;

/**
 * Minimal view renderer: a template file is included inside an output buffer
 * and its result is injected into templates/layout.php.
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], int $status = 200): void
    {
        $file = dirname(__DIR__, 2) . '/templates/' . $template . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException(sprintf('Template "%s" does not exist.', $template));
        }

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');

        $data['route'] = $template;
        $data['appName'] = Env::get('APP_NAME', 'CloudDeploy PHP');

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        require dirname(__DIR__, 2) . '/templates/layout.php';
    }

    public static function json(mixed $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
