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

            $settings = Ledge::getInstance()->getSettings();
            $threshold = $settings->diskSpaceThreshold;

            $meta = [
                'usedPercent' => $usedPercent,
                'freeBytes' => $freeSpace,
                'totalBytes' => $totalSpace,
            ];

            if ($usedPercent >= $threshold) {
                return CheckResult::unhealthy($this->getName(), $meta, "Disk usage at {$usedPercent}% (threshold: {$threshold}%)");
            }

            if ($threshold > 10 && $usedPercent >= $threshold - 10) {
                return CheckResult::degraded($this->getName(), $meta, "Disk usage approaching threshold at {$usedPercent}%");
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge disk check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }
}
