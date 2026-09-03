<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use craft\db\Query;
use ledgehq\craftledge\Ledge;
use Throwable;

class FormieCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'formie';
    }

    public function run(): ?CheckResult
    {
        if (!Craft::$app->getPlugins()->isPluginInstalled('formie')) {
            return null;
        }

        try {
            $failedNotifications = (new Query())
                ->from(['sn' => '{{%formie_sentnotifications}}'])
                ->innerJoin(['e' => '{{%elements}}'], '[[e.id]] = [[sn.id]]')
                ->where(['sn.success' => false])
                ->andWhere(['e.dateDeleted' => null])
                ->count();

            $settings = Ledge::currentSettings();
            $degradedAt = $settings->formieDegradedAt;
            $unhealthyAt = $settings->formieUnhealthyAt;
            $count = (int) $failedNotifications;

            $meta = [
                'installed' => true,
                'failedNotifications' => $count,
                'thresholds' => Thresholds::describe($degradedAt, $unhealthyAt),
            ];

            $status = Thresholds::status($count, $degradedAt, $unhealthyAt);
            $output = $status === CheckResult::STATUS_HEALTHY ? null : "{$count} failed notification(s) detected";

            return new CheckResult($this->getName(), $status, $meta, $output);
        } catch (Throwable) {
            return CheckResult::degraded($this->getName(), [
                'installed' => true,
                'error' => 'Unable to query Formie data',
            ], 'Unable to query Formie data');
        }
    }
}
