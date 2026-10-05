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

/** Verify the JSON bytes Guzzle prepares, rather than mocking Reevit::request. */
final class RequestBodyTest extends TestCase
{
    public function testFullRefundWithoutAmountOrReasonSendsAnObjectOnEveryAttempt(): void
    {
        $requests = [];
        $client = $this->clientWith($requests);
        $key = 'refund:order_123:full';

        $client->payments->refund('pay_123', null, null, $key);
        $client->payments->refund('pay_123', null, null, $key);

        $this->assertCount(2, $requests);
        foreach ($requests as $transaction) {
            $request = $transaction['request'];
            $this->assertSame('/v1/payments/pay_123/refund', $request->getUri()->getPath());
            $this->assertSame('{}', (string) $request->getBody());
            $this->assertSame($key, $request->getHeaderLine('Idempotency-Key'));
        }
    }

    public function testPartialRefundPreservesItsScalarAmountAndReason(): void
    {
        $requests = [];
        $client = $this->clientWith($requests);

        $client->payments->refund('pay_123', 2500, 'Customer requested', 'refund:order_123:partial_1');

        $request = $requests[0]['request'];
        $this->assertSame(['amount' => 2500, 'reason' => 'Customer requested'], $this->jsonBody($request));
        $this->assertSame('refund:order_123:partial_1', $request->getHeaderLine('Idempotency-Key'));
    }

    public function testReasonOnlyRefundKeepsTheReasonAndOmitsTheAmount(): void
    {
        $requests = [];
        $client = $this->clientWith($requests);

        $client->payments->refund('pay_123', null, 'Customer requested', 'refund:order_123:reason');

        $this->assertSame(['reason' => 'Customer requested'], $this->jsonBody($requests[0]['request']));
        $this->assertSame('refund:order_123:reason', $requests[0]['request']->getHeaderLine('Idempotency-Key'));
    }

    /** @dataProvider emptyMutationProvider */
    public function testEmptyMutationPayloadsAreObjects(callable $call, string $method, string $path): void
    {
        $requests = [];
        $client = $this->clientWith($requests);
        $key = 'operation:revision_2';

        $call($client, $key);

        $request = $requests[0]['request'];
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());
        $this->assertSame('{}', (string) $request->getBody());
        $this->assertSame($key, $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('org_123', $request->getHeaderLine('X-Org-Id'));
    }

    public static function emptyMutationProvider(): array
    {
        return [
            'clear fraud policy' => [static fn (Reevit $c, string $key) => $c->fraud->update([], $key), 'POST', '/v1/policies/fraud'],
            'customer update' => [static fn (Reevit $c, string $key) => $c->customers->update('cus_123', [], $key), 'PATCH', '/v1/customers/cus_123'],
            'subscription update' => [static fn (Reevit $c, string $key) => $c->subscriptions->update('sub_123', [], $key), 'PATCH', '/v1/subscriptions/sub_123'],
            'payment link update' => [static fn (Reevit $c, string $key) => $c->paymentLinks->update('link_123', [], $key), 'PATCH', '/v1/payment-links/link_123'],
            'routing rule update' => [static fn (Reevit $c, string $key) => $c->routingRules->update('rule_123', [], $key), 'PATCH', '/v1/routing-rules/rule_123'],
            'invoice update' => [static fn (Reevit $c, string $key) => $c->invoices->update('inv_123', [], $key), 'PATCH', '/v1/invoices/inv_123'],
            'intent update' => [static fn (Reevit $c, string $key) => $c->payments->updateIntent('pay_123', [], $key), 'PATCH', '/v1/payments/intents/pay_123'],
            'existing explicit object' => [static fn (Reevit $c, string $key) => $c->connections->validate('conn_123', $key), 'POST', '/v1/connections/conn_123/validate'],
        ];
    }

    public function testPopulatedObjectsKeepNestedArraysAndObjects(): void
    {
        $requests = [];
        $client = $this->clientWith($requests);
        $payload = [
            'amount' => 5000,
            'currency' => 'GHS',
            'country' => 'GH',
            'metadata' => ['items' => [], 'codes' => ['a', 'b'], 'buyer' => ['name' => 'Ada']],
        ];

        $client->payments->createIntent($payload, 'order:123');

        $this->assertSame($payload, $this->jsonBody($requests[0]['request']));
        $this->assertSame('order:123', $requests[0]['request']->getHeaderLine('Idempotency-Key'));
        $this->assertStringContainsString('"items":[]', (string) $requests[0]['request']->getBody());
        $this->assertStringContainsString('"buyer":{"name":"Ada"}', (string) $requests[0]['request']->getBody());
    }

    public function testPopulatedTopLevelJsonListsRemainLists(): void
    {
        $requests = [];
        $client = $this->clientWith($requests);
        $items = [['amount' => 100], ['amount' => 200]];

        $client->request('POST', '/v1/fixture', ['json' => $items]);

        $this->assertSame('[' . '{"amount":100},{"amount":200}' . ']', (string) $requests[0]['request']->getBody());
        $this->assertFalse($requests[0]['request']->hasHeader('Idempotency-Key'));
    }

    public function testGetListHasNoJsonBodyAndPreservesQueryAndResponse(): void
    {
        $requests = [];
        $rows = [['id' => 'pay_123']];
        $client = $this->clientWith($requests, ['payments' => $rows]);

        $this->assertSame($rows, $client->payments->list(10, 20));

        $request = $requests[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('limit=10&offset=20', $request->getUri()->getQuery());
        $this->assertSame('', (string) $request->getBody());
        $this->assertFalse($request->hasHeader('Idempotency-Key'));
    }

    private function jsonBody(RequestInterface $request): array
    {
        return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function clientWith(array &$requests, array $response = ['id' => 'fixture']): Reevit
    {
        $mock = new MockHandler(array_fill(0, 2, new Response(200, ['Content-Type' => 'application/json'], json_encode($response))));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($requests));

        return new Reevit('pfk_test_fixture.secret', 'org_123', 'https://api.example.test', 30, $stack);
    }
}
