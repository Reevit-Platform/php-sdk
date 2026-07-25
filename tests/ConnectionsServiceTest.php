<?php

declare(strict_types=1);

namespace Reevit\Tests;

use PHPUnit\Framework\TestCase;
use Reevit\Reevit;
use Reevit\Services\ConnectionsService;

final class ConnectionsServiceTest extends TestCase
{
    public function testCredentialTestAcceptsBackendOkResponse(): void
    {
        $client = $this->getMockBuilder(Reevit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $client->expects($this->once())
            ->method('request')
            ->with('POST', '/v1/connections/test', [
                'json' => ['provider' => 'paystack'],
            ])
            ->willReturn(['ok' => true]);

        $service = new ConnectionsService($client);

        $this->assertTrue($service->test(['provider' => 'paystack']));
    }

    public function testListAllFollowsPaginationAndPreservesFilters(): void
    {
        $client = $this->getMockBuilder(Reevit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $client->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['GET', '/v1/connections', ['query' => [
                    'provider' => 'paystack',
                    'mode' => 'live',
                    'status' => 'active',
                    'label' => 'primary',
                    'limit' => 200,
                    'offset' => 0,
                ]]],
                ['GET', '/v1/connections', ['query' => [
                    'provider' => 'paystack',
                    'mode' => 'live',
                    'status' => 'active',
                    'label' => 'primary',
                    'limit' => 200,
                    'offset' => 2,
                ]]]
            )
            ->willReturnOnConsecutiveCalls(
                [
                    'connections' => [['id' => 'conn_1'], ['id' => 'conn_2']],
                    'pagination' => ['total' => 3, 'limit' => 200, 'offset' => 0],
                ],
                [
                    'connections' => [['id' => 'conn_3']],
                    'pagination' => ['total' => 3, 'limit' => 200, 'offset' => 2],
                ]
            );
        $service = new ConnectionsService($client);

        $connections = $service->listAll([
            'provider' => 'paystack',
            'mode' => 'live',
            'status' => 'active',
            'label' => 'primary',
        ]);

        $this->assertSame(['conn_1', 'conn_2', 'conn_3'], array_column($connections, 'id'));
    }

    public function testListLabelsUsesConnectionLabelsEndpoint(): void
    {
        $client = $this->getMockBuilder(Reevit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['request'])
            ->getMock();
        $client->expects($this->once())
            ->method('request')
            ->with('GET', '/v1/connections/labels')
            ->willReturn([['label' => 'primary', 'total' => 2]]);

        $service = new ConnectionsService($client);

        $this->assertSame(
            [['label' => 'primary', 'total' => 2]],
            $service->listLabels()
        );
    }
}
