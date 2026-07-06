<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

/**
 * Normalizes raw (uri, siteHandle) rows enumerated from Craft's element API
 * into the manifest's `uris` list — the site-root-relative crawlable URL of
 * every URL-enabled element, per site, so the runner can crawl the site
 * before/after an update instead of deriving URLs from raw SQL.
 *
 * Craft stores element URIs without a leading slash and the homepage as the
 * sentinel `__home__`; this maps those to `/`-prefixed paths and `/`
 * respectively, drops empty/siteless rows, dedupes exact (uri, site) pairs,
 * and sorts for a stable diff. No sampling, no cap — it emits every row.
 */
class UriManifestBuilder
{
    private const HOME_URI = '__home__';

    /**
     * @param iterable<array{uri?: string|null, siteHandle?: string|null}> $rows
     * @return list<array{uri: string, site: string}>
     */
    public function build(iterable $rows): array
    {
        $seen = [];
        $uris = [];

        foreach ($rows as $row) {
            $rawUri = $row['uri'] ?? null;
            $site = $row['siteHandle'] ?? null;

            if (!is_string($rawUri) || $rawUri === '' || !is_string($site) || $site === '') {
                continue;
            }

            $uri = $this->normalize($rawUri);
            $key = $site . "\0" . $uri;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $uris[] = ['uri' => $uri, 'site' => $site];
        }

        usort($uris, static fn(array $a, array $b): int => [$a['site'], $a['uri']] <=> [$b['site'], $b['uri']]);

        return $uris;
    }

    private function normalize(string $uri): string
    {
        if ($uri === self::HOME_URI) {
            return '/';
        }

        return '/' . ltrim($uri, '/');
    }
}
