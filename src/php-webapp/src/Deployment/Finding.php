<?php

declare(strict_types=1);

namespace CloudDeploy\Deployment;

/**
 * The outcome of a single rule in the deployment manifest validator.
 */
final class Finding
{
    public const PASS = 'pass';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    public function __construct(
        public readonly string $level,
        public readonly string $field,
        public readonly string $message,
        public readonly string $remedy = ''
    ) {
    }

    public function isBlocking(): bool
    {
        return $this->level === self::FAIL;
    }

    public function label(): string
    {
        return match ($this->level) {
            self::PASS => 'PASS',
            self::WARN => 'WARN',
            default => 'FAIL',
        };
    }
}
