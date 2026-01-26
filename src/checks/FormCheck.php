<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\Pulse;
use Craft;
use craft\db\Query;
use DateTime;
use Throwable;

class FormCheck implements CheckInterface
{
    private const FORMIE_PLUGIN = 'formie';
    private const FREEFORM_PLUGIN = 'freeform';

    public function getName(): string
    {
        return 'forms';
    }

    public function run(): CheckResult
    {
        $settings = Pulse::getInstance()->getSettings();
        $windowSeconds = $settings->failedLoginWindow;
        $since = (new DateTime())->modify("-{$windowSeconds} seconds");

        $formieData = $this->checkFormie($since);
        $freeformData = $this->checkFreeform($since);

        $meta = [
            'formie' => $formieData,
            'freeform' => $freeformData,
            'window' => round($windowSeconds / 3600, 1) . 'h',
        ];

        $formieFailures = $formieData !== null ? (int) ($formieData['failedNotifications'] ?? 0) : 0;
        $freeformFailures = $freeformData !== null ? (int) ($freeformData['failedNotifications'] ?? 0) : 0;
        $totalFailures = $formieFailures + $freeformFailures;

        if ($totalFailures > 10) {
            return CheckResult::unhealthy(
                $this->getName(),
                $meta,
                "{$totalFailures} failed notification(s) detected"
            );
        }

        if ($totalFailures > 0) {
            return CheckResult::degraded(
                $this->getName(),
                $meta,
                "{$totalFailures} failed notification(s) detected"
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }

    private function checkFormie(DateTime $since): ?array
    {
        if (!Craft::$app->getPlugins()->isPluginInstalled(self::FORMIE_PLUGIN)) {
            return null;
        }

        try {
            $failedNotifications = (new Query())
                ->from('{{%formie_sentnotifications}}')
                ->where(['success' => false])
                ->andWhere(['>=', 'dateCreated', $since->format('Y-m-d H:i:s')])
                ->count();

            return [
                'installed' => true,
                'failedNotifications' => (int) $failedNotifications,
            ];
        } catch (Throwable) {
            return [
                'installed' => true,
                'error' => 'Unable to query Formie data',
            ];
        }
    }

    private function checkFreeform(DateTime $since): ?array
    {
        if (!Craft::$app->getPlugins()->isPluginInstalled(self::FREEFORM_PLUGIN)) {
            return null;
        }

        try {
            $failedNotifications = (new Query())
                ->from('{{%freeform_notifications_log}}')
                ->where(['success' => false])
                ->andWhere(['>=', 'dateCreated', $since->format('Y-m-d H:i:s')])
                ->count();

            return [
                'installed' => true,
                'failedNotifications' => (int) $failedNotifications,
            ];
        } catch (Throwable) {
            return [
                'installed' => true,
                'error' => 'Unable to query Freeform data',
            ];
        }
    }
}
