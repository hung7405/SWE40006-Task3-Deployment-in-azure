<?php

declare(strict_types=1);

/**
 * Last resort error page. Rendered directly by the exception handler in
 * bootstrap.php, before the view layer is guaranteed to work.
 *
 * @var string $detail
 */
$detail = $detail ?? 'The request could not be processed.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 &mdash; application error</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="container main">
    <section class="page-head">
        <div>
            <h1>500 &mdash; the application could not process this request</h1>
            <p class="lead">This page is returned by the global exception handler in <span class="mono">bootstrap.php</span>.</p>
        </div>
    </section>
    <section class="card">
        <p><?= htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <p class="muted small">
            The full stack trace was written to the log stream, so it is visible in
            <span class="mono">az webapp log tail</span> or in the portal under
            <em>Monitoring &rarr; Log stream</em> without being exposed to the public.
        </p>
        <p><a class="btn btn-primary" href="/">Back to the dashboard</a></p>
    </section>
</main>
</body>
</html>
