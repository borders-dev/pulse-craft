<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;
use Throwable;

class DatabaseCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function run(): ?CheckResult
    {
        $start = microtime(true);

        try {
            Craft::$app->getDb()->createCommand('SELECT 1')->queryScalar();
            $responseTime = round((microtime(true) - $start) * 1000);

            return CheckResult::healthy($this->getName(), [
                'responseTime' => $responseTime,
            ]);
        } catch (Throwable $e) {
            return CheckResult::unhealthy(
                $this->getName(),
                [],
                'Database connection failed: ' . $e->getMessage()
            );
        }
    }
}
