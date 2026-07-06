<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

/**
 * Shapes the richer `acquire.completed` callback detail for a backup run:
 * sealed size + sha256, uncompressed/compressed byte sizes, dump duration,
 * and table count. None of this is secret — it's operational telemetry for
 * scheduled backups. Craft-free so the inputs are injected and it stays
 * testable.
 */
class BackupResultBuilder
{
    public function payload(BundleResult $result, int $dumpDurationMs, ?int $tableCount): array
    {
        return [
            'size' => $result->size,
            'sha256' => $result->sha256,
            'compressed_size' => $result->size,
            'uncompressed_size' => $result->uncompressedSize,
            'dump_duration_ms' => $dumpDurationMs,
            'table_count' => $tableCount,
        ];
    }
}
