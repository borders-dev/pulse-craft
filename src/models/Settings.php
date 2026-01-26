<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\models;

use Craft;
use craft\base\Model;

class Settings extends Model
{
    public ?string $secretKey = null;
    public string $endpointPath = 'health';
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
    ];

    public function getSecretKey(): ?string
    {
        return Craft::parseEnv($this->secretKey) ?: Craft::parseEnv('$PULSE_SECRET_KEY');
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
