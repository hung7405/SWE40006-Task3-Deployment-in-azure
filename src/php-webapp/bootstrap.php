<?php

declare(strict_types=1);

/**
 * Bootstrap for the CloudDeploy PHP web application (SWE40006 Task 3.3).
 *
 * The application deliberately has zero third-party dependencies: no Composer
 * vendor directory means there is nothing to install during the App Service
 * build step, which removes a whole class of deployment failures and makes the
 * cold start of the F1 plan noticeably faster.
 */

// ---------------------------------------------------------------------------
// Autoloading (PSR-4 style: CloudDeploy\Foo\Bar -> src/Foo/Bar.php)
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'CloudDeploy\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

date_default_timezone_set('UTC');

// ---------------------------------------------------------------------------
// Error handling
//
// Errors are never printed to the browser. They are written to stderr, which is
// exactly where Azure App Service expects them: the "Log stream" blade in the
// portal and `az webapp log tail` both read from that stream, so every PHP
// notice/warning becomes visible during an incident investigation.
// ---------------------------------------------------------------------------
$debug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);

ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stderr');

error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $exception) use ($debug): void {
    error_log(sprintf(
        '[clouddeploy-php] Unhandled %s: %s in %s:%d',
        $exception::class,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));

    // CLI context (tests, cron, scripts): never render HTML, never touch headers.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, sprintf(
            "[clouddeploy-php] %s: %s in %s:%d\n",
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
        exit(1);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $detail = $debug
        ? htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'The request could not be processed. The details have been written to the App Service log stream.';

    require __DIR__ . '/templates/error.php';
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log(sprintf(
            '[clouddeploy-php] Fatal: %s in %s:%d',
            $error['message'],
            $error['file'],
            $error['line']
        ));
    }
});
