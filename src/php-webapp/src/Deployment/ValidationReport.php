<?php

declare(strict_types=1);

namespace CloudDeploy\Deployment;

/**
 * Aggregated result of a manifest validation run.
 */
final class ValidationReport
{
    /** @param list<Finding> $findings */
    public function __construct(
        public readonly array $findings,
        public readonly string $manifestJson = ''
    ) {
    }

    public function count(string $level): int
    {
        return count(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->level === $level
        ));
    }

    public function status(): string
    {
        if ($this->count(Finding::FAIL) > 0) {
            return 'FAIL';
        }

        return $this->count(Finding::WARN) > 0 ? 'WARN' : 'PASS';
    }

    public function score(): int
    {
        $total = count($this->findings);

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->count(Finding::PASS) / $total * 100);
    }
}
