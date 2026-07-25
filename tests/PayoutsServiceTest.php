<?php

declare(strict_types=1);

namespace Reevit\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Reevit\Reevit;
use Reevit\Services\PayoutsService;

final class PayoutsServiceTest extends TestCase
{
    public function testCreateSendsIdempotencyKeyAndPayload(): void
    {
        $payload = [
            'connection_id' => 'conn_123',
            'amount' => 2500,
            'currency' => 'GHS',
            'beneficiary_id' => 'ben_123',
        ];
        $client = $this->getMockBuilder(Reevit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $client->expects($this->once())
            ->method('request')
            ->with('POST', '/v1/payouts', [
                'json' => $payload,
                'headers' => ['Idempotency-Key' => 'order-123'],
            ])
            ->willReturn(['id' => 'po_123']);

        $service = new PayoutsService($client);

        $this->assertSame(['id' => 'po_123'], $service->create($payload, 'order-123'));
    }

    public function testCreateRejectsBlankIdempotencyKey(): void
    {
        $client = $this->getMockBuilder(Reevit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $client->expects($this->never())->method('request');
        $service = new PayoutsService($client);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('idempotency key is required');
        $service->create([], '  ');
    }
}
