<?php

declare(strict_types=1);

namespace ledgehq\craftledge\services;

use Composer\InstalledVersions;
use Throwable;
use yii\base\Component;

/**
 * Installed package inventory for Ledge's security-advisory matching.
 *
 * Sourced strictly from Composer's runtime data (InstalledVersions), never a
 * lockfile: it reflects what is actually deployed, and on a production
 * `composer install --no-dev` the dev dependencies simply are not present.
 */
class DependenciesService extends Component
{
    /**
     * @return array<int, array{name: string, version: string}>
     */
    public function getPackages(): array
    {
        $rootPackage = InstalledVersions::getRootPackage()['name'];
        $packages = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            if ($name === $rootPackage) {
                continue;
            }

            $version = InstalledVersions::getPrettyVersion($name);

            if ($version === null) {
                continue;
            }

            $packages[] = [
                'name' => strtolower($name),
                'version' => ltrim($version, 'v'),
            ];
        }

        usort($packages, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $packages;
    }

    public function getHash(array $packages): string
    {
        return hash('sha256', json_encode($packages));
    }

    public function getCurrentHash(): ?string
    {
        try {
            return $this->getHash($this->getPackages());
        } catch (Throwable) {
            return null;
        }
    }
}
