<?php

declare(strict_types=1);

use CloudDeploy\Support\Env;
?>
<section class="page-head">
    <div>
        <h1>About this deployment</h1>
        <p class="lead">What was built, which tools were used, and how it reached Azure.</p>
    </div>
</section>

<section class="grid grid-2">
    <article class="card">
        <h2>Student and unit</h2>
        <dl class="kv">
            <dt>Student</dt><dd><?= htmlspecialchars(Env::get('STUDENT_NAME', 'Student Name'), ENT_QUOTES) ?></dd>
            <dt>Student ID</dt><dd><?= htmlspecialchars(Env::get('STUDENT_ID', 'Student ID'), ENT_QUOTES) ?></dd>
            <dt>Unit</dt><dd>SWE40006 Software Deployment and Evolution</dd>
            <dt>Assessment</dt><dd>Deployment Portfolio Task 3</dd>
            <dt>Level attempted</dt><dd>HD &mdash; sub-tasks 3.1, 3.2 and 3.3</dd>
        </dl>
    </article>

    <article class="card">
        <h2>Technology stack</h2>
        <dl class="kv">
            <dt>Language</dt><dd>PHP <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES) ?></dd>
            <dt>Dependencies</dt><dd>None at runtime (hand written autoloader)</dd>
            <dt>Hosting</dt><dd>Azure App Service, Linux, PHP 8.3</dd>
            <dt>Plan</dt><dd>F1 (Free) &mdash; no Always On on this tier</dd>
            <dt>Editor</dt><dd>Visual Studio Code with the PHP and Azure extensions</dd>
            <dt>Deployment</dt><dd>Zip deploy (<span class="mono">az webapp deploy</span>)</dd>
        </dl>
    </article>
</section>

<section class="card">
    <h2>Deployment pipeline</h2>
    <ol class="steps">
        <li><strong>Install PHP</strong> &mdash; PHP 8.3 for Windows plus Composer, verified with <span class="mono">php -v</span>.</li>
        <li><strong>Develop</strong> &mdash; front controller, autoloader, validator, views and stylesheet.</li>
        <li><strong>Run locally</strong> &mdash; <span class="mono">php -S localhost:8000</span> and exercise every route.</li>
        <li><strong>Create the Azure resources</strong> &mdash; resource group, Linux App Service plan (F1) and a PHP 8.3 web app.</li>
        <li><strong>Publish</strong> &mdash; <span class="mono">Compress-Archive</span> then <span class="mono">az webapp deploy --type zip</span>.</li>
        <li><strong>Configure</strong> &mdash; student identity and app version as application settings, health probe set to <span class="mono">/health</span>.</li>
        <li><strong>Verify</strong> &mdash; browse the public URL, check <span class="mono">/health</span>, <span class="mono">/api/info</span> and the validator.</li>
    </ol>
</section>

<section class="card">
    <h2>Repository layout</h2>
<pre class="code">src/php-webapp/
  index.php                    Front controller and routes
  bootstrap.php                Autoloader, error handling, logging to stderr
  src/Deployment/              EnvironmentReport, SelfTest, ManifestValidator
  src/Support/                 Env, View
  templates/                   layout, home, validate, about, not-found, error
  assets/app.css               Stylesheet served by the platform</pre>
    <p class="muted small">
        The application never writes to the site directory: App Service Linux runs the deployed content
        from a read-only mount, so only <span class="mono">/tmp</span> is writable. Every check respects that constraint.
    </p>
</section>
