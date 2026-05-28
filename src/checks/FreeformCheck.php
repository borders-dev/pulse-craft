<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
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
            $logger = Freeform::getInstance()->logger;

            $errorCount = $logger->getCombinedLogLineCount(['error']);

            $meta = [
                'installed' => true,
                'errorLogCount' => $logger->getLogReader()->count(),
                'integrationsLogCount' => $logger->getLogReader('freeform-integrations.log')->count(),
                'emailLogCount' => $logger->getLogReader('freeform-email.log')->count(),
                'errors' => $errorCount,
            ];
        } catch (Throwable) {
            return CheckResult::degraded($this->getName(), [
                'installed' => true,
                'error' => 'Unable to read Freeform logs',
            ], 'Unable to read Freeform logs');
        }

        if ($errorCount > 10) {
            return CheckResult::unhealthy(
                $this->getName(),
                $meta,
                "{$errorCount} Freeform error(s) logged"
            );
        }

        if ($errorCount > 0) {
            return CheckResult::degraded(
                $this->getName(),
                $meta,
                "{$errorCount} Freeform error(s) logged"
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }
}
