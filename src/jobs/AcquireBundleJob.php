<?php

declare(strict_types=1);

namespace ledgehq\craftledge\jobs;

use Craft;
use craft\queue\BaseJob;
use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\BackupResultBuilder;
use ledgehq\craftledge\acquire\BundleBuilder;
use ledgehq\craftledge\acquire\BundleResult;
use ledgehq\craftledge\acquire\BundleUploader;
use ledgehq\craftledge\acquire\CallbackClient;
use ledgehq\craftledge\acquire\ManifestBuilder;
use ledgehq\craftledge\acquire\Preflight;
use ledgehq\craftledge\acquire\TempWorkspace;
use ledgehq\craftledge\Ledge;
use ledgehq\craftledge\services\AcquireService;
use Throwable;
use yii\db\Query;

class AcquireBundleJob extends BaseJob
{
    public string $runId = '';
    public string $uploadUrl = '';
    public string $bundlePubkey = '';
    public string $callbackUrl = '';
    public string $callbackToken = '';
    public string $profile = 'full';

    public function execute($queue): void
    {
        $service = Ledge::getInstance()->acquire;
        $callbacks = $service->createCallbackClient($this->callbackUrl, $this->callbackToken);
        $workspace = new TempWorkspace(Craft::$app->getPath()->getRuntimePath() . '/ledge', $this->runId);
        $step = 'preflight';

        try {
            $service->transition($this->runId, AcquireService::STATUS_RUNNING, $step);
            $callbacks->send(CallbackClient::EVENT_STARTED, $step);

            $workspace->create();
            $this->runPreflight($workspace);
            $this->setProgress($queue, 0.2);
            $callbacks->send(CallbackClient::EVENT_PROGRESS, $step);

            $step = 'dump';
            $service->transition($this->runId, AcquireService::STATUS_RUNNING, $step);
            $dumpPath = $workspace->path('dump.sql');
            $dumpStart = microtime(true);
            $this->createDump($dumpPath);
            $dumpDurationMs = (int)round((microtime(true) - $dumpStart) * 1000);
            $this->setProgress($queue, 0.5);
            $callbacks->send(CallbackClient::EVENT_PROGRESS, $step);

            $step = 'manifest';
            $service->transition($this->runId, AcquireService::STATUS_RUNNING, $step);
            $entries = $this->buildEntries($workspace, $dumpPath);
            $this->setProgress($queue, 0.6);
            $callbacks->send(CallbackClient::EVENT_PROGRESS, $step);

            $step = 'encrypt';
            $service->transition($this->runId, AcquireService::STATUS_RUNNING, $step);
            $result = (new BundleBuilder())->build(
                $entries,
                $this->getBundlePubkeyBytes(),
                Ledge::getInstance()->getSettings()->acquireMaxBundleBytes,
                $workspace,
            );
            $this->setProgress($queue, 0.8);
            $callbacks->send(CallbackClient::EVENT_PROGRESS, $step);

            $step = 'upload';
            $service->transition($this->runId, AcquireService::STATUS_RUNNING, $step);
            (new BundleUploader(Craft::createGuzzleClient()))->upload($result->path, $this->uploadUrl);
            $this->setProgress($queue, 1.0);

            $service->transition($this->runId, AcquireService::STATUS_COMPLETED, $step, null, $result->size, $result->sha256);
            $callbacks->send(CallbackClient::EVENT_COMPLETED, $step, $this->completedDetail($result, $dumpDurationMs));
        } catch (Throwable $e) {
            $reason = $e instanceof AcquireException ? $e->reason : 'unexpected_error';
            $detail = $e instanceof AcquireException ? $e->detail : $e->getMessage();

            Craft::error("Ledge acquisition {$this->runId} failed at {$step}: {$e->getMessage()}", __METHOD__);
            $service->transition($this->runId, AcquireService::STATUS_FAILED, $step, $reason);
            $callbacks->send(CallbackClient::EVENT_FAILED, $step, [
                'reason' => $reason,
                'detail' => $detail,
            ]);

            throw $e;
        } finally {
            $workspace->cleanup();
        }
    }

    protected function defaultDescription(): ?string
    {
        $label = $this->profile === 'backup' ? 'backup' : 'acquisition';

        return "Ledge {$label} {$this->runId}";
    }

    private function runPreflight(TempWorkspace $workspace): void
    {
        $db = Craft::$app->getDb();
        $binary = $db->getIsMysql() ? 'mysqldump' : 'pg_dump';
        $backupCommand = Craft::$app->getConfig()->getGeneral()->backupCommand;

        $preflight = new Preflight(
            function() use ($binary, $backupCommand): bool {
                if ($backupCommand === false) {
                    return false;
                }

                if ($backupCommand !== null) {
                    return true;
                }

                $resolved = shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null');

                return is_string($resolved) && trim($resolved) !== '';
            },
            fn(string $dir): float|false => @disk_free_space($dir),
            $this->estimateDumpBytes(),
            $workspace->getDir(),
            extension_loaded('sodium'),
        );

        $preflight->run();
    }

    private function createDump(string $dumpPath): void
    {
        try {
            Craft::$app->getDb()->backupTo($dumpPath);
        } catch (Throwable $e) {
            throw new AcquireException('dump_failed', 500, $e->getMessage());
        }
    }

    /**
     * Assembles the archive contents for the bundle.
     *
     * The `backup` profile is deliberately the database and nothing else — no
     * env manifest, no metadata sidecar, no project config. Craft's project
     * config and schema version already live inside the dump (projectconfig /
     * info tables), so a bare dump still restores to a working site while
     * carrying zero secret-bearing sidecars.
     *
     * The `full` (update-run) profile additionally ships the crawl manifest
     * (env vars by denylist, facts, URIs); that manifest is never built for
     * backups.
     *
     * @return array<string, string> archive name => absolute source path
     */
    private function buildEntries(TempWorkspace $workspace, string $dumpPath): array
    {
        if ($this->profile === 'backup') {
            return ['dump.sql' => $dumpPath];
        }

        $manifestPath = $workspace->path('manifest.json');
        $denylist = Ledge::getInstance()->getSettings()->getAcquireEnvDenylist();
        $manifest = (new ManifestBuilder($denylist))->build($this->collectEnv(), $this->collectFacts());
        $this->writeJson($manifestPath, $manifest);

        return ['dump.sql' => $dumpPath, 'manifest.json' => $manifestPath];
    }

    private function completedDetail(BundleResult $result, int $dumpDurationMs): array
    {
        if ($this->profile === 'backup') {
            return (new BackupResultBuilder())->payload($result, $dumpDurationMs, $this->countTables());
        }

        return [
            'size' => $result->size,
            'sha256' => $result->sha256,
        ];
    }

    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json) === false) {
            throw new AcquireException('tmp_not_writable', 500, $path);
        }
    }

    private function countTables(): ?int
    {
        try {
            $db = Craft::$app->getDb();

            if ($db->getIsMysql()) {
                $count = (new Query())
                    ->from('information_schema.TABLES')
                    ->where('TABLE_SCHEMA = DATABASE()')
                    ->count('*', $db);
            } else {
                $count = (new Query())
                    ->from('information_schema.tables')
                    ->where(['table_schema' => 'public'])
                    ->count('*', $db);
            }

            return is_numeric($count) ? (int)$count : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function estimateDumpBytes(): ?int
    {
        try {
            $db = Craft::$app->getDb();

            if ($db->getIsMysql()) {
                $sum = (new Query())
                    ->from('information_schema.TABLES')
                    ->where('TABLE_SCHEMA = DATABASE()')
                    ->sum('DATA_LENGTH + INDEX_LENGTH', $db);
            } else {
                $sum = $db->createCommand('SELECT pg_database_size(current_database())')->queryScalar();
            }

            return is_numeric($sum) ? (int)$sum : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function getBundlePubkeyBytes(): string
    {
        $bytes = base64_decode($this->bundlePubkey, true);

        if ($bytes === false || strlen($bytes) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new AcquireException('encrypt_failed', 500, 'invalid bundle_pubkey');
        }

        return $bytes;
    }

    private function collectEnv(): array
    {
        return array_merge(getenv(), $_ENV);
    }

    private function collectFacts(): array
    {
        $db = Craft::$app->getDb();
        $extensions = get_loaded_extensions();
        sort($extensions);

        $plugins = [];
        foreach (Craft::$app->getPlugins()->getAllPluginInfo() as $handle => $info) {
            if (!empty($info['isInstalled'])) {
                $plugins[$handle] = $info['version'] ?? null;
            }
        }

        $facts = [
            'php' => [
                'version' => PHP_VERSION,
                'extensions' => $extensions,
            ],
            'database' => [
                'driver' => $db->getDriverName(),
                'version' => $db->getSchema()->getServerVersion(),
            ],
            'craft' => [
                'version' => Craft::$app->getVersion(),
                'edition' => Craft::$app->edition->name,
            ],
            'configVersion' => Craft::$app->getInfo()->configVersion,
            'plugins' => $plugins,
        ];

        // Omit `uris` entirely on catastrophic failure so the runner falls back
        // to its own URL discovery rather than trusting an empty list.
        try {
            $facts['uris'] = Ledge::getInstance()->acquire->getPublicUris();
        } catch (Throwable $e) {
            Craft::warning('Ledge URI enumeration failed: ' . $e->getMessage(), __METHOD__);
        }

        return $facts;
    }
}
