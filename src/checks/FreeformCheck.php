<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use ledgehq\craftledge\Ledge;
use Solspace\Freeform\Freeform;
use Throwable;

class FreeformCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'freeform';
    }

    public function run(): ?CheckResult
    {
        $plugin = Craft::$app->getPlugins()->getPlugin('freeform');
        if ($plugin === null || !class_exists(Freeform::class)) {
            return null;
        }

        try {
            $freeform = Freeform::getInstance();
            if ($freeform === null) {
                return CheckResult::degraded(
                    $this->getName(),
                    ['installed' => true],
                    'Freeform instance unavailable'
                );
            }
            $logger = $freeform->logger;

            $errorCount = $logger->getCombinedLogLineCount(['error']);
            $settings = Ledge::currentSettings();
            $degradedAt = $settings->freeformDegradedAt;
            $unhealthyAt = $settings->freeformUnhealthyAt;

            $meta = [
                'installed' => true,
                'errorLogCount' => $logger->getLogReader()->count(),
                'integrationsLogCount' => $logger->getLogReader('freeform-integrations.log')->count(),
                'emailLogCount' => $logger->getLogReader('freeform-email.log')->count(),
                'errors' => $errorCount,
                'thresholds' => Thresholds::describe($degradedAt, $unhealthyAt),
            ];
        } catch (Throwable) {
            return CheckResult::degraded($this->getName(), [
                'installed' => true,
                'error' => 'Unable to read Freeform logs',
            ], 'Unable to read Freeform logs');
        }

        $status = Thresholds::status($errorCount, $degradedAt, $unhealthyAt);
        $output = $status === CheckResult::STATUS_HEALTHY ? null : "{$errorCount} Freeform error(s) logged";

        return new CheckResult($this->getName(), $status, $meta, $output);
    }
}
