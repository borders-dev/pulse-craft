<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\HostAllowlist;
use PHPUnit\Framework\TestCase;

final class HostAllowlistTest extends TestCase
{
    public function testExactHostMatch(): void
    {
        $allowlist = new HostAllowlist(['app.ledgehq.app']);

        $this->assertTrue($allowlist->allows('https://app.ledgehq.app/callback'));
        $this->assertFalse($allowlist->allows('https://other.ledgehq.app/callback'));
        $this->assertFalse($allowlist->allows('https://app.ledgehq.app.evil.com/callback'));
    }

    public function testMatchingIsCaseInsensitive(): void
    {
        $allowlist = new HostAllowlist(['App.LedgeHQ.app']);

        $this->assertTrue($allowlist->allows('https://APP.ledgehq.APP/callback'));
    }

    public function testWildcardSubdomainMatch(): void
    {
        $allowlist = new HostAllowlist(['*.ledgehq.app']);

        $this->assertTrue($allowlist->allows('https://storage.ledgehq.app/bundle'));
        $this->assertTrue($allowlist->allows('https://a.b.ledgehq.app/bundle'));
        $this->assertFalse($allowlist->allows('https://ledgehq.app/bundle'));
        $this->assertFalse($allowlist->allows('https://evilledgehq.app/bundle'));
    }

    public function testHttpsIsRequiredByDefault(): void
    {
        $allowlist = new HostAllowlist(['app.ledgehq.app']);

        $this->assertFalse($allowlist->allows('http://app.ledgehq.app/callback'));
        $this->assertFalse($allowlist->allows('ftp://app.ledgehq.app/callback'));
    }

    public function testHttpIsAllowedWhenInsecureIsEnabled(): void
    {
        $allowlist = new HostAllowlist(['site.ddev.site'], true);

        $this->assertTrue($allowlist->allows('http://site.ddev.site/callback'));
        $this->assertFalse($allowlist->allows('ftp://site.ddev.site/callback'));
    }

    public function testEmptyAllowlistAllowsNothing(): void
    {
        $allowlist = new HostAllowlist([]);

        $this->assertFalse($allowlist->allows('https://app.ledgehq.app/callback'));
    }

    public function testUnparseableUrlIsRejected(): void
    {
        $allowlist = new HostAllowlist(['app.ledgehq.app']);

        $this->assertFalse($allowlist->allows('not a url'));
        $this->assertFalse($allowlist->allows(''));
    }
}
