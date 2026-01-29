<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\models;

use craft\base\Model;
use craft\helpers\App;

class Settings extends Model
{
    public ?string $secretKey = null;
    public string $endpointPath = '_pulse/health';
    public int $diskSpaceThreshold = 90;
    public int $queueStuckThreshold = 3600;
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

    public function getSecretKey(): ?string
    {
        if ($this->secretKey) {
            return App::parseEnv($this->secretKey);
        }

        return App::env('PULSE_SECRET_KEY');
    }

    public function rules(): array
    {
        return [
            [['endpointPath'], 'required'],
            [['endpointPath'], 'string'],
            [['diskSpaceThreshold', 'queueStuckThreshold', 'failedLoginWindow'], 'integer'],
            [['diskSpaceThreshold'], 'integer', 'min' => 1, 'max' => 100],
        ];
    }
}
