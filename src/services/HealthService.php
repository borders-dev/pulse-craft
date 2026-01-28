<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\services;

use bordersdev\craftpulse\checks\CheckInterface;
use bordersdev\craftpulse\checks\CheckResult;
use bordersdev\craftpulse\checks\CraftVersionCheck;
use bordersdev\craftpulse\checks\DatabaseCheck;
use bordersdev\craftpulse\checks\DebugModeCheck;
use bordersdev\craftpulse\checks\DiskSpaceCheck;
use bordersdev\craftpulse\checks\EnvironmentCheck;
use bordersdev\craftpulse\checks\FailedLoginsCheck;
use bordersdev\craftpulse\checks\FormieCheck;
use bordersdev\craftpulse\checks\FreeformCheck;
use bordersdev\craftpulse\checks\LicenseCheck;
use bordersdev\craftpulse\checks\MemoryCheck;
use bordersdev\craftpulse\checks\PluginVersionsCheck;
use bordersdev\craftpulse\checks\QueueCheck;
use bordersdev\craftpulse\Pulse;
use yii\base\Component;

class HealthService extends Component
{
    /** @var CheckInterface[] */
    private array $checks = [];

    public function init(): void
    {
        parent::init();

        $this->registerCheck(new DatabaseCheck());
        $this->registerCheck(new QueueCheck());
        $this->registerCheck(new DiskSpaceCheck());
        $this->registerCheck(new MemoryCheck());
        $this->registerCheck(new CraftVersionCheck());
        $this->registerCheck(new PluginVersionsCheck());
        $this->registerCheck(new DebugModeCheck());
        $this->registerCheck(new FailedLoginsCheck());
        $this->registerCheck(new LicenseCheck());
        $this->registerCheck(new EnvironmentCheck());
        $this->registerCheck(new FormieCheck());
        $this->registerCheck(new FreeformCheck());
    }

    public function registerCheck(CheckInterface $check): void
    {
        $this->checks[$check->getName()] = $check;
    }

    public function runChecks(): array
    {
        $settings = Pulse::getInstance()->getSettings();
        $enabledChecks = $settings->enabledChecks;

        $results = [];
        $overallStatus = CheckResult::STATUS_HEALTHY;

        foreach ($this->checks as $name => $check) {
            if (!($enabledChecks[$name] ?? true)) {
                continue;
            }

            $result = $check->run();
            $results[$name] = $result->toArray();

            $overallStatus = $this->determineOverallStatus($overallStatus, $result->status);
        }

        return [
            'status' => $overallStatus,
            'checks' => $results,
        ];
    }

    private function determineOverallStatus(string $current, string $new): string
    {
        $priority = [
            CheckResult::STATUS_HEALTHY => 0,
            CheckResult::STATUS_DEGRADED => 1,
            CheckResult::STATUS_UNHEALTHY => 2,
        ];

        return $priority[$new] > $priority[$current] ? $new : $current;
    }
}
