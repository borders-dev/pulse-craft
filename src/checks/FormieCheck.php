<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;
use craft\db\Query;
use Throwable;

class FormieCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'formie';
    }

    public function run(): CheckResult
    {
        if (!Craft::$app->getPlugins()->isPluginInstalled('formie')) {
            return CheckResult::healthy($this->getName(), ['installed' => false]);
        }

        try {
            $failedNotifications = (new Query())
                ->from('{{%formie_sentnotifications}}')
                ->where(['success' => false])
                ->count();

            $meta = [
                'installed' => true,
                'failedNotifications' => (int) $failedNotifications,
            ];

            $count = (int) $failedNotifications;

            if ($count > 10) {
                return CheckResult::unhealthy(
                    $this->getName(),
                    $meta,
                    "{$count} failed notification(s) detected"
                );
            }

            if ($count > 0) {
                return CheckResult::degraded(
                    $this->getName(),
                    $meta,
                    "{$count} failed notification(s) detected"
                );
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable) {
            return CheckResult::degraded($this->getName(), [
                'installed' => true,
                'error' => 'Unable to query Formie data',
            ], 'Unable to query Formie data');
        }
    }
}
