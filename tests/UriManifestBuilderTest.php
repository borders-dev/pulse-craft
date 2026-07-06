<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\UriManifestBuilder;
use PHPUnit\Framework\TestCase;

final class UriManifestBuilderTest extends TestCase
{
    public function testHomepageSentinelBecomesSlash(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => '__home__', 'siteHandle' => 'default'],
        ]);

        $this->assertSame([['uri' => '/', 'site' => 'default']], $uris);
    }

    public function testAddsLeadingSlashToElementUris(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/sample-post', 'siteHandle' => 'default'],
        ]);

        $this->assertSame([['uri' => '/blog/sample-post', 'site' => 'default']], $uris);
    }

    public function testIncludesEntriesCategoriesAndProducts(): void
    {
        // Rows as they arrive from the Entry / Category / Commerce Product queries.
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/hello-world', 'siteHandle' => 'default'],   // entry
            ['uri' => 'categories/news', 'siteHandle' => 'default'],    // category
            ['uri' => 'shop/blue-widget', 'siteHandle' => 'default'],   // commerce product
        ]);

        $this->assertContains(['uri' => '/blog/hello-world', 'site' => 'default'], $uris);
        $this->assertContains(['uri' => '/categories/news', 'site' => 'default'], $uris);
        $this->assertContains(['uri' => '/shop/blue-widget', 'site' => 'default'], $uris);
    }

    public function testCarriesSiteHandle(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'ueber-uns', 'siteHandle' => 'german'],
        ]);

        $this->assertSame('german', $uris[0]['site']);
    }

    public function testDedupesExactUriSitePairs(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/post', 'siteHandle' => 'default'],
            ['uri' => 'blog/post', 'siteHandle' => 'default'],
            ['uri' => '/blog/post', 'siteHandle' => 'default'],
        ]);

        $this->assertSame([['uri' => '/blog/post', 'site' => 'default']], $uris);
    }

    public function testKeepsTheSameUriOnDifferentSites(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => '__home__', 'siteHandle' => 'default'],
            ['uri' => '__home__', 'siteHandle' => 'german'],
        ]);

        $this->assertCount(2, $uris);
        $this->assertContains(['uri' => '/', 'site' => 'default'], $uris);
        $this->assertContains(['uri' => '/', 'site' => 'german'], $uris);
    }

    public function testExcludesEmptyOrNullUris(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => null, 'siteHandle' => 'default'],
            ['uri' => '', 'siteHandle' => 'default'],
            ['uri' => 'valid', 'siteHandle' => 'default'],
        ]);

        $this->assertSame([['uri' => '/valid', 'site' => 'default']], $uris);
    }

    public function testExcludesRowsWithoutASiteHandle(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'valid', 'siteHandle' => null],
            ['uri' => 'valid', 'siteHandle' => ''],
        ]);

        $this->assertSame([], $uris);
    }

    public function testOutputIsSortedBySiteThenUri(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'zebra', 'siteHandle' => 'default'],
            ['uri' => 'apple', 'siteHandle' => 'default'],
            ['uri' => 'apple', 'siteHandle' => 'aaa'],
        ]);

        $this->assertSame([
            ['uri' => '/apple', 'site' => 'aaa'],
            ['uri' => '/apple', 'site' => 'default'],
            ['uri' => '/zebra', 'site' => 'default'],
        ], $uris);
    }
}
