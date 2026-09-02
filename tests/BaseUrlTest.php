<?php

declare(strict_types=1);

namespace Reevit\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Reevit\Reevit;

/**
 * Guzzle resolves `base_uri` against the request path per RFC 3986, so an
 * absolute path (`/v1/payments`) REPLACES the base path. A merchant pointing
 * the SDK at a reverse proxy or a path-mounted staging environment used to get
 * 404s that looked like API bugs.
 */
final class BaseUrlTest extends TestCase
{
    public function testPathPrefixOnTheBaseUrlSurvivesIntoTheRequest(): void
    {
        $requests = [];
        $client = $this->clientWith('https://gateway.internal/reevit', $requests);

        $client->payments->get('pay_123');

        $this->assertCount(1, $requests);
        $uri = $requests[0]['request']->getUri();
        $this->assertSame('gateway.internal', $uri->getHost());
        $this->assertSame('/reevit/v1/payments/pay_123', $uri->getPath());
        $this->assertSame(
            'https://gateway.internal/reevit/v1/payments/pay_123',
            (string) $uri
        );
    }

    public function testTrailingSlashOnTheBaseUrlIsNotDoubled(): void
    {
        $requests = [];
        $client = $this->clientWith('https://gateway.internal/reevit/', $requests);

        $client->payments->get('pay_123');

        $this->assertSame('/reevit/v1/payments/pay_123', $requests[0]['request']->getUri()->getPath());
    }

    public function testMultiSegmentPrefixIsPreserved(): void
    {
        $requests = [];
        $client = $this->clientWith('https://gateway.internal/api/reevit/v2-proxy', $requests);

        $client->customers->list();

        $this->assertSame(
            '/api/reevit/v2-proxy/v1/customers',
            $requests[0]['request']->getUri()->getPath()
        );
    }

    public function testDefaultBaseUrlStillHitsTheApiRoot(): void
    {
        $requests = [];
        $client = $this->clientWith(null, $requests);

        $client->payments->get('pay_123');

        $uri = $requests[0]['request']->getUri();
        $this->assertSame('api.reevit.io', $uri->getHost());
        $this->assertSame('/v1/payments/pay_123', $uri->getPath());
    }

    public function testQueryStringIsPreservedAlongsideThePrefix(): void
    {
        $requests = [];
        $client = $this->clientWith('https://gateway.internal/reevit', $requests);

        $client->payments->list(10, 20);

        /** @var RequestInterface $request */
        $request = $requests[0]['request'];
        $this->assertSame('/reevit/v1/payments', $request->getUri()->getPath());
        $this->assertSame('limit=10&offset=20', $request->getUri()->getQuery());
    }

    /**
     * @param array<int, array{request: RequestInterface}> $requests
     */
    private function clientWith(?string $baseUrl, array &$requests): Reevit
    {
        $mock = new MockHandler(array_fill(0, 4, new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['id' => 'pay_123'])
        )));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($requests));

        return new Reevit('pfk_test_key', 'org_123', $baseUrl, 30, $stack);
    }
}
