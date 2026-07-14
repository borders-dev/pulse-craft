<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use craft\helpers\App;
use Throwable;

class CraftVersionCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'craftVersion';
    }

    public function run(): ?CheckResult
    {
        try {
            $currentVersion = Craft::$app->getVersion();
            $edition = App::editionName(Craft::$app->getEdition());
        } catch (Throwable $e) {
            Craft::error('Ledge craftVersion check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }

        try {
            $updates = Craft::$app->getUpdates()->getUpdates(false);
            $craftUpdates = $updates->cms;

            $hasUpdate = $craftUpdates->getHasReleases();
            $latestVersion = null;
            $isCritical = false;
            $notes = null;

            if ($hasUpdate) {
                $latest = $craftUpdates->getLatest();
                if ($latest) {
                    $latestVersion = $latest->version;
                }
                $isCritical = $craftUpdates->getHasCritical();
                $notes = $this->buildReleaseNotes($craftUpdates->releases);
            }
        } catch (Throwable) {
            return CheckResult::healthy($this->getName(), [
                'current' => $currentVersion,
                'edition' => $edition,
                'latest' => null,
                'hasUpdate' => false,
                'isCritical' => false,
                'notes' => null,
            ]);
        }

        $meta = [
            'current' => $currentVersion,
            'edition' => $edition,
            'latest' => $latestVersion,
            'hasUpdate' => $hasUpdate,
            'isCritical' => $isCritical,
            'notes' => $notes,
        ];

        if ($isCritical) {
            return CheckResult::unhealthy(
                $this->getName(),
                $meta,
                "Critical update available: {$latestVersion}"
            );
        }

        if ($hasUpdate) {
            return CheckResult::healthy($this->getName(), $meta, "Update available: {$latestVersion}");
        }

        return CheckResult::healthy($this->getName(), $meta);
    }

    private function buildReleaseNotes(array $releases): ?array
    {
        $notes = [];
        foreach ($releases as $release) {
            if ($release->notes !== null && $release->notes !== '') {
                $notes[] = [
                    'version' => $release->version,
                    'date' => $release->date?->format('Y-m-d'),
                    'critical' => $release->critical,
                    'notes' => $release->notes,
                ];
            }
        }

        return $notes ?: null;
    }
}
