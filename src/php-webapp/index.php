<?php

declare(strict_types=1);

/**
 * CloudDeploy PHP - front controller.
 *
 * Every request enters here. The router is intentionally tiny (a match on the
 * request path) because the application only exposes five endpoints; pulling in
 * a framework would add a Composer dependency and a build step to the App
 * Service deployment without adding any value for this assessment.
 *
 * Routes
 *   GET  /            dashboard (runtime facts + self-tests)
 *   GET  /validate    manifest validator form
 *   POST /validate    validate a manifest (HTML or JSON response)
 *   GET  /api/info    machine readable deployment facts
 *   GET  /health      health probe (503 when a critical self-test fails)
 *   GET  /about       what was built and how it was deployed
 */

require __DIR__ . '/bootstrap.php';

use CloudDeploy\Deployment\EnvironmentReport;
use CloudDeploy\Deployment\Finding;
use CloudDeploy\Deployment\ManifestValidator;
use CloudDeploy\Deployment\SelfTest;
use CloudDeploy\Deployment\ValidationReport;
use CloudDeploy\Support\View;

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');

$validManifest = <<<'JSON'
{
  "appName": "clouddeploy-12345678",
  "runtime": "DOTNETCORE:9.0",
  "os": "linux",
  "region": "australiaeast",
  "plan": "plan-swe40006-task3",
  "sku": "F1",
  "healthCheckPath": "/health",
  "httpsOnly": true,
  "minTlsVersion": "1.2",
  "alwaysOn": false,
  "appSettings": {
    "Student__FullName": "Your Full Name",
    "Student__Id": "Your Student ID",
    "App__Version": "1.0.0"
  }
}
JSON;

$brokenManifest = <<<'JSON'
{
  "appName": "CloudDeploy_Dashboard",
  "runtime": "PHP:8.3",
  "os": "windows",
  "region": "eastus",
  "sku": "F1",
  "slot": "staging",
  "healthCheckPath": "health",
  "httpsOnly": "yes",
  "alwaysOn": true,
  "appSettings": {
    "DbPassword": "P@ssw0rd!"
  }
}
JSON;

switch (true) {
    case $path === '/' && $method === 'GET':
        $checks = SelfTest::run();

        View::render('home', [
            'sections' => EnvironmentReport::sections(),
            'checks' => $checks,
            'overall' => SelfTest::overall($checks),
        ]);
        break;

    case $path === '/about' && $method === 'GET':
        View::render('about');
        break;

    case $path === '/validate' && $method === 'GET':
        $example = ($_GET['example'] ?? '') === 'broken' ? $brokenManifest : $validManifest;

        View::render('validate', [
            'manifest' => $example,
            'report' => null,
            'error' => null,
        ]);
        break;

    case $path === '/validate' && $method === 'POST':
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $wantsJson = str_contains($contentType, 'application/json');

        $raw = $wantsJson
            ? (string) file_get_contents('php://input')
            : (string) ($_POST['manifest'] ?? '');

        if (trim($raw) === '') {
            $report = new ValidationReport([
                new Finding(Finding::FAIL, 'manifest', 'The request body was empty.', 'Paste a JSON manifest or use the sample.'),
            ]);
        } else {
            try {
                $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);

                if (!is_array($decoded)) {
                    throw new RuntimeException('The manifest must be a JSON object.');
                }

                $report = ManifestValidator::validate($decoded, $raw);
            } catch (JsonException $exception) {
                $report = new ValidationReport([
                    new Finding(
                        Finding::FAIL,
                        'manifest',
                        'Invalid JSON: ' . $exception->getMessage(),
                        'A trailing comma or a missing brace is the usual cause; paste the manifest into a JSON formatter to locate it.'
                    ),
                ]);
            } catch (RuntimeException $exception) {
                $report = new ValidationReport([
                    new Finding(Finding::FAIL, 'manifest', $exception->getMessage(), 'Wrap the manifest in { ... }.'),
                ]);
            }
        }

        if ($wantsJson) {
            View::json([
                'status' => $report->status(),
                'score' => $report->score(),
                'counts' => [
                    'pass' => $report->count(Finding::PASS),
                    'warn' => $report->count(Finding::WARN),
                    'fail' => $report->count(Finding::FAIL),
                ],
                'findings' => array_map(
                    static fn (Finding $finding): array => [
                        'level' => $finding->level,
                        'field' => $finding->field,
                        'message' => $finding->message,
                        'remedy' => $finding->remedy,
                    ],
                    $report->findings
                ),
            ], $report->status() === 'FAIL' ? 422 : 200);
            break;
        }

        View::render('validate', [
            'manifest' => $raw,
            'report' => $report,
            'error' => null,
        ]);
        break;

    case $path === '/api/info' && $method === 'GET':
        View::json([
            'application' => EnvironmentReport::toArray(),
            'selfTests' => SelfTest::run(),
            'overall' => SelfTest::overall(),
        ]);
        break;

    case $path === '/health' && $method === 'GET':
        $checks = SelfTest::run();
        $overall = SelfTest::overall($checks);

        View::json([
            'status' => $overall === SelfTest::FAIL ? 'Unhealthy' : 'Healthy',
            'detail' => $overall,
            'checks' => array_map(
                static fn (array $check): array => [
                    'name' => $check['name'],
                    'status' => $check['status'],
                    'detail' => $check['detail'],
                ],
                $checks
            ),
            'serverTimeUtc' => gmdate('c'),
        ], $overall === SelfTest::FAIL ? 503 : 200);
        break;

    default:
        View::render('not-found', ['path' => $path], 404);
}
