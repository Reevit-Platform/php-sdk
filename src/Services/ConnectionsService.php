<?php

namespace Reevit\Services;

use Reevit\Reevit;

class ConnectionsService
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
        return $this->client->request('POST', '/v1/connections', $options);
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/connections', ['query' => $query]);
        return is_array($response) && isset($response['connections']) ? $response['connections'] : ($response ?? []);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', "/v1/connections/{$id}");
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', "/v1/connections/{$id}", $options);
    }

    public function validate(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/connections/{$id}/validate", $options);
    }

    public function listAudit(string $id, array $query = []): array
    {
        $response = $this->client->request('GET', "/v1/connections/{$id}/audit", ['query' => $query]);
        return is_array($response) && isset($response['audit']) ? $response['audit'] : ($response ?? []);
    }

    public function updateLabels(string $id, array $labels, ?string $idempotencyKey = null): array
    {
        $options = ['json' => ['labels' => $labels]];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/connections/{$id}/labels", $options);
    }

    public function updateStatus(string $id, string $status, ?string $idempotencyKey = null): array
    {
        $options = ['json' => ['status' => $status]];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/connections/{$id}/status", $options);
    }

    public function test(array $data, ?string $idempotencyKey = null): bool
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $result = $this->client->request('POST', '/v1/connections/test', $options);
        return $result['success'] ?? false;
    }
}
