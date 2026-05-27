<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
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

            $meta = [
                'devMode' => $devMode,
                'allowAdminChanges' => $allowAdminChanges,
            ];

            if ($devMode) {
                return CheckResult::degraded($this->getName(), $meta, 'Dev mode is enabled');
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge debugMode check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }
}
