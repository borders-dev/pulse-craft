<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\services\DependenciesService;
use PHPUnit\Framework\TestCase;

final class DependenciesServiceTest extends TestCase
{
    /**
     * Ledge compares this hash between the health payload and the
     * dependencies endpoint; any change to normalization or encoding shows
     * up in production as a refetch on every poll, so the value is pinned.
     */
    private const FIXTURE_HASH = 'c78b87ee19f29b7b2e52d69853c219eb447b112487a34a74beef5fa57dd9d864';

    private DependenciesService $service;

    protected function setUp(): void
    {
        $this->service = new DependenciesService();
    }

    public function testNormalizationDropsRootAndVersionlessLowercasesStripsVAndSorts(): void
    {
        $packages = $this->service->normalizePackages([
            'verbb/formie' => 'v2.1.0',
            'CraftCMS/CMS' => '4.5.0',
            'acme/site' => 'dev-main',
            'some/metapackage' => null,
        ], 'acme/site');

        self::assertSame([
            ['name' => 'craftcms/cms', 'version' => '4.5.0'],
            ['name' => 'verbb/formie', 'version' => '2.1.0'],
        ], $packages);
    }

    public function testHashIsPinnedForAFixedInventory(): void
    {
        $packages = $this->service->normalizePackages([
            'verbb/formie' => 'v2.1.0',
            'CraftCMS/CMS' => '4.5.0',
            'acme/site' => '1.0.0',
        ], 'acme/site');

        self::assertSame(self::FIXTURE_HASH, $this->service->getHash($packages));
    }

    public function testHashIgnoresInputOrder(): void
    {
        $a = $this->service->normalizePackages(['b/b' => '1.0.0', 'a/a' => '1.0.0'], null);
        $b = $this->service->normalizePackages(['a/a' => '1.0.0', 'b/b' => '1.0.0'], null);

        self::assertSame($this->service->getHash($a), $this->service->getHash($b));
    }

    public function testComposerLockHashIsSha256OfTheRawBytes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ledge-lock-');
        file_put_contents($path, "{\n    \"content-hash\": \"abc\"\n}\n");

        try {
            self::assertSame(hash('sha256', "{\n    \"content-hash\": \"abc\"\n}\n"), $this->service->getComposerLockHash($path));
        } finally {
            unlink($path);
        }
    }

    public function testComposerLockHashIsNullWhenTheFileIsMissing(): void
    {
        self::assertNull($this->service->getComposerLockHash(sys_get_temp_dir() . '/ledge-does-not-exist/composer.lock'));
    }
}
