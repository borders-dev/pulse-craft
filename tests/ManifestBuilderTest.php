<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\ManifestBuilder;
use PHPUnit\Framework\TestCase;

final class ManifestBuilderTest extends TestCase
{
    public function testIncludesAllEnvVarsByDefault(): void
    {
        $manifest = (new ManifestBuilder())->build([
            'CRAFT_ENVIRONMENT' => 'production',
            'DB_PASSWORD' => 'hunter2',
            'STRIPE_API_KEY' => 'sk_live_x',
            'PATH' => '/usr/bin',
        ], []);

        $this->assertSame([
            'CRAFT_ENVIRONMENT' => 'production',
            'DB_PASSWORD' => 'hunter2',
            'PATH' => '/usr/bin',
            'STRIPE_API_KEY' => 'sk_live_x',
        ], $manifest['env']);
    }

    public function testAlwaysExcludesLedgeAuthMaterial(): void
    {
        $manifest = (new ManifestBuilder())->build([
            'LEDGE_SECRET_KEY' => 'shared-key',
            'LEDGE_ACQUIRE_ALLOWED_HOSTS' => 'app.ledgehq.app',
            'CRAFT_ENVIRONMENT' => 'production',
        ], []);

        $this->assertSame(['CRAFT_ENVIRONMENT' => 'production'], $manifest['env']);
    }

    public function testOperatorDenylistExcludesMatchingVars(): void
    {
        $manifest = (new ManifestBuilder(['DB_*', '*_SECRET*', '*_API_KEY']))->build([
            'DB_PASSWORD' => 'hunter2',
            'DB_SERVER' => 'localhost',
            'SESSION_SECRET' => 'x',
            'STRIPE_API_KEY' => 'sk_live_x',
            'CRAFT_ENVIRONMENT' => 'production',
        ], []);

        $this->assertSame(['CRAFT_ENVIRONMENT' => 'production'], $manifest['env']);
    }

    public function testDenylistMatchingIsCaseInsensitive(): void
    {
        $manifest = (new ManifestBuilder(['db_*']))->build([
            'DB_PASSWORD' => 'hunter2',
            'CRAFT_ENVIRONMENT' => 'production',
        ], []);

        $this->assertSame(['CRAFT_ENVIRONMENT' => 'production'], $manifest['env']);
    }

    public function testEmptyDenylistPatternsAreIgnored(): void
    {
        $manifest = (new ManifestBuilder(['', '  ']))->build([
            'CRAFT_ENVIRONMENT' => 'production',
        ], []);

        $this->assertSame(['CRAFT_ENVIRONMENT' => 'production'], $manifest['env']);
    }

    public function testNonScalarValuesAreSkipped(): void
    {
        $manifest = (new ManifestBuilder())->build([
            'CRAFT_ENVIRONMENT' => ['nested'],
            'CRAFT_APP_ID' => 'abc',
        ], []);

        $this->assertSame(['CRAFT_APP_ID' => 'abc'], $manifest['env']);
    }

    public function testFactsArePassedThrough(): void
    {
        $facts = [
            'php' => ['version' => '8.3.0', 'extensions' => ['sodium']],
            'database' => ['driver' => 'mysql', 'version' => '8.0.36'],
            'configVersion' => 'abcdef123456',
            'craft' => ['version' => '5.9.0', 'edition' => 'Pro'],
            'plugins' => ['ledge' => '5.0.7'],
        ];

        $manifest = (new ManifestBuilder())->build(['CRAFT_ENVIRONMENT' => 'production'], $facts);

        $this->assertSame(['CRAFT_ENVIRONMENT' => 'production'], $manifest['env']);
        $this->assertSame('abcdef123456', $manifest['configVersion']);
        $this->assertSame($facts['php'], $manifest['php']);
        $this->assertSame($facts['database'], $manifest['database']);
        $this->assertSame($facts['craft'], $manifest['craft']);
        $this->assertSame($facts['plugins'], $manifest['plugins']);
    }
}
