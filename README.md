# Reevit PHP SDK

The official PHP SDK for [Reevit](https://reevit.io) — a unified payment orchestration platform for Africa.

[![Packagist Version](https://img.shields.io/packagist/v/reevit/reevit-php.svg)](https://packagist.org/packages/reevit/reevit-php)
[![PHP Version](https://img.shields.io/packagist/php-v/reevit/reevit-php.svg)](https://packagist.org/packages/reevit/reevit-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
composer require reevit/reevit-php
```

## Quick Start

```php
<?php

require 'vendor/autoload.php';

use Reevit\Reevit;

$client = new Reevit('pfk_live_xxx', 'org_123');

// Create a payment
$payment = $client->payments->createIntent([
    'amount' => 5000, // 50.00 GHS
    'currency' => 'GHS',
    'method' => 'momo',
    'country' => 'GH',
    'customer_id' => 'cust_123',
    'metadata' => [
        'order_id' => '12345'
    ]
], 'order_12345');

echo "Payment created: " . $payment['id'] . "\n";

// List payments
$payments = $client->payments->list();
print_r($payments);
```

## Server-created checkout sessions

Create checkout sessions on your server, then pass `$session['session_secret']` to a browser SDK — [`@reevit/react`](https://www.npmjs.com/package/@reevit/react), [`@reevit/vue`](https://www.npmjs.com/package/@reevit/vue), or [`@reevit/svelte`](https://www.npmjs.com/package/@reevit/svelte) — to render the checkout UI.

```php
$session = $client->checkoutSessions->create([
    'amount' => 5000,
    'currency' => 'GHS',
    'method' => 'mobile_money',
    'country' => 'GH',
], 'order_12345');
```

## Idempotency

Pass an idempotency key as the second argument to prevent duplicate intent creation.

```php
$intent = $client->payments->createIntent(
    [
        'amount' => 5000,
        'currency' => 'GHS',
        'method' => 'momo',
        'country' => 'GH',
    ],
    'order_12345'
);
```

## Error handling

Every failed request raises `Reevit\ReevitApiException`. The API's error code,
message, structured details and request id are already parsed off the response
body — no need to re-read `$e->getResponse()->getBody()` yourself.

```php
use Reevit\ReevitApiException;

try {
    $payment = $client->payments->confirm('pay_123');
} catch (ReevitApiException $e) {
    if ($e->errorCode === 'payment_declined') {
        // $e->details carries the issuer's structured reason, when the API sends one.
    }

    if ($e->isRecoverable()) {
        // 0 (transport failure), 408, 409, 425, 429 and every 5xx.
    }

    error_log(sprintf('reevit %s: %s', $e->requestId ?? 'no-request-id', $e->getMessage()));
}
```

| Member | Meaning |
|---|---|
| `$e->status` | HTTP status, or `0` when the request never got a response |
| `$e->errorCode` | Machine-readable code, e.g. `payment_declined`. Defaults to `api_error`. Named `errorCode` because `\Exception::$code` is reserved for an int |
| `$e->getMessage()` | Human-readable message from the API |
| `$e->details` | Structured detail from the API body (`[]` when absent) |
| `$e->requestId` | `X-Request-Id` (falling back to `X-Reevit-Request-Id`) — quote this in support tickets |
| `$e->isRecoverable()` | Whether a retry could plausibly succeed |
| `$e->getPrevious()` | The originating Guzzle exception, preserved |

Error codes are shared across every Reevit SDK, including
`unexpected_response_shape` — raised when a list endpoint answers with a body
this SDK cannot recognise, rather than silently returning an empty list.

## Features

- **Payments**: Create intents, update intents, confirm, confirm intent, cancel, retry, refund, stats
- **Connections**: Manage PSP integrations, validation, labels, status, audit
- **Subscriptions**: Manage recurring billing lifecycle
- **Fraud**: Configure fraud rules
- **Customers / Payment Links / Checkout Sessions / Webhooks / Routing Rules / Invoices**: Additional backend services
- **PSR-4 Autoloading**: Standard PHP structure

Passing `null` for `orgId` is still accepted for backward compatibility, but authenticated org-scoped requests should include it.

Use `$client->connections->listPage()` when pagination metadata matters, or
`$client->connections->listAll()` to fetch every connected PSP matching the
supplied filters.

---

## Webhook Verification

Reevit sends webhooks to notify your application of payment events. Always verify webhook signatures.

### Understanding Webhooks

There are **two types of webhooks** in Reevit:

1. **Inbound Webhooks (PSP → Reevit)**: Webhooks from payment providers (Paystack, Flutterwave, etc.) to Reevit. Configure these in the PSP dashboard. Reevit handles them automatically.

2. **Outbound Webhooks (Reevit → Your App)**: Webhooks from Reevit to your application. Configure in Reevit Dashboard and create a handler in your app.

### Signature Format

- **Header**: `X-Reevit-Signature: sha256=<hex-signature>`
- **Signature**: `HMAC-SHA256(request_body, signing_secret)`

### Getting Your Signing Secret

1. Go to **Reevit Dashboard > Developers > Webhooks**
2. Configure your webhook endpoint URL
3. Copy the signing secret (starts with `whsec_`)
4. Set environment variable: `REEVIT_WEBHOOK_SECRET=whsec_xxx...`

The SDK ships a constant-time verifier — `Reevit\Webhooks\SignatureVerifier::verify($payload, $signature, $secret)` — so you do not have to reimplement HMAC. Pass the **raw** request body (`file_get_contents('php://input')`, not parsed-and-reencoded JSON), the `X-Reevit-Signature` header, and your signing secret.

### PHP Webhook Handler

```php
<?php
// webhooks/reevit.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Reevit\Webhooks\SignatureVerifier;

/**
 * Payment event data structure
 */
class PaymentData {
    public string $id;
    public string $status;
    public int $amount;
    public string $currency;
    public string $provider;
    public ?string $customer_id;
    public ?array $metadata;
    
    public function __construct(array $data) {
        $this->id = $data['id'] ?? '';
        $this->status = $data['status'] ?? '';
        $this->amount = $data['amount'] ?? 0;
        $this->currency = $data['currency'] ?? '';
        $this->provider = $data['provider'] ?? '';
        $this->customer_id = $data['customer_id'] ?? null;
        $this->metadata = $data['metadata'] ?? null;
    }
}

/**
 * Subscription event data structure
 */
class SubscriptionData {
    public string $id;
    public string $customer_id;
    public string $plan_id;
    public string $status;
    public int $amount;
    public string $currency;
    public string $interval;
    public ?string $next_renewal_at;
    
    public function __construct(array $data) {
        $this->id = $data['id'] ?? '';
        $this->customer_id = $data['customer_id'] ?? '';
        $this->plan_id = $data['plan_id'] ?? '';
        $this->status = $data['status'] ?? '';
        $this->amount = $data['amount'] ?? 0;
        $this->currency = $data['currency'] ?? '';
        $this->interval = $data['interval'] ?? '';
        $this->next_renewal_at = $data['next_renewal_at'] ?? null;
    }
}

// Payment handlers
function handlePaymentSucceeded(PaymentData $data): void {
    $orderId = $data->metadata['order_id'] ?? null;
    error_log("[Webhook] Payment succeeded: {$data->id} for order $orderId");
    
    // TODO: Implement your business logic
    // - Update order status to "paid"
    // - Send confirmation email to customer
    // - Trigger fulfillment process
}

function handlePaymentFailed(PaymentData $data): void {
    error_log("[Webhook] Payment failed: {$data->id}");
    
    // TODO: Implement your business logic
    // - Update order status to "payment_failed"
    // - Send notification to customer
    // - Allow retry
}

function handlePaymentRefunded(PaymentData $data): void {
    $orderId = $data->metadata['order_id'] ?? null;
    error_log("[Webhook] Payment refunded: {$data->id} for order $orderId");
    
    // TODO: Implement your business logic
    // - Update order status to "refunded"
    // - Restore inventory if applicable
}

// Subscription handlers
function handleSubscriptionCreated(SubscriptionData $data): void {
    error_log("[Webhook] Subscription created: {$data->id} for customer {$data->customer_id}");
    
    // TODO: Implement your business logic
    // - Grant access to subscription features
    // - Send welcome email
}

function handleSubscriptionRenewed(SubscriptionData $data): void {
    error_log("[Webhook] Subscription renewed: {$data->id}");
    
    // TODO: Implement your business logic
    // - Extend access period
    // - Send renewal confirmation
}

function handleSubscriptionCanceled(SubscriptionData $data): void {
    error_log("[Webhook] Subscription canceled: {$data->id}");
    
    // TODO: Implement your business logic
    // - Revoke access at end of billing period
    // - Send cancellation confirmation
}

// Main webhook handler
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_REEVIT_SIGNATURE'] ?? '';
$secret = getenv('REEVIT_WEBHOOK_SECRET');

// Verify signature (required in production)
if (!SignatureVerifier::verify($payload, $signature, $secret)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
$eventType = $event['type'] ?? '';
$eventId = $event['id'] ?? '';

error_log("[Webhook] Received: $eventType ($eventId)");

// Handle different event types
switch ($eventType) {
    // Test event
    case 'reevit.webhook.test':
        error_log("[Webhook] Test received: " . ($event['message'] ?? ''));
        break;
    
    // Payment events
    case 'payment.succeeded':
        handlePaymentSucceeded(new PaymentData($event['data'] ?? []));
        break;
    
    case 'payment.failed':
        handlePaymentFailed(new PaymentData($event['data'] ?? []));
        break;
    
    case 'payment.refunded':
        handlePaymentRefunded(new PaymentData($event['data'] ?? []));
        break;
    
    case 'payment.pending':
        $data = new PaymentData($event['data'] ?? []);
        error_log("[Webhook] Payment pending: {$data->id}");
        break;
    
    // Subscription events
    case 'subscription.created':
        handleSubscriptionCreated(new SubscriptionData($event['data'] ?? []));
        break;
    
    case 'subscription.renewed':
        handleSubscriptionRenewed(new SubscriptionData($event['data'] ?? []));
        break;
    
    case 'subscription.canceled':
        handleSubscriptionCanceled(new SubscriptionData($event['data'] ?? []));
        break;
    
    default:
        error_log("[Webhook] Unhandled event: $eventType");
}

// Acknowledge receipt
http_response_code(200);
echo json_encode(['received' => true]);
```

### Laravel Webhook Handler

```php
<?php
// routes/api.php
Route::post('/webhooks/reevit', [WebhookController::class, 'handle']);

// app/Http/Controllers/WebhookController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Reevit\Webhooks\SignatureVerifier;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Reevit-Signature', '');
        $secret = config('services.reevit.webhook_secret');
        
        // Verify signature (required in production)
        if (!SignatureVerifier::verify($payload, $signature, $secret)) {
            Log::warning('[Webhook] Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        $event = $request->all();
        $eventType = $event['type'] ?? '';
        $eventId = $event['id'] ?? '';
        
        Log::info("[Webhook] Received: $eventType ($eventId)");
        
        // Handle different event types
        switch ($eventType) {
            // Test event
            case 'reevit.webhook.test':
                Log::info('[Webhook] Test received: ' . ($event['message'] ?? ''));
                break;
            
            // Payment events
            case 'payment.succeeded':
                $this->handlePaymentSucceeded($event['data'] ?? []);
                break;
            
            case 'payment.failed':
                $this->handlePaymentFailed($event['data'] ?? []);
                break;
            
            case 'payment.refunded':
                $this->handlePaymentRefunded($event['data'] ?? []);
                break;
            
            // Subscription events
            case 'subscription.created':
                $this->handleSubscriptionCreated($event['data'] ?? []);
                break;
            
            case 'subscription.renewed':
                $this->handleSubscriptionRenewed($event['data'] ?? []);
                break;
            
            case 'subscription.canceled':
                $this->handleSubscriptionCanceled($event['data'] ?? []);
                break;
            
            default:
                Log::info("[Webhook] Unhandled event: $eventType");
        }
        
        return response()->json(['received' => true]);
    }
    
    // Payment handlers
    private function handlePaymentSucceeded(array $data): void
    {
        $paymentId = $data['id'] ?? '';
        $orderId = $data['metadata']['order_id'] ?? null;
        Log::info("[Webhook] Payment succeeded: $paymentId for order $orderId");
        
        // TODO: Implement your business logic
        // - Update order status to "paid"
        // - Send confirmation email to customer
        // - Trigger fulfillment process
    }
    
    private function handlePaymentFailed(array $data): void
    {
        $paymentId = $data['id'] ?? '';
        Log::info("[Webhook] Payment failed: $paymentId");
        
        // TODO: Implement your business logic
        // - Update order status to "payment_failed"
        // - Send notification to customer
    }
    
    private function handlePaymentRefunded(array $data): void
    {
        $paymentId = $data['id'] ?? '';
        $orderId = $data['metadata']['order_id'] ?? null;
        Log::info("[Webhook] Payment refunded: $paymentId for order $orderId");
        
        // TODO: Implement your business logic
        // - Update order status to "refunded"
    }
    
    // Subscription handlers
    private function handleSubscriptionCreated(array $data): void
    {
        $subscriptionId = $data['id'] ?? '';
        $customerId = $data['customer_id'] ?? '';
        Log::info("[Webhook] Subscription created: $subscriptionId for customer $customerId");
        
        // TODO: Grant access to subscription features
    }
    
    private function handleSubscriptionRenewed(array $data): void
    {
        $subscriptionId = $data['id'] ?? '';
        Log::info("[Webhook] Subscription renewed: $subscriptionId");
        
        // TODO: Extend access period
    }
    
    private function handleSubscriptionCanceled(array $data): void
    {
        $subscriptionId = $data['id'] ?? '';
        Log::info("[Webhook] Subscription canceled: $subscriptionId");
        
        // TODO: Revoke access at end of billing period
    }
}
```

### Laravel Configuration

```php
// config/services.php
return [
    // ...
    'reevit' => [
        'api_key' => env('REEVIT_API_KEY'),
        'org_id' => env('REEVIT_ORG_ID'),
        'webhook_secret' => env('REEVIT_WEBHOOK_SECRET'),
    ],
];
```

## Supported PSPs

| Provider | Countries | Payment Methods |
|----------|-----------|-----------------|
| Paystack | NG, GH, ZA, KE | Card, Mobile Money, Bank Transfer |
| Flutterwave | NG, GH, KE, ZA + | Card, Mobile Money, Bank Transfer |
| Hubtel | GH | Mobile Money |
| Stripe | Global (50+) | Card, Apple Pay, Google Pay |
| Monnify | NG | Card, Bank Transfer, USSD |
| M-Pesa | KE, TZ | Mobile Money (STK Push) |

---

## Release Notes

### Unreleased

#### Changed

- **Behaviour change**: failed requests now raise `Reevit\ReevitApiException`
  instead of escaping as a raw `GuzzleHttp\Exception\ClientException` /
  `ServerException` / `ConnectException`. The Guzzle exception is preserved as
  `getPrevious()`, so `catch (\GuzzleHttp\Exception\GuzzleException $e)` no
  longer fires — catch `Reevit\ReevitApiException` (or inspect
  `$e->getPrevious()`) instead.

#### Fixed

- A path prefix on a custom base URL is no longer dropped. `new Reevit($key,
  $org, 'https://gateway.internal/reevit')` now requests
  `/reevit/v1/payments`; previously Guzzle's RFC 3986 resolution let the
  absolute request path replace the base path and the SDK requested
  `/v1/payments`.

### v0.9.0

- Added server-created checkout sessions
- Version alignment across all Reevit SDKs
- Updated documentation and webhook examples
- Added support for Apple Pay and Google Pay
- Updated supported PSPs and payment methods documentation

---

## Environment Variables

```bash
REEVIT_API_KEY=pfk_live_xxx
REEVIT_ORG_ID=org_xxx
REEVIT_WEBHOOK_SECRET=whsec_xxx  # Get from Dashboard > Developers > Webhooks
```

### v0.3.0

- Standardized authenticated requests on `X-Reevit-Key`
- Restored `orgId` support on the client constructor
- Defaulted all requests to `https://api.reevit.io`
- Added payment lifecycle, customer, payment link, webhook, routing rule, and invoice services

---

## Support

- **Documentation**: [https://docs.reevit.io](https://docs.reevit.io)
- **GitHub Issues**: [https://github.com/Reevit-Platform/backend/issues](https://github.com/Reevit-Platform/backend/issues)
- **Email**: support@reevit.io

## License

MIT License - see [LICENSE](LICENSE) for details.
