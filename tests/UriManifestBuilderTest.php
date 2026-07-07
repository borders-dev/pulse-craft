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
            ['uri' => '__home__', 'siteHandle' => 'default', 'section' => 'pages'],
        ]);

        $this->assertSame([['uri' => '/', 'site' => 'default', 'section' => 'pages']], $uris);
    }

    public function testAddsLeadingSlashToElementUris(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/sample-post', 'siteHandle' => 'default', 'section' => 'blog'],
        ]);

        $this->assertSame([['uri' => '/blog/sample-post', 'site' => 'default', 'section' => 'blog']], $uris);
    }

    public function testIncludesEntriesCategoriesAndProducts(): void
    {
        // Rows as they arrive from the Entry / Category / Commerce Product queries.
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/hello-world', 'siteHandle' => 'default', 'section' => 'blog'],  // entry
            ['uri' => 'categories/news', 'siteHandle' => 'default', 'section' => null],      // category
            ['uri' => 'shop/blue-widget', 'siteHandle' => 'default', 'section' => null],     // commerce product
        ]);

        $this->assertContains(['uri' => '/blog/hello-world', 'site' => 'default', 'section' => 'blog'], $uris);
        $this->assertContains(['uri' => '/categories/news', 'site' => 'default', 'section' => null], $uris);
        $this->assertContains(['uri' => '/shop/blue-widget', 'site' => 'default', 'section' => null], $uris);
    }

    public function testCarriesSectionHandleForEntries(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'news/launch', 'siteHandle' => 'default', 'section' => 'news'],
        ]);

        $this->assertSame('news', $uris[0]['section']);
    }

    public function testSectionIsNullForSectionlessElements(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'categories/news', 'siteHandle' => 'default', 'section' => null],
            ['uri' => 'shop/widget', 'siteHandle' => 'default'],
            ['uri' => 'x', 'siteHandle' => 'default', 'section' => ''],
        ]);

        foreach ($uris as $entry) {
            $this->assertNull($entry['section']);
        }
    }

    public function testCarriesSiteHandle(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'ueber-uns', 'siteHandle' => 'german', 'section' => 'pages'],
        ]);

        $this->assertSame('german', $uris[0]['site']);
    }

    public function testDedupesExactUriSitePairs(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'blog/post', 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => 'blog/post', 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => '/blog/post', 'siteHandle' => 'default', 'section' => 'blog'],
        ]);

        $this->assertSame([['uri' => '/blog/post', 'site' => 'default', 'section' => 'blog']], $uris);
    }

    public function testKeepsTheSameUriOnDifferentSites(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => '__home__', 'siteHandle' => 'default', 'section' => 'pages'],
            ['uri' => '__home__', 'siteHandle' => 'german', 'section' => 'pages'],
        ]);

        $this->assertCount(2, $uris);
        $this->assertContains(['uri' => '/', 'site' => 'default', 'section' => 'pages'], $uris);
        $this->assertContains(['uri' => '/', 'site' => 'german', 'section' => 'pages'], $uris);
    }

    public function testExcludesEmptyOrNullUris(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => null, 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => '', 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => 'valid', 'siteHandle' => 'default', 'section' => 'blog'],
        ]);

        $this->assertSame([['uri' => '/valid', 'site' => 'default', 'section' => 'blog']], $uris);
    }

    public function testExcludesRowsWithoutASiteHandle(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'valid', 'siteHandle' => null, 'section' => 'blog'],
            ['uri' => 'valid', 'siteHandle' => '', 'section' => 'blog'],
        ]);

        $this->assertSame([], $uris);
    }

    public function testOutputIsSortedBySiteThenUri(): void
    {
        $uris = (new UriManifestBuilder())->build([
            ['uri' => 'zebra', 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => 'apple', 'siteHandle' => 'default', 'section' => 'blog'],
            ['uri' => 'apple', 'siteHandle' => 'aaa', 'section' => 'blog'],
        ]);

        $this->assertSame([
            ['uri' => '/apple', 'site' => 'aaa', 'section' => 'blog'],
            ['uri' => '/apple', 'site' => 'default', 'section' => 'blog'],
            ['uri' => '/zebra', 'site' => 'default', 'section' => 'blog'],
        ], $uris);
    }
}
