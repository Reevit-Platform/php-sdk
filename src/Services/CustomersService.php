<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;

class CustomersService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/customers', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'customers');
    }

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/customers', $options);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/customers/' . rawurlencode($id));
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/customers/' . rawurlencode($id), $options);
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', '/v1/customers/' . rawurlencode($id), $options);
    }

    public function lookup(string $externalId): array
    {
        return $this->client->request('GET', '/v1/customers/lookup', ['query' => ['external_id' => $externalId]]);
    }

    public function top(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/customers/top', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'customers');
    }

    public function paymentHistory(string $id, array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/customers/' . rawurlencode($id) . '/payments', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'payments');
    }
}
