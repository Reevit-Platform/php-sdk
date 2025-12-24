<?php

namespace Reevit\Services;

use GuzzleHttp\Client;

class FraudService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function get()
    {
        $response = $this->client->get('/v1/policies/fraud');
        return json_decode($response->getBody(), true);
    }

    public function update(array $policy)
    {
        $response = $this->client->post('/v1/policies/fraud', [
            'json' => $policy
        ]);

        return json_decode($response->getBody(), true);
    }
}
