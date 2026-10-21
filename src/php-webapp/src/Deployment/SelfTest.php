<?php

declare(strict_types=1);

namespace CloudDeploy\Deployment;

use CloudDeploy\Support\Env;

/**
 * Runtime self-tests.
 *
 * The same checks are used by the HTML page and by /health, so the health probe
 * is meaningful: a failing check produces HTTP 503 and App Service removes the
 * instance from rotation instead of sending traffic to a broken worker.
 */
final class SelfTest
{
    public const PASS = 'pass';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /** @return list<array{name: string, status: string, detail: string}> */
    public static function run(): array
    {
        return [
            self::check(
                'PHP version',
                version_compare(PHP_VERSION, '8.2.0', '>=') ? self::PASS : self::FAIL,
                PHP_VERSION . ' (8.2+ required by the source code)'
            ),
            self::check(
                'JSON extension',
                extension_loaded('json') ? self::PASS : self::FAIL,
                extension_loaded('json') ? 'json_encode/decode available' : 'required by /api/info and /validate'
            ),
            self::check(
                'OpenSSL extension',
                extension_loaded('openssl') ? self::PASS : self::WARN,
                extension_loaded('openssl') ? 'TLS for outbound calls available' : 'outbound HTTPS calls would fail'
            ),
            self::check(
                'Multibyte string',
                extension_loaded('mbstring') ? self::PASS : self::WARN,
                extension_loaded('mbstring') ? 'mbstring loaded' : 'non-ASCII student names may be mangled'
            ),
            self::check(
                'Temporary directory writable',
                self::tempIsWritable() ? self::PASS : self::FAIL,
                sprintf('%s (App Service gives each instance its own /tmp)', sys_get_temp_dir())
            ),
            self::check(
                'Errors logged, not displayed',
                ini_get('display_errors') === '0' || ini_get('display_errors') === '' ? self::PASS : self::WARN,
                'display_errors=' . var_export(ini_get('display_errors'), true) . '; production must not leak stack traces'
            ),
            self::check(
                'Node/instance identity',
                Env::inAzure() ? self::PASS : self::WARN,
                Env::inAzure()
                    ? 'running inside ' . Env::get('WEBSITE_SITE_NAME')
                    : 'running locally; WEBSITE_SITE_NAME is not set'
            ),
        ];
    }

    /** @param list<array{name: string, status: string, detail: string}>|null $checks */
    public static function overall(?array $checks = null): string
    {
        $checks ??= self::run();

        foreach ($checks as $check) {
            if ($check['status'] === self::FAIL) {
                return self::FAIL;
            }
        }

        foreach ($checks as $check) {
            if ($check['status'] === self::WARN) {
                return self::WARN;
            }
        }

        return self::PASS;
    }

    /** @return array{name: string, status: string, detail: string} */
    private static function check(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    private static function tempIsWritable(): bool
    {
        $file = @tempnam(sys_get_temp_dir(), 'cd');

        if ($file === false) {
            return false;
        }

        $written = @file_put_contents($file, 'ok') === 2;
        @unlink($file);

        return $written;
    }
}
