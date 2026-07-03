<?php

declare(strict_types=1);

namespace ledgehq\craftledge\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ledgehq\craftledge\acquire\AcquireException;
use ledgehq\craftledge\acquire\KeyResolver;
use PHPUnit\Framework\TestCase;
use yii\caching\ArrayCache;

final class KeyResolverTest extends TestCase
{
    private const BASE_URL = 'https://my.ledgehq.app';

    /** @var array<array{request: Request}> */
    private array $history = [];

    private ArrayCache $cache;
    private string $publicKeyA;
    private string $publicKeyB;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
        $this->publicKeyA = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
        $this->publicKeyB = sodium_crypto_sign_publickey(sodium_crypto_sign_keypair());
    }

    public function testFetchesKeysetOnCacheMiss(): void
    {
        $resolver = $this->resolver([$this->keysetResponse(['key-a' => $this->publicKeyA])]);

        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
        $this->assertCount(1, $this->history);
        $this->assertSame(
            self::BASE_URL . KeyResolver::KEYSET_PATH,
            (string)$this->history[0]['request']->getUri(),
        );
    }

    public function testUsesCachedKeysetWithoutRefetching(): void
    {
        $resolver = $this->resolver([$this->keysetResponse(['key-a' => $this->publicKeyA])]);

        $resolver->resolve('key-a');
        $resolver->resolve('key-a');

        $this->assertCount(1, $this->history);
    }

    public function testRefetchesOnceForUnknownKeyIdThenFails(): void
    {
        $resolver = $this->resolver([
            $this->keysetResponse(['key-a' => $this->publicKeyA]),
            $this->keysetResponse(['key-a' => $this->publicKeyA]),
        ]);

        $resolver->resolve('key-a');

        try {
            $resolver->resolve('key-nope');
            $this->fail('Expected AcquireException with reason "unknown_key_id"');
        } catch (AcquireException $e) {
            $this->assertSame('unknown_key_id', $e->reason);
            $this->assertSame(401, $e->httpStatus);
        }

        $this->assertCount(2, $this->history);
    }

    public function testResolvesNewKeyIdAfterRefetch(): void
    {
        $resolver = $this->resolver([
            $this->keysetResponse(['key-a' => $this->publicKeyA]),
            $this->keysetResponse(['key-a' => $this->publicKeyA, 'key-b' => $this->publicKeyB]),
        ]);

        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
        $this->assertSame($this->publicKeyB, $resolver->resolve('key-b'));
    }

    public function testPinsSurviveKeysetCacheExpiry(): void
    {
        $resolver = $this->resolver([
            $this->keysetResponse(['key-a' => $this->publicKeyA]),
            $this->keysetResponse(['key-a' => $this->publicKeyB, 'key-b' => $this->publicKeyB]),
        ]);

        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));

        $this->cache->delete(KeyResolver::KEYSET_CACHE_KEY);

        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
        $this->assertCount(1, $this->history);

        $this->assertSame($this->publicKeyB, $resolver->resolve('key-b'));
        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
        $this->assertCount(2, $this->history);
    }

    public function testNeverSilentlyReplacesACachedKey(): void
    {
        $resolver = $this->resolver([
            $this->keysetResponse(['key-a' => $this->publicKeyA]),
            $this->keysetResponse(['key-a' => $this->publicKeyB, 'key-b' => $this->publicKeyB]),
        ]);

        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
        $this->assertSame($this->publicKeyB, $resolver->resolve('key-b'));
        $this->assertSame($this->publicKeyA, $resolver->resolve('key-a'));
    }

    public function testNonEd25519KeysAreIgnored(): void
    {
        $response = new Response(200, [], json_encode([
            'keys' => [
                ['key_id' => 'key-rsa', 'alg' => 'rs256', 'public_key' => base64_encode($this->publicKeyA)],
            ],
        ]));

        $this->assertFailsWith('unknown_key_id', $this->resolver([$response]), 'key-rsa');
    }

    public function testKeysetEndpointErrorIsReported(): void
    {
        $this->assertFailsWith('keyset_unavailable', $this->resolver([new Response(500)]), 'key-a');
    }

    public function testKeysetConnectionFailureIsReported(): void
    {
        $failure = new ConnectException('refused', new Request('GET', self::BASE_URL . KeyResolver::KEYSET_PATH));

        $this->assertFailsWith('keyset_unavailable', $this->resolver([$failure]), 'key-a');
    }

    public function testMalformedKeysetIsReported(): void
    {
        $this->assertFailsWith('keyset_unavailable', $this->resolver([new Response(200, [], 'not json')]), 'key-a');
    }

    public function testInvalidPublicKeyMaterialIsRejected(): void
    {
        $response = new Response(200, [], json_encode([
            'keys' => [
                ['key_id' => 'key-a', 'alg' => 'ed25519', 'public_key' => base64_encode('too short')],
            ],
        ]));

        $this->assertFailsWith('unknown_key_id', $this->resolver([$response]), 'key-a');
    }

    private function keysetResponse(array $keys): Response
    {
        return new Response(200, [], json_encode([
            'keys' => array_map(
                fn(string $keyId, string $publicKey): array => [
                    'key_id' => $keyId,
                    'alg' => 'ed25519',
                    'public_key' => base64_encode($publicKey),
                ],
                array_keys($keys),
                $keys,
            ),
        ]));
    }

    private function resolver(array $responses): KeyResolver
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new KeyResolver(new Client(['handler' => $stack]), $this->cache, self::BASE_URL);
    }

    private function assertFailsWith(string $reason, KeyResolver $resolver, string $keyId): void
    {
        try {
            $resolver->resolve($keyId);
            $this->fail("Expected AcquireException with reason \"{$reason}\"");
        } catch (AcquireException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
