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
use ledgehq\craftledge\acquire\CallbackClient;
use PHPUnit\Framework\TestCase;

final class CallbackClientTest extends TestCase
{
    private const URL = 'https://app.ledgehq.app/api/acquire/callback';
    private const TOKEN = 'callback-token';

    /** @var array<array{request: Request}> */
    private array $history = [];

    public function testSendsAuthorizedJsonCallback(): void
    {
        $client = $this->client([new Response(200)]);

        $client->send(CallbackClient::EVENT_PROGRESS, 'dump');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(self::URL, (string)$request->getUri());
        $this->assertSame('Bearer ' . self::TOKEN, $request->getHeaderLine('Authorization'));
        $this->assertSame([
            'event' => 'acquire.progress',
            'step' => 'dump',
            'detail' => null,
        ], json_decode((string)$request->getBody(), true));
    }

    public function testSendsDetailPayload(): void
    {
        $client = $this->client([new Response(200)]);

        $client->send(CallbackClient::EVENT_COMPLETED, 'upload', ['size' => 42, 'sha256' => 'abc']);

        $body = json_decode((string)$this->history[0]['request']->getBody(), true);
        $this->assertSame(['size' => 42, 'sha256' => 'abc'], $body['detail']);
    }

    public function testRetriesOnceOnServerError(): void
    {
        $client = $this->client([new Response(500), new Response(200)]);

        $client->send(CallbackClient::EVENT_STARTED, 'preflight');

        $this->assertCount(2, $this->history);
    }

    public function testRetriesOnceOnConnectionFailure(): void
    {
        $client = $this->client([
            new ConnectException('refused', new Request('POST', self::URL)),
            new Response(200),
        ]);

        $client->send(CallbackClient::EVENT_STARTED, 'preflight');

        $this->assertCount(2, $this->history);
    }

    public function testDoesNotRetryOnClientError(): void
    {
        $client = $this->client([new Response(403)]);

        $client->send(CallbackClient::EVENT_STARTED, 'preflight');

        $this->assertCount(1, $this->history);
    }

    public function testDoesNotRetryOnFinishedRunConflict(): void
    {
        $client = $this->client([new Response(409)]);

        $client->send(CallbackClient::EVENT_PROGRESS, 'dump');

        $this->assertCount(1, $this->history);
    }

    public function testNeverThrowsWhenAllAttemptsFail(): void
    {
        $client = $this->client([
            new ConnectException('refused', new Request('POST', self::URL)),
            new ConnectException('refused', new Request('POST', self::URL)),
        ]);

        $client->send(CallbackClient::EVENT_FAILED, 'upload', ['reason' => 'upload_failed']);

        $this->assertCount(2, $this->history);
    }

    public function testEmitsExpectedEventSequenceForSuccessfulRun(): void
    {
        $responses = array_fill(0, 7, new Response(200));
        $client = $this->client($responses);

        $client->send(CallbackClient::EVENT_ACCEPTED, 'queued');
        $client->send(CallbackClient::EVENT_STARTED, 'preflight');
        $client->send(CallbackClient::EVENT_PROGRESS, 'dump');
        $client->send(CallbackClient::EVENT_PROGRESS, 'manifest');
        $client->send(CallbackClient::EVENT_PROGRESS, 'encrypt');
        $client->send(CallbackClient::EVENT_COMPLETED, 'upload', ['size' => 1, 'sha256' => 'a']);

        $events = array_map(
            fn(array $entry): string => json_decode((string)$entry['request']->getBody(), true)['event'],
            $this->history,
        );

        $this->assertSame([
            'acquire.accepted',
            'acquire.started',
            'acquire.progress',
            'acquire.progress',
            'acquire.progress',
            'acquire.completed',
        ], $events);
    }

    private function client(array $responses): CallbackClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new CallbackClient(new Client(['handler' => $stack]), self::URL, self::TOKEN);
    }
}
