<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

class BundleResult
{
    public function __construct(
        public readonly string $path,
        public readonly int $size,
        public readonly string $sha256,
    ) {
    }
}
