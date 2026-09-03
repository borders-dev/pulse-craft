<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use ledgehq\craftledge\Ledge;
use Throwable;

class DebugModeCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'debugMode';
    }

    public function run(): ?CheckResult
    {
        try {
            $general = Craft::$app->getConfig()->getGeneral();
            $devMode = $general->devMode;
            $allowAdminChanges = $general->allowAdminChanges;

            $devModeStatus = Ledge::currentSettings()->devModeStatus;

            $meta = [
                'devMode' => $devMode,
                'allowAdminChanges' => $allowAdminChanges,
                'thresholds' => ['devModeStatus' => $devModeStatus],
            ];

            if ($devMode && $devModeStatus !== CheckResult::STATUS_HEALTHY) {
                return new CheckResult($this->getName(), $devModeStatus, $meta, 'Dev mode is enabled');
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge debugMode check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }
}
