<?php

declare(strict_types=1);

use CloudDeploy\Deployment\Finding;
use CloudDeploy\Deployment\ValidationReport;

/**
 * @var string $manifest
 * @var ValidationReport|null $report
 */
?>
<section class="page-head">
    <div>
        <h1>Deployment manifest validator</h1>
        <p class="lead">
            Paste the App Service configuration you intend to deploy. Twelve rules encode the platform
            constraints that broke earlier attempts, so a mistake is caught before the deployment starts.
        </p>
    </div>
</section>

<?php if ($report !== null): ?>
    <section class="card result result-<?= htmlspecialchars(strtolower($report->status()), ENT_QUOTES) ?>">
        <div class="result-head">
            <div>
                <span class="badge badge-<?= htmlspecialchars(strtolower($report->status()), ENT_QUOTES) ?>">
                    <?= htmlspecialchars($report->status(), ENT_QUOTES) ?>
                </span>
                <h2>Validation result</h2>
            </div>
            <div class="score">
                <span class="score-value"><?= $report->score() ?></span>
                <span class="score-label">/ 100</span>
            </div>
        </div>
        <p class="muted">
            <?= $report->count(Finding::PASS) ?> passed &middot;
            <?= $report->count(Finding::WARN) ?> warning(s) &middot;
            <?= $report->count(Finding::FAIL) ?> blocking failure(s)
        </p>
        <table class="table">
            <thead>
            <tr><th>Rule</th><th>Result</th><th>Finding</th><th>Remedy</th></tr>
            </thead>
            <tbody>
            <?php foreach ($report->findings as $finding): ?>
                <tr>
                    <td class="mono small"><?= htmlspecialchars($finding->field, ENT_QUOTES) ?></td>
                    <td><span class="badge badge-<?= htmlspecialchars($finding->level, ENT_QUOTES) ?>"><?= htmlspecialchars($finding->label(), ENT_QUOTES) ?></span></td>
                    <td><?= htmlspecialchars($finding->message, ENT_QUOTES) ?></td>
                    <td class="muted small"><?= htmlspecialchars($finding->remedy, ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<section class="grid grid-2">
    <article class="card">
        <h2>Submit a manifest</h2>
        <form method="post" action="/validate" class="form">
            <label for="manifest">Manifest (JSON)</label>
            <textarea id="manifest" name="manifest" rows="20" spellcheck="false" class="mono"><?= htmlspecialchars($manifest, ENT_QUOTES) ?></textarea>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Validate</button>
                <a class="btn btn-ghost" href="/validate?example=broken">Load a failing example</a>
                <a class="btn btn-ghost" href="/validate">Reset</a>
            </div>
        </form>
    </article>

    <article class="card">
        <h2>Rules applied</h2>
        <ul class="feature-list compact">
            <li><strong>appName</strong> &mdash; 3-60 lowercase characters; it becomes the DNS label.</li>
            <li><strong>runtime / os</strong> &mdash; <span class="mono">STACK:VERSION</span>; PHP 8.3 requires Linux.</li>
            <li><strong>region</strong> &mdash; warns when the site is hosted far from the marker.</li>
            <li><strong>sku / alwaysOn / slot</strong> &mdash; Free tier has no Always On and no slots.</li>
            <li><strong>healthCheckPath</strong> &mdash; must be a dedicated <span class="mono">/health</span> route.</li>
            <li><strong>httpsOnly / minTlsVersion</strong> &mdash; HTTPS and TLS 1.2+ are mandatory.</li>
            <li><strong>appSettings</strong> &mdash; flags anything that looks like a secret.</li>
        </ul>
        <p class="muted small">
            The validator is also an API: <span class="mono">POST /validate</span> with
            <span class="mono">Content-Type: application/json</span> returns the same report as JSON
            (HTTP 422 when the manifest has blocking failures).
        </p>
        <h3>Scripted example</h3>
<pre class="code">curl -s -X POST https://<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES) ?>/validate ^
  -H "Content-Type: application/json" ^
  --data-binary "@manifest.json"</pre>
    </article>
</section>
