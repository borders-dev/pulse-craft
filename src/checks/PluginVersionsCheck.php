<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;
use Throwable;

class PluginVersionsCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'plugins';
    }

    public function run(): ?CheckResult
    {
        $plugins = Craft::$app->getPlugins()->getAllPlugins();

        try {
            $updates = Craft::$app->getUpdates()->getUpdates(false);
        } catch (Throwable) {
            $updates = null;
        }

        $pluginData = [];
        $outdated = [];
        $hasCritical = false;

        foreach ($plugins as $handle => $plugin) {
            $hasUpdate = false;
            $latestVersion = null;
            $isCritical = false;
            $notes = null;

            if ($updates !== null && isset($updates->plugins[$handle])) {
                $pluginUpdates = $updates->plugins[$handle];
                $hasUpdate = $pluginUpdates->getHasReleases();

                if ($hasUpdate) {
                    $latest = $pluginUpdates->getLatest();
                    if ($latest) {
                        $latestVersion = $latest->version;
                        $isCritical = $latest->critical ?? false;
                        $notes = $latest->notes;

                        if ($isCritical) {
                            $hasCritical = true;
                        }

                        $outdated[$handle] = [
                            'name' => $plugin->name,
                            'current' => $plugin->getVersion(),
                            'latest' => $latestVersion,
                            'isCritical' => $isCritical,
                            'notes' => $notes,
                        ];
                    }
                }
            }

            $pluginData[$handle] = [
                'name' => $plugin->name,
                'version' => $plugin->getVersion(),
                'hasUpdate' => $hasUpdate,
                'latestVersion' => $latestVersion,
                'isCritical' => $isCritical,
                'notes' => $notes,
            ];
        }

        $meta = [
            'installed' => $pluginData,
            'outdated' => $outdated,
        ];

        if ($hasCritical) {
            $criticalCount = count(array_filter($outdated, fn($p) => $p['isCritical']));
            return CheckResult::degraded(
                $this->getName(),
                $meta,
                "{$criticalCount} plugin(s) have critical updates"
            );
        }

        return CheckResult::healthy($this->getName(), $meta);
    }
}
