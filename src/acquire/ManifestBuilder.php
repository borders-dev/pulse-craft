<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

/**
 * Builds manifest.json from the process environment plus Craft facts.
 *
 * Every env var is included by default; the site operator narrows this with a
 * configurable denylist of fnmatch patterns (case-insensitive). The bundle
 * already carries a full, sealed DB dump, so the manifest's env vars are a
 * minor incremental exposure — the operator owns the policy.
 *
 * The plugin's own auth material is always excluded regardless of operator
 * config: shipping it would let anyone with the bundle (or a compromised
 * runner) authenticate to this site's Ledge endpoints.
 */
class ManifestBuilder
{
    private const ALWAYS_DENY = [
        'LEDGE_*',
    ];

    /**
     * @param string[] $denylist operator-configured fnmatch patterns
     */
    public function __construct(
        private readonly array $denylist = [],
    ) {
    }

    public function build(array $env, array $facts): array
    {
        $patterns = array_merge(self::ALWAYS_DENY, $this->denylist);
        $vars = [];

        foreach ($env as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }

            if ($this->matchesAny($name, $patterns)) {
                continue;
            }

            $vars[$name] = (string)$value;
        }

        ksort($vars);

        return ['env' => $vars] + $facts;
    }

    private function matchesAny(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && fnmatch($pattern, $name, FNM_NOESCAPE | FNM_CASEFOLD)) {
                return true;
            }
        }

        return false;
    }
}
