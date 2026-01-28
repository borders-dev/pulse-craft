<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\helpers\LogParser;
use Craft;
use craft\db\Query;
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
        $formieData = $this->checkFormie();
        $freeformData = $this->checkFreeform();

        $meta = [
            'formie' => $formieData,
            'freeform' => $freeformData,
        ];

        $formieFailures = $formieData !== null ? (int) ($formieData['failedNotifications'] ?? 0) : 0;
        $freeformFailures = $freeformData !== null
            ? (int) ($freeformData['failedNotifications'] ?? $freeformData['logErrors'] ?? 0)
            : 0;
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

    private function checkFormie(): ?array
    {
        if (!Craft::$app->getPlugins()->isPluginInstalled(self::FORMIE_PLUGIN)) {
            return null;
        }

        try {
            $failedNotifications = (new Query())
                ->from('{{%formie_sentnotifications}}')
                ->where(['success' => false])
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

    private function checkFreeform(): ?array
    {
        $plugin = Craft::$app->getPlugins()->getPlugin(self::FREEFORM_PLUGIN);
        if ($plugin === null) {
            return null;
        }

        $version = $plugin->getVersion();
        $isFreeform4Plus = version_compare($version, '4.0.0', '>=');

        if ($isFreeform4Plus) {
            return $this->checkFreeform4();
        }

        return $this->checkFreeform3();
    }

    private function checkFreeform4(): array
    {
        try {
            $failedNotifications = (new Query())
                ->from('{{%freeform_notifications_log}}')
                ->where(['success' => false])
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

    private function checkFreeform3(): array
    {
        try {
            /** @phpstan-ignore-next-line */
            $freeform = \Solspace\Freeform\Freeform::getInstance();
            $logReader = $freeform->logger->getLogReader();
            $errorCount = $logReader->count();

            $result = [
                'installed' => true,
                'logErrors' => $errorCount,
            ];

            if ($errorCount > 0) {
                $result['errors'] = LogParser::parseMany($logReader->getLastLines(20));
            }

            return $result;
        } catch (Throwable) {
            return [
                'installed' => true,
                'error' => 'Unable to query Freeform data',
            ];
        }
    }
}
