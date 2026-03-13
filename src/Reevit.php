<?php

declare(strict_types=1);

namespace Reevit;

use GuzzleHttp\Client;
use Reevit\Services\ConnectionsService;
use Reevit\Services\CustomersService;
use Reevit\Services\FraudService;
use Reevit\Services\InvoicesService;
use Reevit\Services\PaymentLinksService;
use Reevit\Services\PaymentsService;
use Reevit\Services\RoutingRulesService;
use Reevit\Services\SubscriptionsService;
use Reevit\Services\WebhooksService;

class Reevit
{
    private const API_BASE_URL_PRODUCTION = 'https://api.reevit.io';
    private const DEFAULT_TIMEOUT = 30;

    private Client $httpClient;
    private ?string $orgId;

    public PaymentsService $payments;
    public ConnectionsService $connections;
    public SubscriptionsService $subscriptions;
    public FraudService $fraud;
    public CustomersService $customers;
    public PaymentLinksService $paymentLinks;
    public WebhooksService $webhooks;
    public RoutingRulesService $routingRules;
    public InvoicesService $invoices;

    public function __construct(
        string $apiKey,
        ?string $orgId = null,
        ?string $baseUrl = null,
        int $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->orgId = $orgId;
        $this->httpClient = new Client([
            'base_uri' => $baseUrl ?: self::API_BASE_URL_PRODUCTION,
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => '@reevit/php',
                'X-Reevit-Key' => $apiKey,
                'X-Reevit-Client' => '@reevit/php',
                'X-Reevit-Client-Version' => '0.7.1',
            ],
        ]);

        $this->payments = new PaymentsService($this);
        $this->connections = new ConnectionsService($this);
        $this->subscriptions = new SubscriptionsService($this);
        $this->fraud = new FraudService($this);
        $this->customers = new CustomersService($this);
        $this->paymentLinks = new PaymentLinksService($this);
        $this->webhooks = new WebhooksService($this);
        $this->routingRules = new RoutingRulesService($this);
        $this->invoices = new InvoicesService($this);
    }

    public function request(string $method, string $path, array $options = []): mixed
    {
        if ($this->orgId === null && strncmp($path, '/v1/pay/', 8) !== 0) {
            @trigger_error(
                'Passing null orgId for authenticated Reevit API requests is deprecated and will be removed in a future release.',
                E_USER_DEPRECATED
            );
        }

        $headers = $options['headers'] ?? [];
        if ($this->orgId !== null && strncmp($path, '/v1/pay/', 8) !== 0) {
            $headers['X-Org-Id'] = $this->orgId;
        }
        $options['headers'] = $headers;

        $response = $this->httpClient->request($method, $path, $options);
        if ($response->getStatusCode() === 204) {
            return null;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return null;
        }

        return json_decode($body, true);
    }
}
