<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

class MemoryCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'memory';
    }

    public function run(): ?CheckResult
    {
        $memoryLimit = $this->getMemoryLimitBytes();
        $memoryUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        if ($memoryLimit <= 0) {
            return CheckResult::healthy($this->getName(), [
                'usageBytes' => $memoryUsage,
                'peakBytes' => $peakUsage,
                'limitBytes' => null,
                'usedPercent' => null,
            ]);
        }

        $usedPercent = (int) round(($memoryUsage / $memoryLimit) * 100);

        if ($usedPercent >= 90) {
            return CheckResult::unhealthy($this->getName(), [
                'usageBytes' => $memoryUsage,
                'peakBytes' => $peakUsage,
                'limitBytes' => $memoryLimit,
                'usedPercent' => $usedPercent,
            ], "Memory usage at {$usedPercent}%");
        }

        if ($usedPercent >= 75) {
            return CheckResult::degraded($this->getName(), [
                'usageBytes' => $memoryUsage,
                'peakBytes' => $peakUsage,
                'limitBytes' => $memoryLimit,
                'usedPercent' => $usedPercent,
            ], "Memory usage at {$usedPercent}%");
        }

        return CheckResult::healthy($this->getName(), [
            'usageBytes' => $memoryUsage,
            'peakBytes' => $peakUsage,
            'limitBytes' => $memoryLimit,
            'usedPercent' => $usedPercent,
        ]);
    }

    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1' || $limit === '') {
            return -1;
        }

        if (strlen($limit) < 2) {
            return (int) $limit;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
