<?php

declare(strict_types=1);

/**
 * Router used only by PHP's built-in development server:
 *
 *     php -S localhost:8000 router.php
 *
 * PHP's built-in server has no rewrite rules, so without this file requests such
 * as /validate or /health would return 404 instead of reaching the front
 * controller. Azure App Service does not use this file: its nginx + php-fpm
 * stack serves /assets/app.css directly and forwards every other path to
 * index.php by default.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

// Let the built-in server serve real files (CSS, images, favicon).
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
