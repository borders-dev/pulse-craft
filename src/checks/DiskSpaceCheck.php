<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\Pulse;
use Craft;

class DiskSpaceCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'disk';
    }

    public function run(): CheckResult
    {
        $path = Craft::getAlias('@storage');
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);

        if ($totalSpace === false || $freeSpace === false || $totalSpace < 1) {
            return CheckResult::unhealthy(
                $this->getName(),
                [],
                'Unable to determine disk space'
            );
        }

        $usedSpace = $totalSpace - $freeSpace;
        $usedPercent = (int) round(($usedSpace / $totalSpace) * 100);

        $settings = Pulse::getInstance()->getSettings();
        $threshold = $settings->diskSpaceThreshold;

        if ($usedPercent >= $threshold) {
            return CheckResult::unhealthy($this->getName(), [
                'usedPercent' => $usedPercent,
                'freeBytes' => $freeSpace,
                'totalBytes' => $totalSpace,
            ], "Disk usage at {$usedPercent}% (threshold: {$threshold}%)");
        }

        if ($threshold > 10 && $usedPercent >= $threshold - 10) {
            return CheckResult::degraded($this->getName(), [
                'usedPercent' => $usedPercent,
                'freeBytes' => $freeSpace,
                'totalBytes' => $totalSpace,
            ], "Disk usage approaching threshold at {$usedPercent}%");
        }

        return CheckResult::healthy($this->getName(), [
            'usedPercent' => $usedPercent,
            'freeBytes' => $freeSpace,
            'totalBytes' => $totalSpace,
        ]);
    }
}
