<?php

declare(strict_types=1);

namespace CloudDeploy\Support;

/**
 * Typed access to configuration values.
 *
 * Azure App Service exposes its platform metadata as environment variables
 * (WEBSITE_SITE_NAME, REGION_NAME, WEBSITE_SKU, ...). Reading them through a
 * single helper keeps the rest of the code free of raw getenv() calls and makes
 * the "not running in Azure" case explicit instead of silently returning false.
 */
final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);

        if ($value === false || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function inAzure(): bool
    {
        return self::get('WEBSITE_SITE_NAME') !== null;
    }
}
