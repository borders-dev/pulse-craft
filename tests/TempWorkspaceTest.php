<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\TempWorkspace;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TempWorkspaceTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/ledge-workspace-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @rmdir($this->baseDir);
    }

    public function testCleanupRemovesWorkspaceOnSuccess(): void
    {
        $workspace = new TempWorkspace($this->baseDir, 'run-1');
        $workspace->create();
        file_put_contents($workspace->path('dump.sql'), 'data');
        mkdir($workspace->path('nested'));
        file_put_contents($workspace->path('nested/file.txt'), 'data');

        $workspace->cleanup();

        $this->assertDirectoryDoesNotExist($workspace->getDir());
    }

    public function testCleanupRunsAfterFailure(): void
    {
        $workspace = new TempWorkspace($this->baseDir, 'run-2');
        $workspace->create();
        file_put_contents($workspace->path('bundle.tar.gz'), 'partial');

        try {
            try {
                throw new RuntimeException('dump blew up mid-run');
            } finally {
                $workspace->cleanup();
            }
        } catch (RuntimeException) {
        }

        $this->assertDirectoryDoesNotExist($workspace->getDir());
    }

    public function testCleanupRemovesTrackedExternalFiles(): void
    {
        $external = $this->baseDir . '-external.sql';
        file_put_contents($external, 'dump');

        $workspace = new TempWorkspace($this->baseDir, 'run-3');
        $workspace->create();
        $workspace->trackFile($external);

        $workspace->cleanup();

        $this->assertFileDoesNotExist($external);
        $this->assertDirectoryDoesNotExist($workspace->getDir());
    }

    public function testCleanupIsSafeWhenWorkspaceWasNeverCreated(): void
    {
        $workspace = new TempWorkspace($this->baseDir, 'run-4');

        $workspace->cleanup();

        $this->assertDirectoryDoesNotExist($workspace->getDir());
    }
}
