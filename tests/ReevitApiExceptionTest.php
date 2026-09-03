<?php

declare(strict_types=1);

namespace Reevit\Tests;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reevit\Reevit;
use Reevit\ReevitApiException;

/**
 * A failing request must surface as the SDK's own error type, with the API's
 * code, message, details and request id already parsed out of the body.
 */
final class ReevitApiExceptionTest extends TestCase
{
    public function testApiErrorResponseBecomesTypedException(): void
    {
        $client = $this->clientWith(new MockHandler([
            new Response(
                402,
                ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_abc123'],
                json_encode([
                    'code' => 'payment_declined',
                    'message' => 'The card was declined by the issuer.',
                    'details' => ['issuer_code' => '51'],
                ])
            ),
        ]));

        try {
            $client->payments->get('pay_123');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame(402, $e->status);
            $this->assertSame('payment_declined', $e->errorCode);
            $this->assertSame('The card was declined by the issuer.', $e->getMessage());
            $this->assertSame(['issuer_code' => '51'], $e->details);
            $this->assertSame('req_abc123', $e->requestId);
            $this->assertFalse($e->isRecoverable());
            $this->assertStringContainsString('req_abc123', (string) $e);
        }
    }

    public function testOriginalGuzzleExceptionIsPreservedAsPrevious(): void
    {
        $client = $this->clientWith(new MockHandler([
            new Response(404, [], json_encode(['code' => 'not_found', 'message' => 'No such payment'])),
        ]));

        try {
            $client->payments->get('pay_missing');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertInstanceOf(ClientException::class, $e->getPrevious());
        }
    }

    public function testRequestIdFallsBackToTheReevitHeader(): void
    {
        $client = $this->clientWith(new MockHandler([
            new Response(500, ['X-Reevit-Request-Id' => 'req_fallback'], json_encode(['message' => 'boom'])),
        ]));

        try {
            $client->payments->get('pay_123');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('req_fallback', $e->requestId);
            $this->assertTrue($e->isRecoverable(), '5xx must be retryable');
        }
    }

    public function testNonJsonErrorBodyStillYieldsAUsableException(): void
    {
        $client = $this->clientWith(new MockHandler([
            new Response(502, ['Content-Type' => 'text/html'], '<html>bad gateway</html>'),
        ]));

        try {
            $client->payments->get('pay_123');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame(502, $e->status);
            $this->assertSame('api_error', $e->errorCode);
            $this->assertSame('Reevit request failed with status 502', $e->getMessage());
            $this->assertSame([], $e->details);
            $this->assertNull($e->requestId);
        }
    }

    public function testConnectionFailureBecomesARecoverableStatusZeroException(): void
    {
        $client = $this->clientWith(new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/v1/payments/pay_123')),
        ]));

        try {
            $client->payments->get('pay_123');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame(0, $e->status);
            $this->assertSame('connection_error', $e->errorCode);
            $this->assertTrue($e->isRecoverable());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    /**
     * @dataProvider recoverableStatusProvider
     */
    public function testRecoverableStatuses(int $status, bool $recoverable): void
    {
        $e = new ReevitApiException('x', $status);

        $this->assertSame($recoverable, $e->isRecoverable());
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public static function recoverableStatusProvider(): array
    {
        return [
            'transport failure' => [0, true],
            'bad request' => [400, false],
            'unauthorized' => [401, false],
            'not found' => [404, false],
            'timeout' => [408, true],
            'conflict' => [409, true],
            'too early' => [425, true],
            'rate limited' => [429, true],
            'server error' => [500, true],
            'bad gateway' => [502, true],
        ];
    }

    private function clientWith(MockHandler $handler): Reevit
    {
        return new Reevit('pfk_test_key', 'org_123', 'https://api.example.test', 30, HandlerStack::create($handler));
    }
}
