<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use ledgehq\craftledge\Ledge;
use Throwable;

class DiskSpaceCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'disk';
    }

    public function run(): ?CheckResult
    {
        try {
            $path = Craft::getAlias('@storage');
            $totalSpace = @disk_total_space($path);
            $freeSpace = @disk_free_space($path);

            if ($totalSpace === false || $freeSpace === false || $totalSpace < 1) {
                return CheckResult::unhealthy(
                    $this->getName(),
                    [],
                    'Unable to determine disk space'
                );
            }

            $usedSpace = $totalSpace - $freeSpace;
            $usedPercent = (int) round(($usedSpace / $totalSpace) * 100);

            $settings = Ledge::currentSettings();
            $degradedAt = $settings->diskDegradedAt;
            $unhealthyAt = $settings->diskUnhealthyAt;
            $minFreeBytes = $settings->diskMinFreeBytes;

            $meta = [
                'usedPercent' => $usedPercent,
                'freeBytes' => $freeSpace,
                'totalBytes' => $totalSpace,
                'thresholds' => Thresholds::describe($degradedAt, $unhealthyAt) + ['minFreeBytes' => $minFreeBytes],
            ];

            if ($minFreeBytes !== null && $freeSpace < $minFreeBytes) {
                return CheckResult::unhealthy($this->getName(), $meta, "Only {$freeSpace} bytes free (minimum: {$minFreeBytes})");
            }

            $status = Thresholds::status($usedPercent, $degradedAt, $unhealthyAt);

            return match ($status) {
                CheckResult::STATUS_UNHEALTHY => CheckResult::unhealthy($this->getName(), $meta, "Disk usage at {$usedPercent}% (threshold: {$unhealthyAt}%)"),
                CheckResult::STATUS_DEGRADED => CheckResult::degraded($this->getName(), $meta, "Disk usage approaching threshold at {$usedPercent}%"),
                default => CheckResult::healthy($this->getName(), $meta),
            };
        } catch (Throwable $e) {
            Craft::error('Ledge disk check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }
}
