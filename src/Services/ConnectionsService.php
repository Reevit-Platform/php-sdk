<?php

namespace Reevit\Services;

use GuzzleHttp\Client;

class ConnectionsService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function create(array $data)
    {
        $response = $this->client->post('/v1/connections', [
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }

    public function list()
    {
        $response = $this->client->get('/v1/connections');
        return json_decode($response->getBody(), true);
    }

    public function test(array $data)
    {
        $response = $this->client->post('/v1/connections/test', [
            'json' => $data
        ]);

        $result = json_decode($response->getBody(), true);
        return $result['success'] ?? false;
    }
}
