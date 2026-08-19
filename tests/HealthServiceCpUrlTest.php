<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use Craft;
use craft\config\GeneralConfig;
use craft\helpers\UrlHelper;
use ledgehq\craftledge\services\HealthService;
use PHPUnit\Framework\TestCase;

final class HealthServiceCpUrlTest extends TestCase
{
    private mixed $previousApp = null;

    protected function setUp(): void
    {
        $this->previousApp = Craft::$app;
        Craft::setAlias('@web', 'https://site.example');
    }

    protected function tearDown(): void
    {
        Craft::$app = $this->previousApp;
    }

    public function testDefaultCpTrigger(): void
    {
        $this->configureApp(new GeneralConfig());

        $this->assertCpUrl('https://site.example/admin');
    }

    public function testRenamedCpTrigger(): void
    {
        $this->configureApp((new GeneralConfig())->cpTrigger('backstage'));

        $this->assertCpUrl('https://site.example/backstage');
    }

    public function testBaseCpUrlOnDifferentHost(): void
    {
        $this->configureApp((new GeneralConfig())->baseCpUrl('https://cp.other.example'));

        $this->assertCpUrl('https://cp.other.example/admin');
    }

    public function testBaseCpUrlWithNullCpTrigger(): void
    {
        $this->configureApp(
            (new GeneralConfig())->baseCpUrl('https://cp.other.example')->cpTrigger(null),
        );

        $this->assertCpUrl('https://cp.other.example');
    }

    private function assertCpUrl(string $expected): void
    {
        $cpUrl = (new HealthService())->getCpUrl();

        $this->assertSame($expected, $cpUrl);
        $this->assertSame(rtrim(UrlHelper::cpUrl(), '/'), $cpUrl);
    }

    private function configureApp(GeneralConfig $general): void
    {
        $general->omitScriptNameInUrls(true);

        $config = new class($general) {
            public function __construct(private readonly GeneralConfig $general)
            {
            }

            public function getGeneral(): GeneralConfig
            {
                return $this->general;
            }
        };

        $request = new class() {
            public bool $isWebAliasSetDynamically = false;

            public function getIsConsoleRequest(): bool
            {
                return true;
            }
        };

        Craft::$app = new class($config, $request) {
            public function __construct(
                private readonly object $config,
                private readonly object $request,
            ) {
            }

            public function getConfig(): object
            {
                return $this->config;
            }

            public function getRequest(): object
            {
                return $this->request;
            }

            public function getIsInitialized(): bool
            {
                return false;
            }
        };
    }
}
