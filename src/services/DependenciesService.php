<?php

declare(strict_types=1);

namespace ledgehq\craftledge\services;

use Composer\InstalledVersions;
use Craft;
use Throwable;
use yii\base\Component;

/**
 * Installed package inventory for Ledge's security-advisory matching.
 *
 * Sourced strictly from Composer's runtime data (InstalledVersions), never a
 * lockfile: it reflects what is actually deployed, and on a production
 * `composer install --no-dev` the dev dependencies simply are not present.
 *
 * The lockfile is hashed separately (`getComposerLockHash()`) for a different
 * question: whether what is deployed matches what is committed to the repo.
 */
class DependenciesService extends Component
{
    /**
     * @return array<int, array{name: string, version: string}>
     */
    public function getPackages(): array
    {
        $installed = [];

        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $installed[$name] = InstalledVersions::getPrettyVersion($name);
        }

        return $this->normalizePackages($installed, InstalledVersions::getRootPackage()['name']);
    }

    /**
     * Shape Composer's raw inventory into the list the endpoint returns and
     * the hash is computed over: root package dropped, versionless entries
     * dropped, names lowercased, leading `v` stripped, sorted by name.
     *
     * @param array<string, string|null> $installed package name => pretty version
     * @return array<int, array{name: string, version: string}>
     */
    public function normalizePackages(array $installed, ?string $rootPackage): array
    {
        $packages = [];

        foreach ($installed as $name => $version) {
            if ($name === $rootPackage || $version === null) {
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

    /**
     * Fingerprint of the normalized package list. Only comparable between the
     * health payload and the dependencies endpoint of the same plugin version.
     *
     * @param array<int, array{name: string, version: string}> $packages
     */
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

    /**
     * SHA-256 of the raw bytes of the project's composer.lock, so Ledge can
     * compare the deployed lockfile against the one at the repo's branch head
     * without fetching it from the server. Null when the file is absent
     * (some pipelines ship only vendor/) or unreadable; that means "unknown",
     * not "changed".
     */
    public function getComposerLockHash(?string $path = null): ?string
    {
        try {
            $path ??= Craft::getAlias('@root') . DIRECTORY_SEPARATOR . 'composer.lock';

            if (!is_string($path) || !is_file($path) || !is_readable($path)) {
                return null;
            }

            $hash = hash_file('sha256', $path);

            return $hash === false ? null : $hash;
        } catch (Throwable) {
            return null;
        }
    }
}
