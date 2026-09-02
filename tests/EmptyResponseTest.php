<?php

declare(strict_types=1);

namespace Reevit\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Reevit\Reevit;
use Reevit\ReevitApiException;

/**
 * `Reevit::request()` returns null for HTTP 204 and for an empty body, so every
 * method that can receive one must declare a return type able to hold it.
 * A `: array` declaration on such a method is a TypeError raised from inside
 * the SDK, on the merchant's stack, for a perfectly valid API response.
 */
final class EmptyResponseTest extends TestCase
{
    /**
     * @dataProvider deleteProvider
     */
    public function testA204DeleteDoesNotThrow(callable $call): void
    {
        $client = $this->clientReturning(new Response(204, [], ''));

        $call($client);

        $this->assertTrue(true, 'a 204 delete must complete without throwing');
    }

    /**
     * @return array<string, array{callable}>
     */
    public static function deleteProvider(): array
    {
        return [
            'customers->delete' => [static fn (Reevit $c) => $c->customers->delete('cus_1')],
            'connections->delete' => [static fn (Reevit $c) => $c->connections->delete('conn_1')],
            'paymentLinks->delete' => [static fn (Reevit $c) => $c->paymentLinks->delete('plink_1')],
            'routingRules->delete' => [static fn (Reevit $c) => $c->routingRules->delete('rule_1')],
            'webhooks->deleteConfig' => [static fn (Reevit $c) => $c->webhooks->deleteConfig()],
            'payouts->deleteBeneficiary' => [static fn (Reevit $c) => $c->payouts->deleteBeneficiary('ben_1')],
        ];
    }

    public function testA204DeleteConfigReturnsNullRatherThanRaising(): void
    {
        $client = $this->clientReturning(new Response(204, [], ''));

        $this->assertNull($client->webhooks->deleteConfig());
    }

    public function testDeleteConfigForwardsTheApiAcknowledgementWhenThereIsOne(): void
    {
        $client = $this->clientReturning(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['status' => 'deleted'])
        ));

        $this->assertSame(['status' => 'deleted'], $client->webhooks->deleteConfig());
    }

    public function testDeleteBeneficiaryForwardsTheApiAcknowledgementWhenThereIsOne(): void
    {
        $client = $this->clientReturning(new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['status' => 'deleted'])
        ));

        $this->assertSame(['status' => 'deleted'], $client->payouts->deleteBeneficiary('ben_1'));
    }

    public function testAnEmptyBodyOn200IsTreatedAsNoContent(): void
    {
        $client = $this->clientReturning(new Response(200, [], ''));

        $this->assertNull($client->payouts->deleteBeneficiary('ben_1'));
    }

    public function testANonJsonBodyRaisesTheSdkErrorRatherThanATypeError(): void
    {
        $client = $this->clientReturning(new Response(200, ['Content-Type' => 'text/html'], '<html>proxy</html>'));

        $this->expectException(ReevitApiException::class);
        $this->expectExceptionMessage('non-JSON body');
        $client->payments->get('pay_1');
    }

    private function clientReturning(Response $response): Reevit
    {
        $handler = HandlerStack::create(new MockHandler([$response]));

        return new Reevit('pfk_test_key', 'org_123', 'https://api.example.test', 30, $handler);
    }
}
