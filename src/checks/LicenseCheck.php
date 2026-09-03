<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use craft\helpers\App;
use ledgehq\craftledge\Ledge;
use Throwable;

class LicenseCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'license';
    }

    public function run(): ?CheckResult
    {
        try {
            $pluginsService = Craft::$app->getPlugins();

            $licenseInfo = Craft::$app->getCache()->get(App::CACHE_KEY_LICENSE_INFO);
            $licenseInfo = is_array($licenseInfo) ? $licenseInfo : [];
            $craft = is_array($licenseInfo['craft'] ?? null) ? $licenseInfo['craft'] : [];

            $licenseKeyStatus = $this->normalizeStatus($craft['status'] ?? null);
            $licensedEdition = is_string($craft['edition'] ?? null) ? $craft['edition'] : null;
            $currentEdition = $this->editionLabel(Craft::$app->getEdition());

            $pluginLicenses = [];
            foreach ($pluginsService->getAllPlugins() as $handle => $plugin) {
                $info = $pluginsService->getPluginInfo($handle);
                $hasKey = $pluginsService->getPluginLicenseKey($handle) !== null;
                $timestamp = $licenseInfo['plugin-' . $handle]['timestamp'] ?? null;

                $pluginLicenses[$handle] = [
                    'name' => $plugin->name,
                    'version' => $plugin->version,
                    'licenseKeyStatus' => $this->resolveStatus($info['licenseKeyStatus'] ?? null, $hasKey),
                    'hasLicenseKey' => $hasKey,
                    'licensedEdition' => $info['licensedEdition'] ?? null,
                    'edition' => $info['edition'] ?? null,
                    'isTrial' => (bool)($info['isTrial'] ?? false),
                    'licenseIssues' => $info['licenseIssues'] ?? [],
                    'lastChecked' => is_int($timestamp) ? date('c', $timestamp) : null,
                ];
            }

            $hasInvalidPluginLicense = false;
            foreach ($pluginLicenses as $license) {
                if (in_array($license['licenseKeyStatus'], ['invalid', 'mismatched', 'astray'], true)) {
                    $hasInvalidPluginLicense = true;
                    break;
                }
            }

            $craftData = [
                'status' => $licenseKeyStatus,
                'licensedEdition' => $licensedEdition,
                'currentEdition' => $currentEdition,
            ];

            $issueStatus = Ledge::currentSettings()->licenseIssueStatus;

            $meta = [
                'craft' => $craftData,
                'plugins' => $pluginLicenses,
                'thresholds' => ['licenseIssueStatus' => $issueStatus],
            ];

            $editionMismatch = $licensedEdition !== null && $licensedEdition !== $currentEdition;

            $invalidStatuses = ['invalid', 'mismatched', 'astray'];
            $hasIssue = in_array($licenseKeyStatus, $invalidStatuses, true) || $editionMismatch || $hasInvalidPluginLicense;
            if ($hasIssue && $issueStatus !== CheckResult::STATUS_HEALTHY) {
                return new CheckResult($this->getName(), $issueStatus, $meta, 'License issue detected');
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge license check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
    }

    private function editionLabel(mixed $edition): ?string
    {
        if ($edition instanceof \BackedEnum) {
            $edition = $edition->value;
        }

        return is_int($edition) ? strtolower(App::editionName($edition)) : null;
    }

    private function resolveStatus(mixed $status, bool $hasKey): string
    {
        $status = $this->normalizeStatus($status);

        if ($status === 'unknown') {
            return $hasKey ? 'unverified' : 'none';
        }

        return $status;
    }

    private function normalizeStatus(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return (string)$status->value;
        }

        return is_string($status) && $status !== '' ? $status : 'unknown';
    }
}
