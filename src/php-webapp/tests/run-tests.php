<?php

declare(strict_types=1);

/**
 * Dependency-free test suite for the manifest validator.
 *
 * Run with:  php tests/run-tests.php
 *
 * The application ships no Composer dependencies, so PHPUnit is not installed;
 * this tiny harness keeps the same shape (arrange / act / assert) and returns a
 * non-zero exit code when a test fails, which is what a CI pipeline needs.
 */

require __DIR__ . '/../bootstrap.php';

use CloudDeploy\Deployment\Finding;
use CloudDeploy\Deployment\ManifestValidator;

$passed = 0;
$failed = 0;
$failures = [];

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $failures;

    if ($condition) {
        $passed++;
        printf("  \033[32mPASS\033[0m  %s\n", $name);
        return;
    }

    $failed++;
    $failures[] = $name . ($detail !== '' ? ' -> ' . $detail : '');
    printf("  \033[31mFAIL\033[0m  %s%s\n", $name, $detail !== '' ? ' -> ' . $detail : '');
}

function levelOf(\CloudDeploy\Deployment\ValidationReport $report, string $field): ?string
{
    foreach ($report->findings as $finding) {
        if ($finding->field === $field) {
            return $finding->level;
        }
    }

    return null;
}

echo "\n  CloudDeploy PHP - manifest validator tests\n";
echo "  PHP " . PHP_VERSION . " - " . date('c') . "\n\n";

// ---------------------------------------------------------------------------
// A valid manifest passes every rule.
// ---------------------------------------------------------------------------
$valid = [
    'appName' => 'clouddeploy-12345678',
    'runtime' => 'DOTNETCORE:9.0',
    'os' => 'linux',
    'region' => 'australiaeast',
    'plan' => 'plan-swe40006-task3',
    'sku' => 'F1',
    'healthCheckPath' => '/health',
    'httpsOnly' => true,
    'minTlsVersion' => '1.2',
    'alwaysOn' => false,
    'appSettings' => ['Student__FullName' => 'Student', 'Student__Id' => '12345678'],
];

$report = ManifestValidator::validate($valid);
check('valid manifest scores PASS', $report->status() === 'PASS', 'status ' . $report->status());
check('valid manifest has no blocking findings', $report->count(Finding::FAIL) === 0);
check('valid manifest counts 11 or more rules', count($report->findings) >= 11, (string) count($report->findings));
check('free tier alwaysOn=false is allowed', levelOf($report, 'alwaysOn') === Finding::PASS);

// ---------------------------------------------------------------------------
// Individual rules reject the configuration mistakes seen in practice.
// ---------------------------------------------------------------------------
$uppercase = ManifestValidator::validate(['appName' => 'CloudDeploy_Dashboard'] + $valid);
check('uppercase app name is rejected', levelOf($uppercase, 'appName') === Finding::FAIL);

$phpOnWindows = ManifestValidator::validate(['runtime' => 'PHP:8.3', 'os' => 'windows'] + $valid);
check('PHP 8.3 on Windows is rejected', levelOf($phpOnWindows, 'runtime + os') === Finding::FAIL);

$php82OnWindows = ManifestValidator::validate(['runtime' => 'PHP:8.2', 'os' => 'windows'] + $valid);
check('PHP 8.2 on Windows is allowed', levelOf($php82OnWindows, 'runtime + os') === Finding::PASS);

$slot = ManifestValidator::validate(['slot' => 'staging'] + $valid);
check('slot on F1 is rejected', levelOf($slot, 'slot') === Finding::FAIL);

$slotOnStandard = ManifestValidator::validate(['slot' => 'staging', 'sku' => 'S1'] + $valid);
check('slot on S1 is allowed', levelOf($slotOnStandard, 'slot') === Finding::PASS);

$http = ManifestValidator::validate(['httpsOnly' => false] + $valid);
check('httpsOnly=false is rejected', levelOf($http, 'httpsOnly') === Finding::FAIL);

$missingHealth = ManifestValidator::validate(array_diff_key($valid, ['healthCheckPath' => true]));
check('missing healthCheckPath is rejected', levelOf($missingHealth, 'healthCheckPath') === Finding::FAIL);

$homePageProbe = ManifestValidator::validate(['healthCheckPath' => '/'] + $valid);
check('using / as probe warns', levelOf($homePageProbe, 'healthCheckPath') === Finding::WARN);

$tls = ManifestValidator::validate(['minTlsVersion' => '1.0'] + $valid);
check('TLS 1.0 is rejected', levelOf($tls, 'minTlsVersion') === Finding::FAIL);

$secret = ManifestValidator::validate(['appSettings' => ['DbPassword' => 'x']] + $valid);
check('plain-text secret warns', levelOf($secret, 'appSettings') === Finding::FAIL || $secret->count(Finding::WARN) > 0);

$region = ManifestValidator::validate(['region' => 'eastus'] + $valid);
check('non-Australian region warns', levelOf($region, 'region') === Finding::WARN);

$badRuntime = ManifestValidator::validate(['runtime' => 'COBOL:1.0'] + $valid);
check('unknown stack is rejected', levelOf($badRuntime, 'runtime') === Finding::FAIL);

$alwaysOnFree = ManifestValidator::validate(['alwaysOn' => true] + $valid);
check('alwaysOn=true on F1 is rejected', levelOf($alwaysOnFree, 'alwaysOn') === Finding::FAIL);

// ---------------------------------------------------------------------------
// Scoring behaviour.
// ---------------------------------------------------------------------------
$score = $report->score();
check('perfect manifest scores 100', $score === 100, (string) $score);
$brokenScore = ManifestValidator::validate([
    'appName' => 'BAD_Name',
    'runtime' => 'PHP:8.3',
    'os' => 'windows',
    'sku' => 'F1',
    'slot' => 'staging',
    'healthCheckPath' => 'health',
    'httpsOnly' => 'yes',
    'alwaysOn' => true,
])->score();
check('broken manifest scores below 60', $brokenScore < 60, (string) $brokenScore);

echo "\n  " . str_repeat('-', 58) . "\n";
printf("  %d passed, %d failed\n", $passed, $failed);

if ($failed > 0) {
    echo "\n  Failures:\n";
    foreach ($failures as $failure) {
        echo "   - {$failure}\n";
    }
    echo "\n";
    exit(1);
}

echo "  RESULT: OK\n\n";
exit(0);
