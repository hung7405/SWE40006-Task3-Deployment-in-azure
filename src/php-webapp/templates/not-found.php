<?php

declare(strict_types=1);

/** @var string $path */
?>
<section class="page-head">
    <div>
        <h1>404 &mdash; page not found</h1>
        <p class="lead">The front controller received the request but no route matched it.</p>
    </div>
</section>

<section class="card">
    <dl class="kv">
        <dt>Requested path</dt><dd class="mono"><?= htmlspecialchars($path, ENT_QUOTES) ?></dd>
        <dt>Known routes</dt><dd class="mono">/ &nbsp; /validate &nbsp; /api/info &nbsp; /health &nbsp; /about</dd>
    </dl>
    <p><a class="btn btn-primary" href="/">Back to the dashboard</a></p>
</section>
