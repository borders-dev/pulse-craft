<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
use craft\helpers\App;
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
            $licenseKeyStatus = $this->normalizeStatus($licenseInfo['craft']['status'] ?? null);

            $rawLicensedEdition = Craft::$app->getLicensedEdition();
            $licensedEdition = $rawLicensedEdition !== null ? App::editionName($rawLicensedEdition) : null;
            $currentEdition = App::editionName(Craft::$app->getEdition());

            $pluginLicenses = [];
            foreach ($pluginsService->getAllPlugins() as $handle => $plugin) {
                $info = $pluginsService->getPluginInfo($handle);
                $hasKey = $pluginsService->getPluginLicenseKey($handle) !== null;
                $pluginLicenses[$handle] = [
                    'name' => $plugin->name,
                    'licenseKeyStatus' => $this->resolveStatus($info['licenseKeyStatus'] ?? null, $hasKey),
                ];
            }

            $hasInvalidPluginLicense = false;
            foreach ($pluginLicenses as $license) {
                if (in_array($license['licenseKeyStatus'], ['invalid', 'mismatched', 'astray'], true)) {
                    $hasInvalidPluginLicense = true;
                    break;
                }
            }

            $editionMismatch = $licensedEdition !== null && $licensedEdition !== $currentEdition;
            $craftData = [
                'status' => $licenseKeyStatus,
                'licensedEdition' => $licensedEdition,
                'currentEdition' => $currentEdition,
            ];

            $meta = [
                'craft' => $craftData,
                'plugins' => $pluginLicenses,
            ];

            if ($licenseKeyStatus === 'invalid' || $editionMismatch || $hasInvalidPluginLicense) {
                return CheckResult::unhealthy($this->getName(), $meta, 'License issue detected');
            }

            return CheckResult::healthy($this->getName(), $meta);
        } catch (Throwable $e) {
            Craft::error('Ledge license check failed: ' . $e->getMessage(), __METHOD__);
            return CheckResult::degraded($this->getName(), [], 'Check unavailable');
        }
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
