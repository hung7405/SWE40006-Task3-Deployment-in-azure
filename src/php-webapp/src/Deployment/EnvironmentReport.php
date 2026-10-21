<?php

declare(strict_types=1);

namespace CloudDeploy\Deployment;

use CloudDeploy\Support\Env;

/**
 * Builds the "what am I running on?" view model.
 *
 * Every value is read at request time from the live process and from the
 * environment variables injected by Azure App Service, which is what makes the
 * rendered page usable as deployment evidence rather than a static mock-up.
 */
final class EnvironmentReport
{
    /** @return list<array{title: string, rows: list<array{label: string, value: string, mono: bool}>}> */
    public static function sections(): array
    {
        return [
            [
                'title' => 'Deployment identity',
                'rows' => [
                    self::row('Application', Env::get('APP_NAME', 'CloudDeploy PHP')),
                    self::row('Version', Env::get('APP_VERSION', '1.0.0')),
                    self::row('Student', sprintf('%s (%s)', Env::get('STUDENT_NAME', 'Student Name'), Env::get('STUDENT_ID', 'Student ID'))),
                    self::row('Unit', Env::get('UNIT_CODE', 'SWE40006')),
                    self::row('Assessment', 'Deployment Portfolio Task 3'),
                    self::row('Level attempted', 'HD (sub-tasks 3.1, 3.2 and 3.3)'),
                ],
            ],
            [
                'title' => 'Azure App Service',
                'rows' => [
                    self::row('Site name', Env::get('WEBSITE_SITE_NAME', 'not running in Azure')),
                    self::row('Public host', Env::get('WEBSITE_HOSTNAME', 'localhost')),
                    self::row('Region', Env::get('REGION_NAME', 'n/a')),
                    self::row('Plan SKU', Env::get('WEBSITE_SKU', 'n/a')),
                    self::row('Slot', Env::get('WEBSITE_SLOT_NAME', 'production')),
                    self::row('Instance id', Env::get('WEBSITE_INSTANCE_ID', 'n/a'), true),
                    self::row('Home directory', Env::get('HOME', getcwd() ?: 'unknown'), true),
                    self::row('Run from package', Env::get('WEBSITE_RUN_FROM_PACKAGE', '0'), true),
                ],
            ],
            [
                'title' => 'PHP runtime',
                'rows' => [
                    self::row('PHP version', PHP_VERSION),
                    self::row('SAPI', PHP_SAPI),
                    self::row('Operating system', PHP_OS . ' ' . php_uname('m')),
                    self::row('Loaded extensions', (string) count(get_loaded_extensions())),
                    self::row('Memory limit', (string) ini_get('memory_limit')),
                    self::row('Max execution time', (string) ini_get('max_execution_time')),
                    self::row('Opcache', self::opcacheState()),
                    self::row('Timezone', date_default_timezone_get()),
                    self::row('Intl / mbstring', (extension_loaded('intl') ? 'intl ' : '') . (extension_loaded('mbstring') ? 'mbstring' : '')),
                ],
            ],
            [
                'title' => 'Testable endpoints',
                'rows' => [
                    self::row('Health probe', '/health', true),
                    self::row('Environment API', '/api/info', true),
                    self::row('Manifest validator', '/validate', true),
                    self::row('Server time (UTC)', gmdate('Y-m-d H:i:s')),
                    self::row('Request id', self::requestId(), true),
                ],
            ],
        ];
    }

    /** @return array{label: string, value: string, mono: bool} */
    private static function row(string $label, string $value, bool $mono = false): array
    {
        return ['label' => $label, 'value' => $value, 'mono' => $mono];
    }

    private static function opcacheState(): string
    {
        if (!function_exists('opcache_get_status')) {
            return 'not installed';
        }

        $status = @opcache_get_status(false);

        if (!is_array($status)) {
            return 'installed, disabled';
        }

        return !empty($status['opcache_enabled']) ? 'enabled' : 'disabled';
    }

    private static function requestId(): string
    {
        return Env::get('HTTP_X_ARR_LOG_ID')
            ?? Env::get('WEBSITE_INSTANCE_ID', 'local')
            . '-' . substr(sha1((string) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))), 0, 8);
    }

    /** @return array<string, string|null> */
    public static function toArray(): array
    {
        return [
            'appName' => Env::get('APP_NAME', 'CloudDeploy PHP'),
            'version' => Env::get('APP_VERSION', '1.0.0'),
            'studentName' => Env::get('STUDENT_NAME', 'Student Name'),
            'studentId' => Env::get('STUDENT_ID', 'Student ID'),
            'unitCode' => Env::get('UNIT_CODE', 'SWE40006'),
            'phpVersion' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'websiteHostName' => Env::get('WEBSITE_HOSTNAME'),
            'siteName' => Env::get('WEBSITE_SITE_NAME'),
            'region' => Env::get('REGION_NAME'),
            'sku' => Env::get('WEBSITE_SKU'),
            'instanceId' => Env::get('WEBSITE_INSTANCE_ID'),
            'slot' => Env::get('WEBSITE_SLOT_NAME'),
            'serverTimeUtc' => gmdate('c'),
            'inAzure' => Env::inAzure() ? 'true' : 'false',
        ];
    }
}
