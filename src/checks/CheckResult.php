<?php

declare(strict_types=1);

namespace bordersdev\craftpulse\checks;

class CheckResult
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_UNHEALTHY = 'unhealthy';

    public function __construct(
        public readonly string $name,
        public readonly string $status,
        public readonly array $meta = [],
        public readonly ?string $output = null,
    ) {
    }

    public static function healthy(string $name, array $meta = []): self
    {
        return new self($name, self::STATUS_HEALTHY, $meta);
    }

    public static function degraded(string $name, array $meta = [], ?string $output = null): self
    {
        return new self($name, self::STATUS_DEGRADED, $meta, $output);
    }

    public static function unhealthy(string $name, array $meta = [], ?string $output = null): self
    {
        return new self($name, self::STATUS_UNHEALTHY, $meta, $output);
    }

    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            ...$this->meta,
        ];

        if ($this->output !== null) {
            $result['output'] = $this->output;
        }

        return $result;
    }
}
