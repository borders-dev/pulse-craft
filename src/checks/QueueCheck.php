<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use bordersdev\craftpulse\Pulse;
use Craft;
use craft\queue\Queue;
use Throwable;

class QueueCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'queue';
    }

    public function run(): ?CheckResult
    {
        try {
            $queue = Craft::$app->getQueue();

            if (!$queue instanceof Queue) {
                return CheckResult::healthy($this->getName(), [
                    'pending' => 0,
                    'output' => 'Non-standard queue driver in use',
                ]);
            }

            $jobInfo = $queue->getJobInfo();
            $pending = count($jobInfo);
            $settings = Pulse::getInstance()->getSettings();
            $stuckThreshold = $settings->queueStuckThreshold;

            $stuck = 0;
            $now = time();

            foreach ($jobInfo as $job) {
                if (isset($job['timePushed']) && ($now - $job['timePushed']) > $stuckThreshold) {
                    $stuck++;
                }
            }

            $failed = $queue->getTotalFailed();

            if ($stuck > 0 || $failed > 0) {
                $messages = [];
                if ($stuck > 0) {
                    $messages[] = "$stuck job(s) stuck for more than " . ($stuckThreshold / 60) . " minutes";
                }
                if ($failed > 0) {
                    $messages[] = "$failed failed job(s)";
                }

                return CheckResult::unhealthy($this->getName(), [
                    'pending' => $pending,
                    'stuck' => $stuck,
                    'failed' => $failed,
                ], implode('; ', $messages));
            }

            return CheckResult::healthy($this->getName(), [
                'pending' => $pending,
                'stuck' => 0,
                'failed' => $failed,
            ]);
        } catch (Throwable $e) {
            return CheckResult::unhealthy(
                $this->getName(),
                [],
                'Queue check failed: ' . $e->getMessage()
            );
        }
    }
}
