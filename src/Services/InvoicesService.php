<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Reevit;

class InvoicesService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/invoices', ['query' => $query]);
        return is_array($response) && isset($response['invoices']) ? $response['invoices'] : ($response ?? []);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', "/v1/invoices/{$id}");
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/invoices/{$id}", $options);
    }

    public function cancel(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/invoices/{$id}/cancel", $options);
    }

    public function retry(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/invoices/{$id}/retry", $options);
    }
}
