<?php

namespace Reevit\Services;

use GuzzleHttp\Client;

class PaymentsService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function createIntent(array $data, ?string $idempotencyKey = null)
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }

        $response = $this->client->post('/v1/payments/intents', $options);

        return json_decode($response->getBody(), true);
    }

    public function list($limit = 50, $offset = 0)
    {
        $response = $this->client->get('/v1/payments', [
            'query' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    public function get($id)
    {
        $response = $this->client->get("/v1/payments/{$id}");
        return json_decode($response->getBody(), true);
    }

    public function refund($id, $amount = null, $reason = null)
    {
        $data = [];
        if ($amount !== null) $data['amount'] = $amount;
        if ($reason !== null) $data['reason'] = $reason;

        $response = $this->client->post("/v1/payments/{$id}/refund", [
            'json' => $data
        ]);

        return json_decode($response->getBody(), true);
    }
}
