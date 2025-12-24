<?php

namespace Reevit;

use GuzzleHttp\Client;
use Reevit\Services\PaymentsService;
use Reevit\Services\ConnectionsService;
use Reevit\Services\SubscriptionsService;
use Reevit\Services\FraudService;

const API_BASE_URL_PRODUCTION = 'https://api.reevit.io';
const API_BASE_URL_SANDBOX = 'https://sandbox-api.reevit.io';
const DEFAULT_TIMEOUT = 30;

private static function isSandboxKey($apiKey) {
    return strpos($apiKey, 'pk_test_') === 0 || strpos($apiKey, 'pk_sandbox_') === 0;
}

class Reevit
{
    private $client;

    public $payments;
    public $connections;
    public $subscriptions;
    public $fraud;

    public function __construct($apiKey, $baseUrl = null)
    {
        if ($baseUrl === null) {
            $baseUrl = self::isSandboxKey($apiKey) ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL_PRODUCTION;
        }
        $this->client = new Client([
            'base_uri' => $baseUrl,
            'timeout'  => self::DEFAULT_TIMEOUT,
            'headers'  => [
                'Content-Type'    => 'application/json',
                'User-Agent'      => '@reevit/php',
                'Authorization' => 'Bearer ' . $apiKey,
                'X-Reevit-Client' => '@reevit/php',
                'X-Reevit-Client-Version' => '0.1.0',
            ],
        ]);

        $this->payments = new PaymentsService($this->client);
        $this->connections = new ConnectionsService($this->client);
        $this->subscriptions = new SubscriptionsService($this->client);
        $this->fraud = new FraudService($this->client);
    }
}
