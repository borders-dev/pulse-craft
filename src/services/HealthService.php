<?php

declare(strict_types=1);

namespace ledgehq\craftledge\services;

use Craft;
use ledgehq\craftledge\checks\CheckInterface;
use ledgehq\craftledge\checks\CheckResult;
use ledgehq\craftledge\checks\CraftVersionCheck;
use ledgehq\craftledge\checks\DatabaseCheck;
use ledgehq\craftledge\checks\DebugModeCheck;
use ledgehq\craftledge\checks\DiskSpaceCheck;
use ledgehq\craftledge\checks\EnvironmentCheck;
use ledgehq\craftledge\checks\FailedLoginsCheck;
use ledgehq\craftledge\checks\FormieCheck;
use ledgehq\craftledge\checks\FreeformCheck;
use ledgehq\craftledge\checks\LicenseCheck;
use ledgehq\craftledge\checks\MemoryCheck;
use ledgehq\craftledge\checks\PluginVersionsCheck;
use ledgehq\craftledge\checks\QueueCheck;
use ledgehq\craftledge\Ledge;
use Throwable;
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
        $settings = Ledge::getInstance()->getSettings();
        $enabledChecks = $settings->enabledChecks;

        $results = [];
        $overallStatus = CheckResult::STATUS_HEALTHY;

        foreach ($this->checks as $name => $check) {
            if (!($enabledChecks[$name] ?? true)) {
                continue;
            }

            try {
                $result = $check->run();
            } catch (Throwable $e) {
                Craft::error("Ledge check '{$name}' threw: " . $e->getMessage(), __METHOD__);
                $result = CheckResult::unhealthy($name, [], 'Check threw an exception');
            }

            if ($result === null) {
                continue;
            }

            $results[$name] = $result->toArray();
            $overallStatus = $this->determineOverallStatus($overallStatus, $result->status);
        }

        return [
            'status' => $overallStatus,
            'configVersion' => $this->getConfigVersion(),
            'checks' => $results,
        ];
    }

    private function getConfigVersion(): ?string
    {
        try {
            return Craft::$app->getInfo()->configVersion;
        } catch (Throwable) {
            return null;
        }
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
