<?php

declare(strict_types=1);

use CloudDeploy\Deployment\SelfTest;

/**
 * @var list<array{title: string, rows: list<array{label: string, value: string, mono: bool}>}> $sections
 * @var list<array{name: string, status: string, detail: string}> $checks
 * @var string $overall
 */
?>
<section class="hero">
    <div>
        <span class="pill">PHP 8 &middot; Azure App Service &middot; Task 3.3</span>
        <h1>Deployment evidence, live from the App Service worker</h1>
        <p class="lead">
            This page is rendered by PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES) ?> running on Microsoft Azure.
            Every value below is read from the running process or from the platform environment variables,
            so it changes when the deployment changes &mdash; it cannot be faked by a static HTML file.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary" href="/validate">Validate a deployment manifest</a>
            <a class="btn btn-ghost" href="/api/info" target="_blank" rel="noopener">View JSON API</a>
            <a class="btn btn-ghost" href="/health" target="_blank" rel="noopener">Health probe</a>
        </div>
    </div>
    <div class="hero-card status-<?= htmlspecialchars($overall, ENT_QUOTES) ?>">
        <span class="dot"></span>
        <div>
            <div class="hero-card-label">Self-test result</div>
            <div class="hero-card-value"><?= htmlspecialchars(strtoupper($overall), ENT_QUOTES) ?></div>
            <div class="hero-card-meta"><?= count($checks) ?> runtime checks &middot; <?= gmdate('H:i:s') ?> UTC</div>
        </div>
    </div>
</section>

<section class="grid">
    <?php foreach ($sections as $section): ?>
        <article class="card">
            <h2><?= htmlspecialchars($section['title'], ENT_QUOTES) ?></h2>
            <dl class="kv">
                <?php foreach ($section['rows'] as $row): ?>
                    <dt><?= htmlspecialchars($row['label'], ENT_QUOTES) ?></dt>
                    <dd class="<?= $row['mono'] ? 'mono' : '' ?>"><?= htmlspecialchars($row['value'], ENT_QUOTES) ?></dd>
                <?php endforeach; ?>
            </dl>
        </article>
    <?php endforeach; ?>
</section>

<section class="card">
    <div class="card-head">
        <h2>Runtime self-tests</h2>
        <span class="badge badge-<?= htmlspecialchars($overall, ENT_QUOTES) ?>"><?= htmlspecialchars(strtoupper($overall), ENT_QUOTES) ?></span>
    </div>
    <table class="table">
        <thead>
        <tr><th>Check</th><th>Result</th><th>Detail</th></tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= htmlspecialchars($check['name'], ENT_QUOTES) ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($check['status'], ENT_QUOTES) ?>"><?= htmlspecialchars(strtoupper($check['status']), ENT_QUOTES) ?></span></td>
                <td class="muted small"><?= htmlspecialchars($check['detail'], ENT_QUOTES) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted small">
        The same checks back the <span class="mono">/health</span> endpoint used by the App Service health probe
        and by the release verification script, so a broken worker is taken out of rotation instead of serving errors.
    </p>
</section>

<section class="card">
    <h2>What this application demonstrates</h2>
    <ul class="feature-list">
        <li><strong>Front controller routing</strong> &mdash; a single <span class="mono">index.php</span> dispatches to controllers without a framework.</li>
        <li><strong>PSR-4 style autoloading</strong> &mdash; hand written, so the deployment has no <span class="mono">vendor/</span> directory to build.</li>
        <li><strong>Server side validation</strong> &mdash; twelve rules check an Azure deployment manifest and explain every failure.</li>
        <li><strong>JSON API</strong> &mdash; <span class="mono">/api/info</span> and <span class="mono">/validate</span> accept and return JSON for scripted checks.</li>
        <li><strong>Operability</strong> &mdash; structured errors on <span class="mono">stderr</span>, a real health probe and no writes to the read-only site directory.</li>
    </ul>
</section>
