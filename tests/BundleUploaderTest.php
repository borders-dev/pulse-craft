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
use ledgehq\craftledge\acquire\BundleUploader;
use PHPUnit\Framework\TestCase;

final class BundleUploaderTest extends TestCase
{
    private const URL = 'https://ledge.ddev.site/api/bundles/run-123?expires=1&signature=abc';

    /** @var array<array{request: Request}> */
    private array $history = [];

    private string $bundlePath;

    protected function setUp(): void
    {
        $this->bundlePath = sys_get_temp_dir() . '/ledge-upload-' . bin2hex(random_bytes(4));
        file_put_contents($this->bundlePath, 'encrypted bundle bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->bundlePath);
    }

    public function testStreamsBundleViaPut(): void
    {
        $this->uploader([new Response(201, [], '{"ok":true,"bytes":22}')])->upload($this->bundlePath, self::URL);

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame(self::URL, (string)$request->getUri());
        $this->assertSame('application/octet-stream', $request->getHeaderLine('Content-Type'));
        $this->assertSame('22', $request->getHeaderLine('Content-Length'));
    }

    public function testAnyTwoxxIsSuccessAndBodyIsIgnored(): void
    {
        $uploader = $this->uploader([
            new Response(200),
            new Response(201, [], '{"ok":true}'),
            new Response(204, [], 'not even json'),
        ]);

        $uploader->upload($this->bundlePath, self::URL);
        $uploader->upload($this->bundlePath, self::URL);
        $uploader->upload($this->bundlePath, self::URL);

        $this->assertCount(3, $this->history);
    }

    public function testPayloadTooLargeMapsToBundleTooLarge(): void
    {
        $this->assertFailsWith('bundle_too_large', [new Response(413, [], '{"error":"bundle_too_large"}')]);
    }

    public function testConflictMapsToRunAlreadyFinished(): void
    {
        $this->assertFailsWith('run_already_finished', [new Response(409)]);
        $this->assertCount(1, $this->history);
    }

    public function testServerErrorMapsToUploadFailed(): void
    {
        $this->assertFailsWith('upload_failed', [new Response(500)]);
    }

    public function testConnectionFailureMapsToUploadFailed(): void
    {
        $this->assertFailsWith('upload_failed', [
            new ConnectException('refused', new Request('PUT', self::URL)),
        ]);
    }

    private function uploader(array $responses): BundleUploader
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new BundleUploader(new Client(['handler' => $stack]));
    }

    private function assertFailsWith(string $reason, array $responses): void
    {
        try {
            $this->uploader($responses)->upload($this->bundlePath, self::URL);
            $this->fail("Expected AcquireException with reason \"{$reason}\"");
        } catch (AcquireException $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
