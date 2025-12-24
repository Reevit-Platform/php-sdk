<?php

namespace Reevit\Services;

use GuzzleHttp\Client;

class SubscriptionsService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function create(array $data)
    {
        $response = $this->client->post('/v1/subscriptions', [
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }

    public function list()
    {
        $response = $this->client->get('/v1/subscriptions');
        return json_decode($response->getBody(), true);
    }
}
