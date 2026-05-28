<?php

declare(strict_types=1);

namespace ledgehq\craftledge\checks;

use Craft;
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
            $licenseKeyStatus = Craft::$app->getCache()->get('licenseKeyStatus') ?? 'unknown';
            $rawLicensedEdition = Craft::$app->getLicensedEdition();
            $rawCurrentEdition = Craft::$app->getEdition();

            $licensedEdition = $rawLicensedEdition instanceof \BackedEnum ? $rawLicensedEdition->value : $rawLicensedEdition;
            $currentEdition = $rawCurrentEdition instanceof \BackedEnum ? $rawCurrentEdition->value : $rawCurrentEdition;

            $pluginLicenses = [];
            foreach (Craft::$app->getPlugins()->getAllPlugins() as $handle => $plugin) {
                $pluginLicenses[$handle] = [
                    'name' => $plugin->name,
                    'licenseKeyStatus' => $plugin->licenseKeyStatus ?? 'unknown',
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
}
