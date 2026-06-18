<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use ledgehq\craftledge\Ledge;
use Craft;
use craft\helpers\DateTimeHelper;
use craft\queue\Queue;
use Throwable;
use yii\db\Expression;
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
            $now = DateTimeHelper::currentTimeStamp();
            $cutoff = $now - $ageThreshold;

            $stale = (int)(new Query())
                ->from($queue->tableName)
                ->where(['channel' => $this->resolveChannel($queue), 'fail' => false])
                ->andWhere('[[timePushed]] + [[delay]] <= :cutoff', [':cutoff' => $cutoff])
                ->andWhere([
                    'or',
                    ['timeUpdated' => null],
                    new Expression('[[timeUpdated]] + [[ttr]] <= :now', [':now' => $now]),
                ])
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
            Craft::error('Ledge queue check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::unhealthy(
                $this->getName(),
                [],
                'Queue check failed'
            );
        }
    }

    /**
     * Resolves the `channel` column value Craft actually stores jobs under.
     *
     * Craft\queue\Queue::$channel is null by default and its real channel is
     * resolved internally via a private channel()/componentId() that falls back
     * to the queue's application component ID. Reading the public $channel
     * property directly would yield null and match no rows.
     */
    private function resolveChannel(Queue $queue): string
    {
        if ($queue->channel !== null) {
            return $queue->channel;
        }

        foreach (Craft::$app->getComponents(false) as $id => $component) {
            if ($component === $queue) {
                return $id;
            }
        }

        return 'queue';
    }
}
