<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Reevit;

class CustomersService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/customers', ['query' => $query]);
        return is_array($response) && isset($response['customers']) ? $response['customers'] : ($response ?? []);
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
        return $this->client->request('GET', "/v1/customers/{$id}");
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/customers/{$id}", $options);
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', "/v1/customers/{$id}", $options);
    }

    public function lookup(string $externalId): array
    {
        return $this->client->request('GET', '/v1/customers/lookup', ['query' => ['external_id' => $externalId]]);
    }

    public function top(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/customers/top', ['query' => $query]);
        return is_array($response) && isset($response['customers']) ? $response['customers'] : ($response ?? []);
    }

    public function paymentHistory(string $id, array $query = []): array
    {
        $response = $this->client->request('GET', "/v1/customers/{$id}/payments", ['query' => $query]);
        return is_array($response) && isset($response['payments']) ? $response['payments'] : ($response ?? []);
    }
}
