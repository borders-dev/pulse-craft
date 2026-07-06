<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\BackupResultBuilder;
use ledgehq\craftledge\acquire\BundleResult;
use PHPUnit\Framework\TestCase;

final class BackupResultBuilderTest extends TestCase
{
    public function testPayloadReportsRicherBackupMetrics(): void
    {
        $result = new BundleResult('/tmp/bundle.enc', 4096, 'deadbeef', 20000);

        $payload = (new BackupResultBuilder())->payload($result, 1234, 87);

        $this->assertSame([
            'size' => 4096,
            'sha256' => 'deadbeef',
            'compressed_size' => 4096,
            'uncompressed_size' => 20000,
            'dump_duration_ms' => 1234,
            'table_count' => 87,
        ], $payload);
    }

    public function testPayloadToleratesUnknownTableCount(): void
    {
        $result = new BundleResult('/tmp/bundle.enc', 10, 'abc', 20);

        $payload = (new BackupResultBuilder())->payload($result, 5, null);

        $this->assertNull($payload['table_count']);
    }
}
