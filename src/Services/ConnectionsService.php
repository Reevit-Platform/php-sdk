<?php

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;
use Reevit\ReevitApiException;

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
        return $this->listPage($query)['connections'];
    }

    public function listPage(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/connections', ['query' => $query]);
        $connections = ListEnvelope::tryExtractArray($response, 'connections');
        if ($connections === null) {
            throw ListEnvelope::unexpectedShape('connections');
        }

        $pagination = isset($response['pagination']) && is_array($response['pagination'])
            ? $response['pagination']
            : [];

        return [
            'connections' => $connections,
            'pagination' => [
                'total' => $pagination['total'] ?? count($connections),
                'limit' => $pagination['limit'] ?? ($query['limit'] ?? count($connections)),
                'offset' => $pagination['offset'] ?? ($query['offset'] ?? 0),
            ],
        ];
    }

    public function listAll(array $filters = []): array
    {
        unset($filters['limit'], $filters['offset']);
        $connections = [];
        $offset = 0;

        while (true) {
            $page = $this->listPage(array_merge($filters, ['limit' => 200, 'offset' => $offset]));
            $batch = $page['connections'];
            $connections = array_merge($connections, $batch);
            $nextOffset = $offset + count($batch);
            if (count($batch) === 0 || $nextOffset >= (int) $page['pagination']['total']) {
                return $connections;
            }
            $offset = $nextOffset;
        }
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/connections/' . rawurlencode($id));
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', '/v1/connections/' . rawurlencode($id), $options);
    }

    public function validate(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/connections/' . rawurlencode($id) . '/validate', $options);
    }

    public function listAudit(string $id, array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/connections/' . rawurlencode($id) . '/audit', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'audit');
    }

    public function listLabels(): array
    {
        $response = $this->client->request('GET', '/v1/connections/labels');
        if (!is_array($response) || !$this->isList($response)) {
            throw new ReevitApiException(
                'unexpected response shape: connection labels must be a JSON array',
                0,
                'unexpected_response_shape'
            );
        }
        return $response;
    }

    public function updateLabels(string $id, array $labels, ?string $idempotencyKey = null): array
    {
        $options = ['json' => ['labels' => $labels]];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/connections/' . rawurlencode($id) . '/labels', $options);
    }

    public function updateStatus(string $id, string $status, ?string $idempotencyKey = null): array
    {
        $options = ['json' => ['status' => $status]];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/connections/' . rawurlencode($id) . '/status', $options);
    }

    public function test(array $data, ?string $idempotencyKey = null): bool
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $result = $this->client->request('POST', '/v1/connections/test', $options);
        return $result['ok'] ?? ($result['success'] ?? false);
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}
