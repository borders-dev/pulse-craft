<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\Pulse;
use craft\db\Query;
use DateTime;

class FailedLoginsCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'failedLogins';
    }

    public function run(): CheckResult
    {
        $settings = Pulse::getInstance()->getSettings();
        $windowSeconds = $settings->failedLoginWindow;

        $since = (new DateTime())->modify("-{$windowSeconds} seconds");

        $count = (new Query())
            ->from('{{%users}}')
            ->where(['>', 'invalidLoginCount', 0])
            ->andWhere(['>', 'invalidLoginWindowStart', $since->format('Y-m-d H:i:s')])
            ->count();

        $windowHours = round($windowSeconds / 3600, 1);

        if ($count >= 50) {
            return CheckResult::unhealthy($this->getName(), [
                'count' => (int) $count,
                'window' => "{$windowHours}h",
            ], "{$count} failed login attempts in the last {$windowHours} hours");
        }

        if ($count >= 10) {
            return CheckResult::degraded($this->getName(), [
                'count' => (int) $count,
                'window' => "{$windowHours}h",
            ], "{$count} failed login attempts in the last {$windowHours} hours");
        }

        return CheckResult::healthy($this->getName(), [
            'count' => (int) $count,
            'window' => "{$windowHours}h",
        ]);
    }
}
