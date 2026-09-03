<?php

declare(strict_types=1);

namespace Reevit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface;
use Reevit\Services\CheckoutSessionsService;
use Reevit\Services\ConnectionsService;
use Reevit\Services\CustomersService;
use Reevit\Services\FraudService;
use Reevit\Services\InvoicesService;
use Reevit\Services\PaymentLinksService;
use Reevit\Services\PayoutsService;
use Reevit\Services\PaymentsService;
use Reevit\Services\RoutingRulesService;
use Reevit\Services\SubscriptionsService;
use Reevit\Services\WebhooksService;

class Reevit
{
    /**
     * The SDK version reported in the `X-Reevit-Client-Version` header.
     *
     * Keep this in step with the released Packagist version.
     */
    public const VERSION = '0.3.0';

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
    public CheckoutSessionsService $checkoutSessions;
    public WebhooksService $webhooks;
    public RoutingRulesService $routingRules;
    public InvoicesService $invoices;
    public PayoutsService $payouts;

    /**
     * @param callable|null $httpHandler Optional Guzzle handler (or HandlerStack)
     *                                   for injecting middleware or a test double.
     */
    public function __construct(
        string $apiKey,
        ?string $orgId = null,
        ?string $baseUrl = null,
        int $timeout = self::DEFAULT_TIMEOUT,
        ?callable $httpHandler = null
    ) {
        $this->orgId = $orgId;
        $config = [
            'base_uri' => self::normalizeBaseUrl($baseUrl ?: self::API_BASE_URL_PRODUCTION),
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => '@reevit/php',
                'X-Reevit-Key' => $apiKey,
                'X-Reevit-Client' => '@reevit/php',
                'X-Reevit-Client-Version' => self::VERSION,
            ],
        ];
        if ($httpHandler !== null) {
            $config['handler'] = $httpHandler;
        }
        $this->httpClient = new Client($config);

        $this->payments = new PaymentsService($this);
        $this->connections = new ConnectionsService($this);
        $this->subscriptions = new SubscriptionsService($this);
        $this->fraud = new FraudService($this);
        $this->customers = new CustomersService($this);
        $this->paymentLinks = new PaymentLinksService($this);
        $this->checkoutSessions = new CheckoutSessionsService($this);
        $this->webhooks = new WebhooksService($this);
        $this->routingRules = new RoutingRulesService($this);
        $this->invoices = new InvoicesService($this);
        $this->payouts = new PayoutsService($this);
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

        try {
            $response = $this->httpClient->request($method, self::relativePath($path), $options);
        } catch (RequestException $e) {
            throw self::apiExceptionFrom($e);
        } catch (TransferException $e) {
            // Connection refused, DNS failure, timeout: no response to read.
            throw new ReevitApiException($e->getMessage(), 0, 'connection_error', [], null, $e);
        }

        if ($response->getStatusCode() === 204) {
            return null;
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            // 200/201 with an empty body. Callers that declare `: void` or
            // `: ?array` handle this; there is nothing to decode.
            return null;
        }

        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            // A proxy answering 200 with an HTML error page used to reach the
            // service layer as null and blow up as a TypeError from inside the
            // SDK. Surface it as the SDK's own error instead.
            throw new ReevitApiException(
                sprintf('Reevit returned a non-JSON body: %s', json_last_error_msg()),
                $response->getStatusCode(),
                'invalid_response'
            );
        }

        return $decoded;
    }

    /**
     * Guzzle resolves `base_uri` against a request path per RFC 3986: a path
     * with a leading `/` REPLACES the base path, so a base URL of
     * `https://gateway.internal/reevit` would send `/v1/payments` rather than
     * `/reevit/v1/payments` and every request to a path-mounted reverse proxy
     * or staging environment would 404. Keeping a trailing slash on the base
     * and a relative request path preserves the prefix.
     */
    private static function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/';
    }

    /**
     * Strip the leading slash so the path resolves relative to the (always
     * trailing-slashed) base URL rather than replacing its path.
     */
    private static function relativePath(string $path): string
    {
        return ltrim($path, '/');
    }

    /**
     * Translate a Guzzle transport failure into the SDK's typed error, pulling
     * `code`, `message`, `details` and the request id off the API's response
     * body so callers never have to re-parse it themselves.
     */
    private static function apiExceptionFrom(RequestException $e): ReevitApiException
    {
        $response = $e->getResponse();
        if (!$response instanceof ResponseInterface) {
            return new ReevitApiException($e->getMessage(), 0, 'connection_error', [], null, $e);
        }

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        $body = is_array($decoded) ? $decoded : [];

        $message = self::firstNonEmptyString($body, ['message', 'error'])
            ?? sprintf('Reevit request failed with status %d', $status);
        $code = self::firstNonEmptyString($body, ['code']) ?? 'api_error';
        $details = isset($body['details']) && is_array($body['details']) ? $body['details'] : [];

        $requestId = $response->getHeaderLine('X-Request-Id');
        if ($requestId === '') {
            $requestId = $response->getHeaderLine('X-Reevit-Request-Id');
        }

        return new ReevitApiException(
            $message,
            $status,
            $code,
            $details,
            $requestId === '' ? null : $requestId,
            $e
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $keys
     */
    private static function firstNonEmptyString(array $body, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $body[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
