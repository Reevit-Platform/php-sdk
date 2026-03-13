<?php

namespace Reevit\Services;

use Reevit\Reevit;

class PaymentsService
{
    private Reevit $client;

    public function __construct(Reevit $client)
    {
        $this->client = $client;
    }

    public function createIntent(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/payments/intents', $options);
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        return $this->client->request('GET', '/v1/payments', [
            'query' => [
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', "/v1/payments/{$id}");
    }

    public function updateIntent(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/payments/intents/{$id}", $options);
    }

    public function confirm(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/payments/{$id}/confirm", $options);
    }

    public function confirmIntent(string $id, string $clientSecret, ?string $idempotencyKey = null): array
    {
        $options = [
            'json' => new \stdClass(),
            'query' => ['client_secret' => $clientSecret],
        ];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/payments/{$id}/confirm-intent", $options);
    }

    public function cancel(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/payments/{$id}/cancel", $options);
    }

    public function retry(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/payments/{$id}/retry", $options);
    }

    public function refund(string $id, $amount = null, $reason = null, ?string $idempotencyKey = null): array
    {
        $data = [];
        if ($amount !== null) $data['amount'] = $amount;
        if ($reason !== null) $data['reason'] = $reason;
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/payments/{$id}/refund", $options);
    }

    public function stats(array $query = []): array
    {
        return $this->client->request('GET', '/v1/payments/stats', ['query' => $query]);
    }
}
