<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Reevit;

class PaymentLinksService
{
    public function __construct(private Reevit $client)
    {
    }

    public function list(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/payment-links', ['query' => $query]);
        return is_array($response) && isset($response['payment_links']) ? $response['payment_links'] : ($response ?? []);
    }

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/payment-links', $options);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', "/v1/payment-links/{$id}");
    }

    public function update(string $id, array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('PATCH', "/v1/payment-links/{$id}", $options);
    }

    public function delete(string $id, ?string $idempotencyKey = null): void
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $this->client->request('DELETE', "/v1/payment-links/{$id}", $options);
    }

    public function getStats(string $id): array
    {
        return $this->client->request('GET', "/v1/payment-links/{$id}/stats");
    }

    public function listPayments(string $id, array $query = []): array
    {
        $response = $this->client->request('GET', "/v1/payment-links/{$id}/payments", ['query' => $query]);
        return is_array($response) && isset($response['payments']) ? $response['payments'] : ($response ?? []);
    }

    public function getByCode(string $code): array
    {
        return $this->client->request('GET', "/v1/pay/{$code}");
    }
}
