<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;

class InvoicesService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/invoices', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'invoices');
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/invoices/' . rawurlencode($id));
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', '/v1/invoices/' . rawurlencode($id), $options);
    }

    public function cancel(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/invoices/' . rawurlencode($id) . '/cancel', $options);
    }

    public function retry(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/invoices/' . rawurlencode($id) . '/retry', $options);
    }
}
