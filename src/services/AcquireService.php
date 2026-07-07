<?php

declare(strict_types=1);

namespace ledgehq\craftledge\services;

use Craft;
use craft\base\ElementInterface;
use craft\queue\Queue;
use ledgehq\craftledge\acquire\AcquireCommand;
use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\CallbackClient;
use ledgehq\craftledge\acquire\CommandVerifier;
use ledgehq\craftledge\acquire\HostAllowlist;
use ledgehq\craftledge\acquire\KeyResolver;
use ledgehq\craftledge\acquire\UriManifestBuilder;
use ledgehq\craftledge\jobs\AcquireBundleJob;
use ledgehq\craftledge\Ledge;
use ledgehq\craftledge\records\AcquisitionRecord;
use Throwable;
use yii\base\Component;
use yii\db\IntegrityException;

class AcquireService extends Component
{
    private const URI_BATCH_SIZE = 100;

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

    /**
     * Enumerates the crawlable, publicly-resolvable URL of every URL-enabled
     * element across all registered element types (entries, categories,
     * Commerce products, any custom type) for every site, via Craft's element
     * API so it stays DB-engine agnostic. Each type's query defaults already
     * restrict to enabled, non-trashed, non-draft/revision elements;
     * `uri(':notempty:')` keeps only those that resolve to a front-end URL.
     * `section` is the section handle for entries and null for element types
     * not organized into sections (categories, products, etc.).
     *
     * @return list<array{uri: string, site: string, section: string|null}>
     */
    public function getPublicUris(): array
    {
        $siteHandles = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteHandles[$site->id] = $site->handle;
        }

        $sectionHandles = [];
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $sectionHandles[$section->id] = $section->handle;
        }

        $rows = [];

        foreach (Craft::$app->getElements()->getAllElementTypes() as $elementType) {
            /** @var class-string<ElementInterface> $elementType */
            try {
                $query = $elementType::find()
                    ->uri(':notempty:')
                    ->site('*')
                    ->asArray();

                // Batch so we never materialize a whole element type's rows at
                // once — keeps enumeration memory bounded on large sites.
                foreach ($query->each(self::URI_BATCH_SIZE) as $element) {
                    $siteId = $element['siteId'] ?? null;
                    $sectionId = $element['sectionId'] ?? null;
                    $rows[] = [
                        'uri' => $element['uri'] ?? null,
                        'siteHandle' => $siteId !== null ? ($siteHandles[$siteId] ?? null) : null,
                        'section' => $sectionId !== null ? ($sectionHandles[$sectionId] ?? null) : null,
                    ];
                }
            } catch (Throwable $e) {
                Craft::warning("Ledge URI enumeration skipped {$elementType}: {$e->getMessage()}", __METHOD__);
                continue;
            }
        }

        return (new UriManifestBuilder())->build($rows);
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
