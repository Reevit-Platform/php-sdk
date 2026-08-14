<?php

declare(strict_types=1);

namespace Reevit\Services;

use InvalidArgumentException;
use Reevit\Reevit;

class PayoutsService
{
    public function __construct(private Reevit $client)
    {
    }

    public function create(array $data, string $idempotencyKey): array
    {
        return $this->client->request('POST', '/v1/payouts', [
            'json' => $data,
            'headers' => ['Idempotency-Key' => $this->requireKey($idempotencyKey)],
        ]);
    }

    public function list(array $query = []): array
    {
        return $this->client->request('GET', '/v1/payouts', ['query' => $query]);
    }

    public function get(string $id): array
    {
        return $this->client->request('GET', "/v1/payouts/{$id}");
    }

    public function confirm(string $id): array
    {
        return $this->client->request('POST', "/v1/payouts/{$id}/confirm", ['json' => new \stdClass()]);
    }

    public function cancel(string $id): array
    {
        return $this->client->request('POST', "/v1/payouts/{$id}/cancel", ['json' => new \stdClass()]);
    }

    public function createBulk(array $data, string $idempotencyKey): array
    {
        return $this->client->request('POST', '/v1/payouts/bulk', [
            'json' => $data,
            'headers' => ['Idempotency-Key' => $this->requireKey($idempotencyKey)],
        ]);
    }

    public function balance(string $connectionId): array
    {
        $response = $this->client->request('GET', '/v1/payouts/balance', [
            'query' => ['connection_id' => $connectionId],
        ]);
        return $response['balances'] ?? [];
    }

    public function resolveAccount(string $connectionId, array $beneficiary): array
    {
        return $this->client->request('POST', '/v1/payouts/resolve-account', [
            'json' => ['connection_id' => $connectionId, 'beneficiary' => $beneficiary],
        ]);
    }

    public function createBeneficiary(array $beneficiary): array
    {
        return $this->client->request('POST', '/v1/beneficiaries', [
            'json' => ['beneficiary' => $beneficiary],
        ]);
    }

    public function listBeneficiaries(array $query = []): array
    {
        return $this->client->request('GET', '/v1/beneficiaries', ['query' => $query]);
    }

    public function getBeneficiary(string $id): array
    {
        return $this->client->request('GET', "/v1/beneficiaries/{$id}");
    }

    public function deleteBeneficiary(string $id): void
    {
        $this->client->request('DELETE', "/v1/beneficiaries/{$id}");
    }

    private function requireKey(string $idempotencyKey): string
    {
        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException('idempotency key is required for payout creation');
        }
        return $idempotencyKey;
    }
}
