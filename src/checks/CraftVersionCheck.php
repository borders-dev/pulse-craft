<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

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
        $currentVersion = Craft::$app->getVersion();
        $edition = App::editionName(Craft::$app->getEdition());

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
                    $isCritical = $latest->critical ?? false;
                    $notes = $latest->notes;
                }
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
            return CheckResult::degraded(
                $this->getName(),
                $meta,
                "Update available: {$latestVersion}"
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }
}
