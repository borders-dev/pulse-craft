<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;
use craft\db\Query;
use Throwable;

class FreeformCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'freeform';
    }

    public function run(): CheckResult
    {
        $plugin = Craft::$app->getPlugins()->getPlugin('freeform');
        if ($plugin === null) {
            return CheckResult::healthy($this->getName(), ['installed' => false]);
        }

        try {
            $failedNotifications = (new Query())
                ->from('{{%freeform_notifications_log}}')
                ->where(['success' => false])
                ->count();

            $meta = [
                'installed' => true,
                'failedNotifications' => (int) $failedNotifications,
            ];
        } catch (Throwable) {
            return CheckResult::degraded($this->getName(), [
                'installed' => true,
                'error' => 'Unable to query Freeform data',
            ], 'Unable to query Freeform data');
        }

        $failures = $meta['failedNotifications'];

        if ($failures > 10) {
            return CheckResult::unhealthy(
                $this->getName(),
                $meta,
                "{$failures} failed notification(s) detected"
            );
        }

        if ($failures > 0) {
            return CheckResult::degraded(
                $this->getName(),
                $meta,
                "{$failures} failed notification(s) detected"
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }
}
