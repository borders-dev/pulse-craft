<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\Preflight;
use PHPUnit\Framework\TestCase;

final class PreflightTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ledge-preflight-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->tmpDir);
    }

    public function testPassesWhenEverythingIsAvailable(): void
    {
        $this->expectNotToPerformAssertions();

        $this->preflight()->run();
    }

    public function testFailsWhenDumpCommandIsNotResolvable(): void
    {
        $this->assertFailsWith('no_dump_command', $this->preflight(resolveDumpCommand: fn(): bool => false));
    }

    public function testFailsWhenTmpDirIsMissing(): void
    {
        $this->assertFailsWith('tmp_not_writable', $this->preflight(tmpDir: $this->tmpDir . '/does-not-exist'));
    }

    public function testFailsWhenDiskHeadroomIsInsufficient(): void
    {
        $this->assertFailsWith(
            'insufficient_disk',
            $this->preflight(freeBytes: fn(string $dir): int => 100, estimatedDumpBytes: 1000),
        );
    }

    public function testFailsWhenFreeSpaceCannotBeDetermined(): void
    {
        $this->assertFailsWith('insufficient_disk', $this->preflight(freeBytes: fn(string $dir): bool => false));
    }

    public function testFailsWhenSodiumIsUnavailable(): void
    {
        $this->assertFailsWith('sodium_unavailable', $this->preflight(sodiumAvailable: false));
    }

    public function testRequiresTwiceTheEstimatedDumpSize(): void
    {
        $this->assertFailsWith(
            'insufficient_disk',
            $this->preflight(freeBytes: fn(string $dir): int => 1999, estimatedDumpBytes: 1000),
        );

        $this->preflight(freeBytes: fn(string $dir): int => 2000, estimatedDumpBytes: 1000)->run();
    }

    public function testFailsWhenDumpSizeCannotBeEstimated(): void
    {
        $this->assertFailsWith(
            'insufficient_disk',
            $this->preflight(freeBytes: fn(string $dir): int => PHP_INT_MAX, estimatedDumpBytes: null),
        );
    }

    private function preflight(
        ?callable $resolveDumpCommand = null,
        ?callable $freeBytes = null,
        ?int $estimatedDumpBytes = 1000,
        ?string $tmpDir = null,
        bool $sodiumAvailable = true,
    ): Preflight {
        return new Preflight(
            $resolveDumpCommand !== null ? $resolveDumpCommand(...) : fn(): bool => true,
            $freeBytes !== null ? $freeBytes(...) : fn(string $dir): int => PHP_INT_MAX,
            $estimatedDumpBytes,
            $tmpDir ?? $this->tmpDir,
            $sodiumAvailable,
        );
    }

    private function assertFailsWith(string $reason, Preflight $preflight): void
    {
        try {
            $preflight->run();
            $this->fail("Expected AcquireException with reason \"{$reason}\"");
        } catch (AcquireException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
