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
 * An unescaped id interpolated into a path lets `/`, `?` and `#` restructure
 * the request: `GET /v1/payments/../../admin` or a `?` that turns the tail of
 * the id into a query string. Only ConnectionsService escaped before; the
 * other services interpolated raw.
 */
final class PathEscapingTest extends TestCase
{
    /**
     * @dataProvider hostileIdProvider
     */
    public function testHostileIdsAreEscapedIntoASinglePathSegment(callable $call, string $expectedPath): void
    {
        $requests = [];
        $client = $this->client($requests);

        $call($client);

        /** @var RequestInterface $request */
        $request = $requests[0]['request'];
        $this->assertSame($expectedPath, $request->getUri()->getPath());
        $this->assertSame('', $request->getUri()->getQuery(), 'the id must not leak into the query string');
        $this->assertSame('', $request->getUri()->getFragment());
    }

    /**
     * @return array<string, array{callable, string}>
     */
    public static function hostileIdProvider(): array
    {
        return [
            'payments->get with a slash' => [
                static fn (Reevit $c) => $c->payments->get('pay/../admin'),
                '/v1/payments/pay%2F..%2Fadmin',
            ],
            'payments->confirm with a question mark' => [
                static fn (Reevit $c) => $c->payments->confirm('pay_1?admin=1'),
                '/v1/payments/pay_1%3Fadmin%3D1/confirm',
            ],
            'payments->updateIntent with a hash' => [
                static fn (Reevit $c) => $c->payments->updateIntent('pi_1#frag', []),
                '/v1/payments/intents/pi_1%23frag',
            ],
            'payouts->get' => [
                static fn (Reevit $c) => $c->payouts->get('po_1/../x'),
                '/v1/payouts/po_1%2F..%2Fx',
            ],
            'payouts->confirm' => [
                static fn (Reevit $c) => $c->payouts->confirm('po_1?x=1'),
                '/v1/payouts/po_1%3Fx%3D1/confirm',
            ],
            'payouts->getBeneficiary' => [
                static fn (Reevit $c) => $c->payouts->getBeneficiary('ben 1'),
                '/v1/beneficiaries/ben%201',
            ],
            'customers->get' => [
                static fn (Reevit $c) => $c->customers->get('cus/1'),
                '/v1/customers/cus%2F1',
            ],
            'invoices->cancel' => [
                static fn (Reevit $c) => $c->invoices->cancel('inv/1'),
                '/v1/invoices/inv%2F1/cancel',
            ],
            'paymentLinks->getStats' => [
                static fn (Reevit $c) => $c->paymentLinks->getStats('plink/1'),
                '/v1/payment-links/plink%2F1/stats',
            ],
            'paymentLinks->getByCode' => [
                static fn (Reevit $c) => $c->paymentLinks->getByCode('code/1'),
                '/v1/pay/code%2F1',
            ],
            'routingRules->get' => [
                static fn (Reevit $c) => $c->routingRules->get('rule/1'),
                '/v1/routing-rules/rule%2F1',
            ],
            'subscriptions->resume' => [
                static fn (Reevit $c) => $c->subscriptions->resume('sub/1'),
                '/v1/subscriptions/sub%2F1/resume',
            ],
            'webhooks->replayEvent' => [
                static fn (Reevit $c) => $c->webhooks->replayEvent('evt/1'),
                '/v1/webhooks/events/evt%2F1/replay',
            ],
            'connections->validate' => [
                static fn (Reevit $c) => $c->connections->validate('conn/1'),
                '/v1/connections/conn%2F1/validate',
            ],
        ];
    }

    public function testAnOrdinaryIdIsUnchanged(): void
    {
        $requests = [];
        $client = $this->client($requests);

        $client->payments->get('pay_01HZX9K3');

        $this->assertSame('/v1/payments/pay_01HZX9K3', $requests[0]['request']->getUri()->getPath());
    }

    /**
     * @param array<int, array{request: RequestInterface}> $requests
     */
    private function client(array &$requests): Reevit
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 'x'])),
        ]));
        $stack->push(Middleware::history($requests));

        return new Reevit('pfk_test_key', 'org_123', null, 30, $stack);
    }
}
