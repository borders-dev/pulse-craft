<?php

declare(strict_types=1);

namespace ledgehq\craftledge\acquire;

class AcquireCommand
{
    public function __construct(
        public string $runId,
        public int $expiresAt,
        public string $uploadUrl,
        public string $bundlePubkey,
        public string $profile,
        public string $callbackUrl,
        public string $callbackToken,
        public string $keyId,
    ) {
    }

    public function getBundlePubkeyBytes(): ?string
    {
        $bytes = base64_decode($this->bundlePubkey, true);

        if ($bytes === false || strlen($bytes) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            return null;
        }

        return $bytes;
    }
}
