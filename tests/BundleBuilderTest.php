<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\BundleBuilder;
use ledgehq\craftledge\acquire\TempWorkspace;
use PharData;
use PHPUnit\Framework\TestCase;

final class BundleBuilderTest extends TestCase
{
    private string $baseDir;
    private TempWorkspace $workspace;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/ledge-bundle-' . bin2hex(random_bytes(4));
        $this->workspace = new TempWorkspace($this->baseDir, 'run-test');
        $this->workspace->create();
    }

    protected function tearDown(): void
    {
        $this->workspace->cleanup();
        @rmdir($this->baseDir);
    }

    public function testBundleRoundtrip(): void
    {
        $keypair = sodium_crypto_box_keypair();
        $dumpContent = str_repeat("INSERT INTO entries VALUES ('hello world');\n", 500);
        $dumpPath = $this->workspace->path('dump.sql');
        file_put_contents($dumpPath, $dumpContent);

        $manifest = ['env' => ['CRAFT_ENVIRONMENT' => 'production'], 'configVersion' => 'abc123'];

        $result = (new BundleBuilder())->build(
            $dumpPath,
            $manifest,
            sodium_crypto_box_publickey($keypair),
            PHP_INT_MAX,
            $this->workspace,
        );

        $this->assertFileExists($result->path);
        $this->assertSame(filesize($result->path), $result->size);
        $this->assertSame(hash_file('sha256', $result->path), $result->sha256);

        [$decryptedDump, $decryptedManifest] = $this->decryptBundle($result->path, $keypair);

        $this->assertSame($dumpContent, $decryptedDump);
        $this->assertSame($manifest, json_decode($decryptedManifest, true));
    }

    public function testOversizedBundleFailsBeforeEncryption(): void
    {
        $keypair = sodium_crypto_box_keypair();
        $dumpPath = $this->workspace->path('dump.sql');
        file_put_contents($dumpPath, random_bytes(100000));

        try {
            (new BundleBuilder())->build(
                $dumpPath,
                ['env' => []],
                sodium_crypto_box_publickey($keypair),
                10,
                $this->workspace,
            );
            $this->fail('Expected AcquireException with reason "bundle_too_large"');
        } catch (AcquireException $e) {
            $this->assertSame('bundle_too_large', $e->reason);
        }

        $this->assertFileDoesNotExist($this->workspace->path('bundle.tar.gz.enc'));
    }

    /**
     * Mirrors the decrypt logic the runner implements:
     * unseal the symmetric key, stream-decrypt, gunzip, untar.
     *
     * @return array{0: string, 1: string} dump.sql and manifest.json contents
     */
    private function decryptBundle(string $encryptedPath, string $keypair): array
    {
        $in = fopen($encryptedPath, 'rb');

        $sealedKey = fread($in, SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $key = sodium_crypto_box_seal_open($sealedKey, $keypair);
        $this->assertNotFalse($key, 'sealed key must open with the bundle keypair');

        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);

        $archive = '';
        $cipherChunkSize = BundleBuilder::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

        do {
            $cipherChunk = fread($in, $cipherChunkSize);
            [$plain, $tag] = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipherChunk);
            $archive .= $plain;
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);

        fclose($in);

        $tgzPath = $this->workspace->path('decrypted.tar.gz');
        file_put_contents($tgzPath, $archive);

        $extractDir = $this->workspace->path('extracted');
        mkdir($extractDir);
        (new PharData($tgzPath))->extractTo($extractDir);

        return [
            (string)file_get_contents($extractDir . '/dump.sql'),
            (string)file_get_contents($extractDir . '/manifest.json'),
        ];
    }
}
