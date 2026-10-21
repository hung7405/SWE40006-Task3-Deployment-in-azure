<?php

declare(strict_types=1);

namespace CloudDeploy\Deployment;

/**
 * Validates a declarative Azure App Service deployment manifest.
 *
 * The rules encode the platform constraints that caused real failures during
 * the deployment work for this assessment: a PHP 8.3 runtime cannot run on a
 * Windows plan, deployment slots do not exist on the Free/Shared tiers, an app
 * without a health-check path cannot be probed by App Service, and secrets must
 * not be stored as plain application settings.
 */
final class ManifestValidator
{
    private const SUPPORTED_RUNTIMES = ['DOTNETCORE', 'PHP', 'NODE', 'PYTHON', 'JAVA', 'RUBY'];

    private const SKUS = ['F1', 'D1', 'B1', 'B2', 'B3', 'S1', 'S2', 'S3', 'P0v3', 'P1v3', 'P2v3'];

    private const SLOT_SKUS = ['S1', 'S2', 'S3', 'P0v3', 'P1v3', 'P2v3'];

    /** @param array<string, mixed> $manifest */
    public static function validate(array $manifest, string $manifestJson = ''): ValidationReport
    {
        $findings = [];

        $findings[] = self::ruleAppName($manifest);
        $findings[] = self::ruleRuntime($manifest);
        $findings[] = self::ruleOperatingSystem($manifest);
        $findings[] = self::ruleRuntimeOsCombination($manifest);
        $findings[] = self::ruleRegion($manifest);
        $findings[] = self::ruleSku($manifest);
        $findings[] = self::ruleSlots($manifest);
        $findings[] = self::ruleHealthCheck($manifest);
        $findings[] = self::ruleHttpsOnly($manifest);
        $findings[] = self::ruleTls($manifest);
        $findings[] = self::ruleAppSettings($manifest);
        $findings[] = self::ruleAlwaysOn($manifest);

        return new ValidationReport($findings, $manifestJson);
    }

    // ------------------------------------------------------------------ rules

    /** @param array<string, mixed> $manifest */
    private static function ruleAppName(array $manifest): Finding
    {
        $value = self::string($manifest, 'appName');

        if ($value === '') {
            return new Finding(Finding::FAIL, 'appName', 'appName is required.', 'Use the App Service name, e.g. clouddeploy-12345678.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$/', $value) !== 1) {
            return new Finding(
                Finding::FAIL,
                'appName',
                'appName must be 3-60 characters of lowercase letters, digits or hyphens.',
                'Azure derives the DNS label from the name, so uppercase letters and underscores are rejected.'
            );
        }

        return new Finding(Finding::PASS, 'appName', sprintf('%s is a valid DNS label.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleRuntime(array $manifest): Finding
    {
        $value = self::string($manifest, 'runtime');

        if ($value === '') {
            return new Finding(Finding::FAIL, 'runtime', 'runtime is required.', 'Example: DOTNETCORE:9.0 or PHP:8.3.');
        }

        if (preg_match('/^(?<stack>[A-Z]+)[|:](?<version>\d+(?:\.\d+)?)$/i', $value, $matches) !== 1) {
            return new Finding(
                Finding::FAIL,
                'runtime',
                sprintf('"%s" is not a runtime identifier.', $value),
                'Use STACK:VERSION, for example DOTNETCORE:9.0 or PHP:8.3.'
            );
        }

        $stack = strtoupper($matches['stack']);

        if (!in_array($stack, self::SUPPORTED_RUNTIMES, true)) {
            return new Finding(
                Finding::FAIL,
                'runtime',
                sprintf('Stack %s is not supported by App Service.', $stack),
                'Supported stacks: ' . implode(', ', self::SUPPORTED_RUNTIMES) . '.'
            );
        }

        return new Finding(Finding::PASS, 'runtime', sprintf('%s %s is a supported runtime.', $stack, $matches['version']));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleOperatingSystem(array $manifest): Finding
    {
        $value = strtolower(self::string($manifest, 'os'));

        if (!in_array($value, ['linux', 'windows'], true)) {
            return new Finding(Finding::FAIL, 'os', 'os must be "linux" or "windows".', 'The hosting OS determines which runtime versions are available.');
        }

        return new Finding(Finding::PASS, 'os', sprintf('Hosting OS is %s.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleRuntimeOsCombination(array $manifest): Finding
    {
        $runtime = strtoupper(self::string($manifest, 'runtime'));
        $os = strtolower(self::string($manifest, 'os'));
        $isPhp83Plus = preg_match('/^PHP[|:](?<v>\d+\.\d+)$/', $runtime, $m) === 1 && version_compare($m['v'], '8.3', '>=');

        if ($isPhp83Plus && $os === 'windows') {
            return new Finding(
                Finding::FAIL,
                'runtime + os',
                'PHP 8.3 is only offered on Linux App Service plans.',
                'Recreate the plan with --is-linux, or target PHP 8.2 on Windows. Mixing these is the most common reason a PHP site returns HTTP 404 or 500 on first deploy.'
            );
        }

        return new Finding(Finding::PASS, 'runtime + os', sprintf('%s and %s are compatible.', $runtime, $os === '' ? 'unknown' : $os));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleRegion(array $manifest): Finding
    {
        $value = strtolower(self::string($manifest, 'region'));

        if ($value === '') {
            return new Finding(Finding::FAIL, 'region', 'region is required.', 'Example: australiaeast.');
        }

        if (!str_starts_with($value, 'australia')) {
            return new Finding(
                Finding::WARN,
                'region',
                sprintf('Region %s is outside Australia.', $value),
                'australiaeast keeps latency low for markers in Australia; the deployment is still valid.'
            );
        }

        return new Finding(Finding::PASS, 'region', sprintf('Region %s is in Australia.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleSku(array $manifest): Finding
    {
        $value = strtoupper(self::string($manifest, 'sku'));

        if ($value === '') {
            return new Finding(Finding::FAIL, 'sku', 'sku is required.', 'F1 is free and sufficient for this assessment.');
        }

        if (!in_array($value, self::SKUS, true)) {
            return new Finding(
                Finding::FAIL,
                'sku',
                sprintf('%s is not a known App Service SKU.', $value),
                'Use one of: ' . implode(', ', self::SKUS) . '.'
            );
        }

        if (in_array($value, ['F1', 'D1'], true)) {
            return new Finding(
                Finding::PASS,
                'sku',
                sprintf('%s is valid for this assessment: $0 cost, no Always On and no slots (see the alwaysOn and slot rules).', $value),
                'Expect a 20 s cold start after the site has been idle; that is a platform property, not a code defect.'
            );
        }

        return new Finding(Finding::PASS, 'sku', sprintf('%s supports Always On and deployment slots.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleSlots(array $manifest): Finding
    {
        $slot = self::string($manifest, 'slot');
        $sku = strtoupper(self::string($manifest, 'sku'));

        if ($slot === '') {
            return new Finding(Finding::PASS, 'slot', 'No deployment slot requested.');
        }

        if (!in_array($sku, self::SLOT_SKUS, true)) {
            return new Finding(
                Finding::FAIL,
                'slot',
                sprintf('Deployment slots require Standard or Premium; %s cannot host slot "%s".', $sku === '' ? 'this SKU' : $sku, $slot),
                'Use SKU S1 or higher, or deploy straight to production for the assessment.'
            );
        }

        return new Finding(Finding::PASS, 'slot', sprintf('Slot "%s" is available on %s.', $slot, $sku));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleHealthCheck(array $manifest): Finding
    {
        $value = self::string($manifest, 'healthCheckPath');

        if ($value === '') {
            return new Finding(
                Finding::FAIL,
                'healthCheckPath',
                'healthCheckPath is required.',
                'Expose a lightweight endpoint such as /health that returns 200 without touching a database.'
            );
        }

        if (!str_starts_with($value, '/')) {
            return new Finding(Finding::FAIL, 'healthCheckPath', 'healthCheckPath must start with "/".', 'Example: /health.');
        }

        if ($value === '/' || strlen($value) > 100) {
            return new Finding(
                Finding::WARN,
                'healthCheckPath',
                'healthCheckPath should be a dedicated endpoint, not the home page.',
                'Using / makes the probe render the full page on every check and can mask slow dependencies.'
            );
        }

        return new Finding(Finding::PASS, 'healthCheckPath', sprintf('Probe endpoint %s is well formed.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleHttpsOnly(array $manifest): Finding
    {
        if (!array_key_exists('httpsOnly', $manifest)) {
            return new Finding(Finding::FAIL, 'httpsOnly', 'httpsOnly is required.', 'Set it to true so plain HTTP is redirected at the platform edge.');
        }

        if ($manifest['httpsOnly'] !== true) {
            return new Finding(
                Finding::FAIL,
                'httpsOnly',
                'httpsOnly must be true.',
                'The assessment requires a publicly accessible HTTPS URL; leaving HTTP open exposes session cookies in clear text.'
            );
        }

        return new Finding(Finding::PASS, 'httpsOnly', 'HTTPS-only is enforced at the platform edge.');
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleTls(array $manifest): Finding
    {
        $value = self::string($manifest, 'minTlsVersion');

        if ($value === '') {
            return new Finding(
                Finding::WARN,
                'minTlsVersion',
                'minTlsVersion is not declared.',
                'App Service defaults to 1.2, but asserting it in the manifest keeps the setting under change control.'
            );
        }

        if (!in_array($value, ['1.2', '1.3'], true)) {
            return new Finding(Finding::FAIL, 'minTlsVersion', 'TLS 1.0/1.1 are deprecated.', 'Set minTlsVersion to 1.2 or 1.3.');
        }

        return new Finding(Finding::PASS, 'minTlsVersion', sprintf('Minimum TLS %s is enforced.', $value));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleAppSettings(array $manifest): Finding
    {
        if (!array_key_exists('appSettings', $manifest)) {
            return new Finding(Finding::WARN, 'appSettings', 'No application settings declared.', 'Student details are supplied as app settings so they never appear in source control.');
        }

        if (!is_array($manifest['appSettings'])) {
            return new Finding(Finding::FAIL, 'appSettings', 'appSettings must be an object of key/value pairs.', 'Example: { "Student__Id": "12345678" }.');
        }

        foreach ($manifest['appSettings'] as $key => $unused) {
            if (preg_match('/(secret|password|pwd|token|apikey|api_key|sas)/i', (string) $key) === 1) {
                return new Finding(
                    Finding::WARN,
                    'appSettings.' . $key,
                    sprintf('"%s" looks like a secret stored as a plain app setting.', $key),
                    'Use a Key Vault reference such as @Microsoft.KeyVault(SecretUri=...) and a managed identity instead.'
                );
            }
        }

        return new Finding(Finding::PASS, 'appSettings', sprintf('%d application setting(s) declared, none look like secrets.', count($manifest['appSettings'])));
    }

    /** @param array<string, mixed> $manifest */
    private static function ruleAlwaysOn(array $manifest): Finding
    {
        $sku = strtoupper(self::string($manifest, 'sku'));
        $alwaysOn = filter_var($manifest['alwaysOn'] ?? false, FILTER_VALIDATE_BOOL);

        if ($alwaysOn && in_array($sku, ['F1', 'D1'], true)) {
            return new Finding(
                Finding::FAIL,
                'alwaysOn',
                sprintf('Always On is not available on %s.', $sku === '' ? 'the Free/Shared tier' : $sku),
                'Remove alwaysOn, or move to B1. On F1 the site unloads after 20 minutes of inactivity.'
            );
        }

        return new Finding(Finding::PASS, 'alwaysOn', $alwaysOn ? 'Always On is enabled.' : 'Always On disabled, which is correct for the Free tier.');
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $manifest */
    private static function string(array $manifest, string $key): string
    {
        $value = $manifest[$key] ?? '';

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
