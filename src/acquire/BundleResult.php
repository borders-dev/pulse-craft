<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

class BundleResult
{
    public function __construct(
        public string $path,
        public int $size,
        public string $sha256,
        public int $uncompressedSize,
    ) {
    }
}
