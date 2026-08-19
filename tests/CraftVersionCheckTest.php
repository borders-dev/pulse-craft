<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use Craft;
use ledgehq\craftledge\checks\CraftVersionCheck;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CraftVersionCheckTest extends TestCase
{
    private mixed $previousApp = null;

    protected function setUp(): void
    {
        $this->previousApp = Craft::$app;
    }

    protected function tearDown(): void
    {
        Craft::$app = $this->previousApp;
    }

    public function testExpiredUpdateStatus(): void
    {
        $this->configureApp($this->update('expired', '5.5.0'));

        $meta = (new CraftVersionCheck())->run()->meta;

        $this->assertSame('expired', $meta['updateStatus']);
        $this->assertSame('5.5.0', $meta['latest']);
    }

    public function testEligibleUpdateStatus(): void
    {
        $this->configureApp($this->update('eligible', '5.5.0'));

        $meta = (new CraftVersionCheck())->run()->meta;

        $this->assertSame('eligible', $meta['updateStatus']);
    }

    public function testUpdatesFetchThrowing(): void
    {
        $this->configureApp(null);

        $meta = (new CraftVersionCheck())->run()->meta;

        $this->assertNull($meta['updateStatus']);
        $this->assertFalse($meta['hasUpdate']);
        $this->assertSame('5.4.0', $meta['current']);
    }

    public function testNonStringStatusIsNormalizedToNull(): void
    {
        $update = $this->update('eligible', '5.5.0');
        $update->status = 123;

        $this->configureApp($update);

        $meta = (new CraftVersionCheck())->run()->meta;

        $this->assertNull($meta['updateStatus']);
    }

    private function update(string $status, string $latestVersion): object
    {
        return new class($status, $latestVersion) {
            public array $releases = [];

            public function __construct(
                public mixed $status,
                private readonly string $latestVersion,
            ) {
            }

            public function getHasReleases(): bool
            {
                return true;
            }

            public function getLatest(): object
            {
                return new class($this->latestVersion) {
                    public ?string $notes = null;

                    public function __construct(public string $version)
                    {
                    }
                };
            }

            public function getHasCritical(): bool
            {
                return false;
            }
        };
    }

    private function configureApp(?object $cmsUpdate): void
    {
        $updatesService = new class($cmsUpdate) {
            public function __construct(private readonly ?object $cmsUpdate)
            {
            }

            public function getUpdates(bool $forceRefresh): object
            {
                if ($this->cmsUpdate === null) {
                    throw new RuntimeException('Updates unavailable');
                }

                return new class($this->cmsUpdate) {
                    public function __construct(public object $cms)
                    {
                    }
                };
            }
        };

        Craft::$app = new class($updatesService) {
            public function __construct(private readonly object $updates)
            {
            }

            public function getVersion(): string
            {
                return '5.4.0';
            }

            public function getEdition(): int
            {
                return 0;
            }

            public function getUpdates(): object
            {
                return $this->updates;
            }
        };
    }
}
