<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\SiteManifestBuilder;
use PHPUnit\Framework\TestCase;

final class SiteManifestBuilderTest extends TestCase
{
    public function testEmitsResolvedBaseUrlAndRootPath(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'default', 'primary' => true, 'enabled' => true, 'language' => 'en-US', 'baseUrl' => 'https://besser.com/'],
        ]);

        $this->assertSame([[
            'handle' => 'default',
            'primary' => true,
            'enabled' => true,
            'language' => 'en-US',
            'baseUrl' => 'https://besser.com',
            'host' => 'besser.com',
            'path' => '/',
        ]], $sites);
    }

    public function testHostDistinguishedSecondSiteKeepsRootPath(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'default', 'primary' => true, 'language' => 'en-US', 'baseUrl' => 'https://besser.com'],
            ['handle' => 'besserFrench', 'primary' => false, 'language' => 'fr-CA', 'baseUrl' => 'https://fr.besser.com'],
        ]);

        $this->assertSame('besserFrench', $sites[1]['handle']);
        $this->assertSame('https://fr.besser.com', $sites[1]['baseUrl']);
        $this->assertSame('fr.besser.com', $sites[1]['host']);
        $this->assertSame('/', $sites[1]['path']);
    }

    public function testPathMountedSiteExposesItsPrefix(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'fr', 'baseUrl' => 'https://example.com/fr/'],
        ]);

        $this->assertSame('https://example.com/fr', $sites[0]['baseUrl']);
        $this->assertSame('example.com', $sites[0]['host']);
        $this->assertSame('/fr', $sites[0]['path']);
    }

    public function testUnresolvedBaseUrlBecomesNullWithRootPath(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'default', 'baseUrl' => null],
            ['handle' => 'blank', 'baseUrl' => '   '],
        ]);

        $this->assertNull($sites[0]['baseUrl']);
        $this->assertNull($sites[0]['host']);
        $this->assertSame('/', $sites[0]['path']);
        $this->assertNull($sites[1]['baseUrl']);
    }

    public function testHostIsLowercasedAndHostlessBaseUrlYieldsNullHost(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'upper', 'baseUrl' => 'https://Example.COM/Fr'],
            ['handle' => 'relative', 'baseUrl' => '/fr'],
        ]);

        $byHandle = array_column($sites, null, 'handle');

        $this->assertSame('example.com', $byHandle['upper']['host']);
        $this->assertSame('/Fr', $byHandle['upper']['path']);
        $this->assertNull($byHandle['relative']['host']);
        $this->assertSame('/fr', $byHandle['relative']['path']);
    }

    public function testDisabledSiteIsRetainedAndFlagged(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'default', 'primary' => true, 'enabled' => true, 'baseUrl' => 'https://example.com'],
            ['handle' => 'staging', 'primary' => false, 'enabled' => false, 'baseUrl' => 'https://staging.example.com'],
        ]);

        $this->assertCount(2, $sites);
        $this->assertFalse($sites[1]['enabled']);
        $this->assertSame('staging.example.com', $sites[1]['host']);
    }

    public function testSortsPrimaryFirstThenByHandle(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => 'zeta', 'primary' => false],
            ['handle' => 'alpha', 'primary' => false],
            ['handle' => 'main', 'primary' => true],
        ]);

        $this->assertSame(['main', 'alpha', 'zeta'], array_column($sites, 'handle'));
    }

    public function testDropsRowsWithoutHandleAndDedupesByHandle(): void
    {
        $sites = (new SiteManifestBuilder())->build([
            ['handle' => '', 'baseUrl' => 'https://a.test'],
            ['handle' => null],
            ['handle' => 'default', 'baseUrl' => 'https://old.test'],
            ['handle' => 'default', 'baseUrl' => 'https://new.test'],
        ]);

        $this->assertCount(1, $sites);
        $this->assertSame('https://new.test', $sites[0]['baseUrl']);
    }

    public function testDefaultsEnabledTrueAndLanguageNull(): void
    {
        $sites = (new SiteManifestBuilder())->build([['handle' => 'default']]);

        $this->assertTrue($sites[0]['enabled']);
        $this->assertFalse($sites[0]['primary']);
        $this->assertNull($sites[0]['language']);
    }
}
