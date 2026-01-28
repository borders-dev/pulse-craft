<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

use Craft;

class LicenseCheck implements CheckInterface
{
    public function getName(): string
    {
        return 'license';
    }

    public function run(): CheckResult
    {
        $licenseKeyStatus = Craft::$app->getCache()->get('licenseKeyStatus') ?? 'unknown';
        $licensedEdition = method_exists(Craft::$app, 'getLicensedEdition')
            ? Craft::$app->getLicensedEdition()
            : null;
        $currentEdition = Craft::$app->getEdition();

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

        if ($licenseKeyStatus === 'invalid' || $editionMismatch || $hasInvalidPluginLicense) {
            return CheckResult::unhealthy($this->getName(), [
                'craft' => $craftData,
                'plugins' => $pluginLicenses,
            ], 'License issue detected');
        }

        return CheckResult::healthy($this->getName(), [
            'craft' => $craftData,
            'plugins' => $pluginLicenses,
        ]);
    }
}
