<?php

declare(strict_types=1);

namespace ledgehq\craftledge\models;

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public const DEFAULT_LEDGE_BASE_URL = 'https://my.ledgehq.app';

    public ?string $secretKey = null;
    public string $endpointPath = '_ledge/health';
    public int $diskSpaceThreshold = 90;
    public int $queueAgeThreshold = 300;
    public int $failedLoginWindow = 86400;
    public array $enabledChecks = [
        'database' => true,
        'queue' => true,
        'disk' => true,
        'memory' => true,
        'craftVersion' => true,
        'plugins' => true,
        'debugMode' => true,
        'failedLogins' => true,
        'license' => true,
        'environment' => true,
        'formie' => true,
        'freeform' => true,
    ];
    public array $ignoredEnvVars = [];
    public ?string $ledgeBaseUrl = null;
    public array $acquireAllowedHosts = [];
    public array $acquireEnvDenylist = [];
    public string $acquirePath = '_ledge/acquire';
    public int $acquireMaxBundleBytes = 524288000;
    public int $acquireJobTtr = 3600;

    public function getSecretKey(): ?string
    {
        if ($this->secretKey) {
            return App::parseEnv($this->secretKey);
        }

        return App::env('LEDGE_SECRET_KEY');
    }

    /**
     * Deliberately file-config only — no env parsing. The keyset base URL is
     * a trust anchor; it must never be attacker-influenceable or derived from
     * an incoming command.
     */
    public function getLedgeBaseUrl(): string
    {
        return rtrim($this->ledgeBaseUrl ?: self::DEFAULT_LEDGE_BASE_URL, '/');
    }

    /**
     * @return string[]
     */
    public function getAcquireAllowedHosts(): array
    {
        $entries = $this->acquireAllowedHosts;

        if (empty($entries)) {
            $env = App::env('LEDGE_ACQUIRE_ALLOWED_HOSTS');
            if (is_string($env) && $env !== '') {
                $entries = explode(',', $env);
            }
        }

        $hosts = [];

        foreach ($entries as $entry) {
            $parsed = App::parseEnv((string)$entry);
            if (is_string($parsed) && trim($parsed) !== '') {
                $hosts[] = trim($parsed);
            }
        }

        return $hosts;
    }

    /**
     * Operator-configured fnmatch patterns of env vars to exclude from the
     * acquisition manifest. Empty by default (all vars included); the plugin's
     * own LEDGE_* auth material is always excluded regardless.
     *
     * @return string[]
     */
    public function getAcquireEnvDenylist(): array
    {
        $entries = $this->acquireEnvDenylist;

        if (empty($entries)) {
            $env = App::env('LEDGE_ACQUIRE_ENV_DENYLIST');
            if (is_string($env) && $env !== '') {
                $entries = explode(',', $env);
            }
        }

        $patterns = [];

        foreach ($entries as $entry) {
            $parsed = App::parseEnv((string)$entry);
            if (is_string($parsed) && trim($parsed) !== '') {
                $patterns[] = trim($parsed);
            }
        }

        return $patterns;
    }

    public function rules(): array
    {
        return [
            [['endpointPath', 'acquirePath'], 'required'],
            [['endpointPath', 'acquirePath'], 'string'],
            [['diskSpaceThreshold', 'queueAgeThreshold', 'failedLoginWindow'], 'integer'],
            [['diskSpaceThreshold'], 'integer', 'min' => 1, 'max' => 100],
            [['acquireMaxBundleBytes', 'acquireJobTtr'], 'integer', 'min' => 1],
        ];
    }
}
