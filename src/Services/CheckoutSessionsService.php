<?php

namespace Reevit\Services;

use Reevit\Reevit;

class CheckoutSessionsService
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

        return $this->client->request('POST', '/v1/checkout/sessions', $options);
    }
}
