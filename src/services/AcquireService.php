<?php

declare(strict_types=1);

namespace ledgehq\craftledge\services;

use Craft;
use craft\queue\Queue;
use ledgehq\craftledge\acquire\AcquireCommand;
use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\CallbackClient;
use ledgehq\craftledge\acquire\CommandVerifier;
use ledgehq\craftledge\acquire\HostAllowlist;
use ledgehq\craftledge\acquire\KeyResolver;
use ledgehq\craftledge\jobs\AcquireBundleJob;
use ledgehq\craftledge\Ledge;
use ledgehq\craftledge\records\AcquisitionRecord;
use Throwable;
use yii\base\Component;
use yii\db\IntegrityException;

class AcquireService extends Component
{
    public function accept(string $rawBody): AcquireCommand
    {
        $settings = Ledge::getInstance()->getSettings();

        $resolver = new KeyResolver(
            Craft::createGuzzleClient(),
            Craft::$app->getCache(),
            $settings->getLedgeBaseUrl(),
        );

        $allowlist = new HostAllowlist(
            $settings->getAcquireAllowedHosts(),
            Craft::$app->getConfig()->getGeneral()->devMode,
        );

        $command = (new CommandVerifier($resolver->resolve(...), $allowlist))->verify($rawBody);

        $this->createRecord($command);

        try {
            $this->queueJob($command, $settings->acquireJobTtr);
        } catch (Throwable $e) {
            AcquisitionRecord::deleteAll(['runId' => $command->runId]);
            throw new AcquireException('queue_unavailable', 500, $e->getMessage());
        }

        $this->createCallbackClient($command->callbackUrl, $command->callbackToken)
            ->send(CallbackClient::EVENT_ACCEPTED, 'queued');

        return $command;
    }

    public function getStatus(string $runId): ?array
    {
        $record = AcquisitionRecord::findOne(['runId' => $runId]);

        if ($record === null) {
            return null;
        }

        return [
            'run_id' => $record->runId,
            'status' => $record->status,
            'step' => $record->step,
            'detail' => $record->detail,
            'profile' => $record->profile,
            'size' => $record->sizeBytes !== null ? (int)$record->sizeBytes : null,
            'sha256' => $record->sha256,
            'dateCreated' => $record->dateCreated,
            'dateUpdated' => $record->dateUpdated,
        ];
    }

    public function transition(
        string $runId,
        string $status,
        ?string $step = null,
        ?string $detail = null,
        ?int $sizeBytes = null,
        ?string $sha256 = null,
    ): void {
        $record = AcquisitionRecord::findOne(['runId' => $runId]);

        if ($record === null) {
            return;
        }

        $record->status = $status;
        $record->step = $step;
        $record->detail = $detail;

        if ($sizeBytes !== null) {
            $record->sizeBytes = $sizeBytes;
        }

        if ($sha256 !== null) {
            $record->sha256 = $sha256;
        }

        $record->save(false);
    }

    public function createCallbackClient(string $callbackUrl, string $token): CallbackClient
    {
        return new CallbackClient(Craft::createGuzzleClient(), $callbackUrl, $token);
    }

    private function createRecord(AcquireCommand $command): void
    {
        if (AcquisitionRecord::findOne(['runId' => $command->runId]) !== null) {
            throw new AcquireException('replayed', 409);
        }

        $record = new AcquisitionRecord();
        $record->runId = $command->runId;
        $record->status = AcquisitionRecord::STATUS_PENDING;
        $record->step = 'queued';
        $record->profile = $command->profile;

        try {
            if (!$record->save(false)) {
                throw new AcquireException('persist_failed', 500);
            }
        } catch (IntegrityException) {
            throw new AcquireException('replayed', 409);
        }
    }

    private function queueJob(AcquireCommand $command, int $ttr): void
    {
        $job = new AcquireBundleJob([
            'runId' => $command->runId,
            'uploadUrl' => $command->uploadUrl,
            'bundlePubkey' => $command->bundlePubkey,
            'callbackUrl' => $command->callbackUrl,
            'callbackToken' => $command->callbackToken,
            'profile' => $command->profile,
        ]);

        $queue = Craft::$app->getQueue();

        if ($queue instanceof Queue) {
            $queue->ttr($ttr)->push($job);
        } else {
            $queue->push($job);
        }
    }
}
