<?php

declare(strict_types=1);

use CloudDeploy\Support\Env;

/** @var string $content */
/** @var string $appName */
/** @var string $route */

$studentName = Env::get('STUDENT_NAME', 'Student Name');
$studentId = Env::get('STUDENT_ID', 'Student ID');
$unitCode = Env::get('UNIT_CODE', 'SWE40006');

$nav = [
    'home' => ['/', 'Dashboard'],
    'validate' => ['/validate', 'Manifest validator'],
    'about' => ['/about', 'About'],
];

$pageTitle = match ($route) {
    'home' => 'Dashboard',
    'validate' => 'Manifest validator',
    'about' => 'About',
    'not-found' => 'Not found',
    default => 'PHP web application',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?> &middot; <?= htmlspecialchars($appName, ENT_QUOTES) ?></title>
    <meta name="description" content="PHP 8 application deployed to Azure App Service for SWE40006 Deployment Portfolio Task 3.">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="/">
            <span class="brand-mark">&lt;?&gt;</span>
            <span class="brand-text">
                <strong>Dung &middot; <?= htmlspecialchars($appName, ENT_QUOTES) ?></strong>
                <small><?= htmlspecialchars($unitCode, ENT_QUOTES) ?> &middot; Task 3.3</small>
            </span>
        </a>
        <nav class="nav">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="<?= $route === $key ? 'active' : '' ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES) ?>
                </a>
            <?php endforeach; ?>
            <a class="nav-cta" href="https://portal.azure.com" target="_blank" rel="noopener">Azure Portal &#8599;</a>
        </nav>
    </div>
</header>

<main class="container main">
    <?= $content ?>
</main>

<footer class="footer">
    <div class="container footer-inner">
        <div>
            <strong><?= htmlspecialchars($studentName, ENT_QUOTES) ?></strong> &middot; <?= htmlspecialchars($studentId, ENT_QUOTES) ?><br>
            <span class="muted"><?= htmlspecialchars($unitCode, ENT_QUOTES) ?> Software Deployment and Evolution &middot; Deployment Portfolio Task 3</span>
        </div>
        <div class="muted small">
            PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES) ?> &middot; Microsoft Azure App Service (Linux)<br>
            &copy; <?= gmdate('Y') ?>
        </div>
    </div>
</footer>
</body>
</html>
