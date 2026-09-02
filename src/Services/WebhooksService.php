<?php

declare(strict_types=1);

namespace Reevit\Services;

use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;

class WebhooksService
{
    public function __construct(private Reevit $client)
    {
    }

    public function getConfig(): array
    {
        return $this->client->request('GET', '/v1/webhooks/config');
    }

    public function upsertConfig(array $data, ?string $idempotencyKey = null): array
    {
        $options = ['json' => $data];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/webhooks/config', $options);
    }

    /**
     * @return array<string, mixed>|null The API's acknowledgement body, or null
     *                                   when the endpoint answers 204 No Content.
     */
    public function deleteConfig(?string $idempotencyKey = null): ?array
    {
        $options = [];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        $response = $this->client->request('DELETE', '/v1/webhooks/config', $options);

        return is_array($response) ? $response : null;
    }

    public function sendTest(?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', '/v1/webhooks/test', $options);
    }

    public function listEvents(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/webhooks/events', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'events');
    }

    public function getEvent(string $id): array
    {
        return $this->client->request('GET', "/v1/webhooks/events/{$id}");
    }

    public function replayEvent(string $id, ?string $idempotencyKey = null): array
    {
        $options = ['json' => new \stdClass()];
        if ($idempotencyKey) {
            $options['headers'] = ['Idempotency-Key' => $idempotencyKey];
        }
        return $this->client->request('POST', "/v1/webhooks/events/{$id}/replay", $options);
    }

    public function listOutbound(array $query = []): array
    {
        $response = $this->client->request('GET', '/v1/webhooks/outbound', ['query' => $query]);
        return ListEnvelope::extractArray($response, 'outbound');
    }

    public function getOutbound(string $id): array
    {
        return $this->client->request('GET', "/v1/webhooks/outbound/{$id}");
    }
}
