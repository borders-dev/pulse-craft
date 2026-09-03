<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use craft\db\Query;
use DateTime;
use ledgehq\craftledge\Ledge;
use Throwable;

class FailedLoginsCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'failedLogins';
    }

    public function run(): ?CheckResult
    {
        try {
            $settings = Ledge::currentSettings();
            $windowSeconds = $settings->failedLoginsWindow;
            $degradedAt = $settings->failedLoginsDegradedAt;
            $unhealthyAt = $settings->failedLoginsUnhealthyAt;

            $since = (new DateTime())->modify("-{$windowSeconds} seconds");

            $count = (int) (new Query())
                ->from('{{%users}}')
                ->where(['>', 'invalidLoginCount', 0])
                ->andWhere(['>', 'invalidLoginWindowStart', $since->format('Y-m-d H:i:s')])
                ->count();

            $windowHours = round($windowSeconds / 3600, 1);
            $meta = [
                'count' => $count,
                'window' => "{$windowHours}h",
                'thresholds' => Thresholds::describe($degradedAt, $unhealthyAt) + ['windowSeconds' => $windowSeconds],
            ];

            $status = Thresholds::status($count, $degradedAt, $unhealthyAt);
            $output = $status === CheckResult::STATUS_HEALTHY ? null : "{$count} failed login attempts in the last {$windowHours} hours";

            return new CheckResult($this->getName(), $status, $meta, $output);
        } catch (Throwable $e) {
            Craft::error('Ledge failedLogins check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }
}
