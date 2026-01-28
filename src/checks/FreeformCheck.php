<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\helpers\LogParser;
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

        $version = $plugin->getVersion();
        $isFreeform4Plus = version_compare($version, '4.0.0', '>=');

        $meta = $isFreeform4Plus ? $this->checkFreeform4() : $this->checkFreeform3();

        if (isset($meta['error'])) {
            return CheckResult::degraded($this->getName(), $meta, $meta['error']);
        }

        $failures = (int) ($meta['failedNotifications'] ?? $meta['logErrors'] ?? 0);

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
