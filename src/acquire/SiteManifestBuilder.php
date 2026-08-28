<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

/**
 * Normalizes raw site rows from Craft's sites service into the manifest's
 * `sites` list — the per-site routing facts a consumer needs to turn a
 * `(uri, site)` pair from `uris` into a real URL. Without this a multi-site
 * install's non-primary URIs get requested against the primary site's host
 * and 404.
 *
 * `baseUrl` is the site's resolved base URL (aliases and env vars already
 * expanded) with no trailing slash, or null when Craft can't resolve one.
 * `host` and `path` are that URL pre-split (lowercase host, or null; path
 * `/`-prefixed and without a trailing slash except for the root) so a
 * consumer re-homing every site onto sandbox hosts can tell a path-mounted
 * site (`example.com/fr` → same host, keep the path) from a host-distinguished
 * one (`fr.example.com` → needs its own host) without re-parsing. Sorted
 * primary first, then by handle, for a stable diff.
 *
 * Purely additive to the manifest: absent on plugins before 5.5.0 and omitted
 * on enumeration failure, and consumers treat absence as single-site.
 */
class SiteManifestBuilder
{
    /**
     * @param iterable<array{handle?: string|null, primary?: bool, enabled?: bool, language?: string|null, baseUrl?: string|null}> $rows
     * @return list<array{handle: string, primary: bool, enabled: bool, language: string|null, baseUrl: string|null, host: string|null, path: string}>
     */
    public function build(iterable $rows): array
    {
        $sites = [];

        foreach ($rows as $row) {
            $handle = $row['handle'] ?? null;

            if (!is_string($handle) || $handle === '') {
                continue;
            }

            $baseUrl = $this->normalizeBaseUrl($row['baseUrl'] ?? null);
            $language = $row['language'] ?? null;

            $sites[$handle] = [
                'handle' => $handle,
                'primary' => (bool)($row['primary'] ?? false),
                'enabled' => (bool)($row['enabled'] ?? true),
                'language' => is_string($language) && $language !== '' ? $language : null,
                'baseUrl' => $baseUrl,
                'host' => $this->hostOf($baseUrl),
                'path' => $this->pathOf($baseUrl),
            ];
        }

        $sites = array_values($sites);

        usort($sites, static fn(array $a, array $b): int => [$b['primary'], $a['handle']] <=> [$a['primary'], $b['handle']]);

        return $sites;
    }

    private function normalizeBaseUrl(mixed $baseUrl): ?string
    {
        if (!is_string($baseUrl)) {
            return null;
        }

        $baseUrl = rtrim(trim($baseUrl), '/');

        return $baseUrl === '' ? null : $baseUrl;
    }

    private function hostOf(?string $baseUrl): ?string
    {
        if ($baseUrl === null) {
            return null;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    private function pathOf(?string $baseUrl): string
    {
        if ($baseUrl === null) {
            return '/';
        }

        $path = parse_url($baseUrl, PHP_URL_PATH);

        if (!is_string($path)) {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $path;
    }
}
