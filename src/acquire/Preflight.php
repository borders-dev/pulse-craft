<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

use Closure;

class Preflight
{
    public function __construct(
        private readonly Closure $resolveDumpCommand,
        private readonly Closure $freeBytes,
        private readonly ?int $estimatedDumpBytes,
        private readonly string $tmpDir,
        private readonly bool $sodiumAvailable = true,
    ) {
    }

    public function run(): void
    {
        if (!($this->resolveDumpCommand)()) {
            throw new AcquireException('no_dump_command', 500, 'database dump command is not resolvable');
        }

        if (!is_dir($this->tmpDir) || !is_writable($this->tmpDir)) {
            throw new AcquireException('tmp_not_writable', 500, $this->tmpDir);
        }

        if ($this->estimatedDumpBytes === null) {
            throw new AcquireException('insufficient_disk', 500, 'unable to estimate dump size');
        }

        $required = $this->estimatedDumpBytes * 2;
        $free = ($this->freeBytes)($this->tmpDir);

        if (!is_int($free) && !is_float($free)) {
            throw new AcquireException('insufficient_disk', 500, 'unable to determine free disk space');
        }

        if ($free < $required) {
            throw new AcquireException('insufficient_disk', 500, sprintf('%d bytes free, %d required', (int)$free, $required));
        }

        if (!$this->sodiumAvailable) {
            throw new AcquireException('sodium_unavailable', 500);
        }
    }
}
