<?php

namespace Reevit\Services;

use Reevit\Reevit;

class FraudService
{
    private Reevit $client;

    public function __construct(Reevit $client)
    {
        $this->client = $client;
    }

    public function get(): array
    {
        return $this->client->request('GET', '/v1/policies/fraud');
    }

    public function update(array $policy, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $policy];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/policies/fraud', $options);
    }
}
