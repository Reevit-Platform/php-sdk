<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;

class RoutingRulesService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(): array
    {
        $response = $this->client->request('GET', '/v1/routing-rules');
        return ListEnvelope::extractArray($response, 'rules');
    }

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/routing-rules', $options);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/routing-rules/' . rawurlencode($id));
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/routing-rules/' . rawurlencode($id), $options);
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', '/v1/routing-rules/' . rawurlencode($id), $options);
    }
}
