<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use Craft;
use ledgehq\craftledge\checks\PluginVersionsCheck;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PluginVersionsCheckTest extends TestCase
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
        $this->configureApp(
            ['hyper' => $this->plugin('Hyper', '1.2.0')],
            ['hyper' => $this->update('expired', '1.3.0')],
        );

        $meta = (new PluginVersionsCheck())->run()->meta;

        $this->assertSame('expired', $meta['installed']['hyper']['updateStatus']);
        $this->assertSame('expired', $meta['outdated']['hyper']['updateStatus']);
    }

    public function testEligibleUpdateStatus(): void
    {
        $this->configureApp(
            ['formie' => $this->plugin('Formie', '2.0.0')],
            ['formie' => $this->update('eligible', '2.1.0')],
        );

        $meta = (new PluginVersionsCheck())->run()->meta;

        $this->assertSame('eligible', $meta['installed']['formie']['updateStatus']);
        $this->assertSame('eligible', $meta['outdated']['formie']['updateStatus']);
    }

    public function testUpdatesFetchThrowing(): void
    {
        $this->configureApp(['formie' => $this->plugin('Formie', '2.0.0')], null);

        $meta = (new PluginVersionsCheck())->run()->meta;

        $this->assertNull($meta['installed']['formie']['updateStatus']);
        $this->assertFalse($meta['installed']['formie']['hasUpdate']);
    }

    public function testPluginWithoutUpdateEntry(): void
    {
        $this->configureApp(['formie' => $this->plugin('Formie', '2.0.0')], []);

        $meta = (new PluginVersionsCheck())->run()->meta;

        $this->assertNull($meta['installed']['formie']['updateStatus']);
    }

    public function testNonStringStatusIsNormalizedToNull(): void
    {
        $update = $this->update('eligible', '2.1.0');
        $update->status = 123;

        $this->configureApp(['formie' => $this->plugin('Formie', '2.0.0')], ['formie' => $update]);

        $meta = (new PluginVersionsCheck())->run()->meta;

        $this->assertNull($meta['installed']['formie']['updateStatus']);
    }

    private function plugin(string $name, string $version): object
    {
        return new class($name, $version) {
            public function __construct(
                public string $name,
                private readonly string $version,
            ) {
            }

            public function getVersion(): string
            {
                return $this->version;
            }
        };
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

    private function configureApp(array $plugins, ?array $pluginUpdates): void
    {
        $pluginsService = new class($plugins) {
            public function __construct(private readonly array $plugins)
            {
            }

            public function getAllPlugins(): array
            {
                return $this->plugins;
            }
        };

        $updatesService = new class($pluginUpdates) {
            public function __construct(private readonly ?array $pluginUpdates)
            {
            }

            public function getUpdates(bool $forceRefresh): object
            {
                if ($this->pluginUpdates === null) {
                    throw new RuntimeException('Updates unavailable');
                }

                return new class($this->pluginUpdates) {
                    public function __construct(public array $plugins)
                    {
                    }
                };
            }
        };

        Craft::$app = new class($pluginsService, $updatesService) {
            public function __construct(
                private readonly object $plugins,
                private readonly object $updates,
            ) {
            }

            public function getPlugins(): object
            {
                return $this->plugins;
            }

            public function getUpdates(): object
            {
                return $this->updates;
            }
        };
    }
}
