<?php

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;

class SubscriptionsService
{
    private Reevit $client;

    public function __construct(Reevit $client)
    {
        $this->client = $client;
    }

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/subscriptions', $options);
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/subscriptions', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'subscriptions');
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/subscriptions/' . rawurlencode($id));
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/subscriptions/' . rawurlencode($id), $options);
    }

    public function cancel(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/subscriptions/' . rawurlencode($id) . '/cancel', $options);
    }

    public function resume(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/subscriptions/' . rawurlencode($id) . '/resume', $options);
    }
}
