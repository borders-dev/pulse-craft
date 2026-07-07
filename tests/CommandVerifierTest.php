<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\CommandVerifier;
use ledgehq\craftledge\acquire\HostAllowlist;
use PHPUnit\Framework\TestCase;

final class CommandVerifierTest extends TestCase
{
    private const ALLOWED_HOSTS = ['storage.ledgehq.app', 'app.ledgehq.app'];
    private const KEY_ID = 'a1b2c3d4';
    private const NOW = 1000;

    private string $publicKey;
    private string $secretKey;

    protected function setUp(): void
    {
        $keypair = sodium_crypto_sign_keypair();
        $this->publicKey = sodium_crypto_sign_publickey($keypair);
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
    }

    public function testValidCommandVerifies(): void
    {
        $command = $this->verifier()->verify($this->body($this->command()));

        $this->assertSame('run-123', $command->runId);
        $this->assertSame(self::NOW + 600, $command->expiresAt);
        $this->assertSame('https://storage.ledgehq.app/bundles/run-123', $command->uploadUrl);
        $this->assertSame('full', $command->profile);
        $this->assertSame('https://app.ledgehq.app/api/acquire/callback', $command->callbackUrl);
        $this->assertSame('callback-token', $command->callbackToken);
        $this->assertSame(self::KEY_ID, $command->keyId);
        $this->assertNotNull($command->getBundlePubkeyBytes());
    }

    public function testKeyOrderDoesNotMatter(): void
    {
        $command = array_reverse($this->command(), true);

        $verified = $this->verifier()->verify($this->body($command));

        $this->assertSame('run-123', $verified->runId);
    }

    public function testCallbackTokenIsNotCoveredBySignature(): void
    {
        $command = $this->command();
        $signature = $this->sign($command);
        $command['callback_token'] = 'rotated-token';

        $verified = $this->verifier()->verify($this->body($command, $signature));

        $this->assertSame('rotated-token', $verified->callbackToken);
    }

    public function testSignatureFromWrongKeyIsRejected(): void
    {
        $otherKeypair = sodium_crypto_sign_keypair();
        $command = $this->command();
        $signature = base64_encode(sodium_crypto_sign_detached(
            CommandVerifier::canonicalize($command),
            sodium_crypto_sign_secretkey($otherKeypair),
        ));

        $this->assertRejected($this->body($command, $signature), 'invalid_signature', 401);
    }

    public function testTamperedCommandIsRejected(): void
    {
        $command = $this->command();
        $signature = $this->sign($command);
        $command['upload_url'] = 'https://storage.ledgehq.app/bundles/run-666';

        $this->assertRejected($this->body($command, $signature), 'invalid_signature', 401);
    }

    public function testUnknownKeyIdIsRejected(): void
    {
        $envelope = $this->body($this->command(['key_id' => 'unknown-key']));

        $this->assertRejected($envelope, 'unknown_key_id', 401);
    }

    public function testMalformedEnvelopeIsRejected(): void
    {
        $this->assertRejected('not json', 'invalid_envelope', 400);
        $this->assertRejected('{"command": {}}', 'invalid_envelope', 400);
        $this->assertRejected('{"command": "flat", "signature": "x"}', 'invalid_envelope', 400);
        $this->assertRejected(
            json_encode(['command' => $this->command(), 'signature' => base64_encode('short')]),
            'invalid_envelope',
            400,
        );
    }

    public function testExpiredCommandIsRejected(): void
    {
        $envelope = $this->body($this->command(['expires_at' => $this->iso(self::NOW)]));

        $this->assertRejected($envelope, 'expired', 403);
    }

    public function testInvalidExpiresAtIsRejected(): void
    {
        $envelope = $this->body($this->command(['expires_at' => 'not-a-date']));

        $this->assertRejected($envelope, 'invalid_payload', 400);
    }

    public function testMissingFieldIsRejected(): void
    {
        $command = $this->command();
        unset($command['key_id']);

        $this->assertRejected($this->body($command), 'invalid_payload', 400);
    }

    public function testInvalidRunIdIsRejected(): void
    {
        $envelope = $this->body($this->command(['run_id' => 'no spaces allowed!']));

        $this->assertRejected($envelope, 'invalid_payload', 400);
    }

    public function testInvalidBundlePubkeyIsRejected(): void
    {
        $envelope = $this->body($this->command(['bundle_pubkey' => base64_encode('too short')]));

        $this->assertRejected($envelope, 'invalid_payload', 400);
    }

    public function testBackupProfileVerifies(): void
    {
        $command = $this->verifier()->verify($this->body($this->command(['profile' => 'backup'])));

        $this->assertSame('backup', $command->profile);
    }

    public function testUnsupportedProfileIsRejected(): void
    {
        $envelope = $this->body($this->command(['profile' => 'sanitized']));

        $this->assertRejected($envelope, 'invalid_profile', 400);
    }

    public function testDisallowedUploadHostIsRejected(): void
    {
        $envelope = $this->body($this->command(['upload_url' => 'https://evil.example.com/bundles/run-123']));

        $this->assertRejected($envelope, 'host_not_allowed', 403);
    }

    public function testDisallowedCallbackHostIsRejected(): void
    {
        $envelope = $this->body($this->command(['callback_url' => 'https://evil.example.com/callback']));

        $this->assertRejected($envelope, 'host_not_allowed', 403);
    }

    public function testInsecureUploadSchemeIsRejected(): void
    {
        $envelope = $this->body($this->command(['upload_url' => 'http://storage.ledgehq.app/bundles/run-123']));

        $this->assertRejected($envelope, 'host_not_allowed', 403);
    }

    private function command(array $overrides = []): array
    {
        $bundleKeypair = sodium_crypto_box_keypair();

        return array_merge([
            'run_id' => 'run-123',
            'expires_at' => $this->iso(self::NOW + 600),
            'upload_url' => 'https://storage.ledgehq.app/bundles/run-123',
            'bundle_pubkey' => base64_encode(sodium_crypto_box_publickey($bundleKeypair)),
            'profile' => 'full',
            'callback_url' => 'https://app.ledgehq.app/api/acquire/callback',
            'callback_token' => 'callback-token',
            'key_id' => self::KEY_ID,
        ], $overrides);
    }

    private function sign(array $command): string
    {
        return base64_encode(sodium_crypto_sign_detached(CommandVerifier::canonicalize($command), $this->secretKey));
    }

    private function body(array $command, ?string $signature = null): string
    {
        return json_encode([
            'command' => $command,
            'signature' => $signature ?? $this->sign($command),
        ], JSON_UNESCAPED_SLASHES);
    }

    private function iso(int $timestamp): string
    {
        return date(DATE_ATOM, $timestamp);
    }

    private function verifier(): CommandVerifier
    {
        return new CommandVerifier(
            function(string $keyId): string {
                if ($keyId !== self::KEY_ID) {
                    throw new AcquireException('unknown_key_id', 401);
                }

                return $this->publicKey;
            },
            new HostAllowlist(self::ALLOWED_HOSTS),
            fn(): int => self::NOW,
        );
    }

    private function assertRejected(string $envelope, string $reason, int $httpStatus): void
    {
        try {
            $this->verifier()->verify($envelope);
            $this->fail("Expected AcquireException with reason \"{$reason}\"");
        } catch (AcquireException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertSame($httpStatus, $e->httpStatus);
        }
    }
}
