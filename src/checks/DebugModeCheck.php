<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;

class DebugModeCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'debugMode';
    }

    public function run(): ?CheckResult
    {
        $devMode = Craft::$app->getConfig()->getGeneral()->devMode;
        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        if ($devMode) {
            return CheckResult::degraded($this->getName(), [
                'devMode' => true,
                'allowAdminChanges' => $allowAdminChanges,
            ], 'Dev mode is enabled');
        }

        return CheckResult::healthy($this->getName(), [
            'devMode' => false,
            'allowAdminChanges' => $allowAdminChanges,
        ]);
    }
}
