<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use ledgehq\craftledge\Ledge;
use Craft;
use craft\helpers\DateTimeHelper;
use craft\queue\Queue;
use Throwable;
use yii\db\Query;

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

            $pending = count($queue->getJobInfo());
            $failed = $queue->getTotalFailed();
            $settings = Ledge::getInstance()->getSettings();
            $ageThreshold = $settings->queueAgeThreshold;
            $cutoff = DateTimeHelper::currentTimeStamp() - $ageThreshold;

            $stale = (int)(new Query())
                ->from($queue->tableName)
                ->where(['channel' => $queue->channel, 'fail' => false, 'timeUpdated' => null])
                ->andWhere('[[timePushed]] + [[delay]] <= :cutoff', [':cutoff' => $cutoff])
                ->count();

            $meta = [
                'pending' => $pending,
                'failed' => $failed,
                'stale' => $stale,
            ];

            if ($failed > 0) {
                return CheckResult::unhealthy($this->getName(), $meta, "$failed failed job(s)");
            }

            if ($stale > 0) {
                $minutes = (int)($ageThreshold / 60);
                return CheckResult::unhealthy($this->getName(), $meta, "$stale job(s) waiting longer than $minutes minutes");
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            return CheckResult::unhealthy(
                $this->getName(),
                [],
                'Queue check failed: ' . $e->getMessage()
            );
        }
    }
}
