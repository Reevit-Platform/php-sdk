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

$client = new Reevit('pfk_live_xxx');

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
]);

echo "Payment created: " . $payment['id'] . "\n";

// List payments
$payments = $client->payments->list();
print_r($payments);
```

## Features

- **Payments**: Create intents, refund, list
- **Connections**: Manage PSP integrations
- **Subscriptions**: Manage recurring billing
- **Fraud**: Configure fraud rules
- **PSR-4 Autoloading**: Standard PHP structure

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

### PHP Webhook Handler

```php
<?php
// webhooks/reevit.php

declare(strict_types=1);

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

/**
 * Verify the webhook signature using HMAC-SHA256
 */
function verifySignature(string $payload, string $signature, string $secret): bool {
    if (strpos($signature, 'sha256=') !== 0) {
        return false;
    }
    
    $expected = hash_hmac('sha256', $payload, $secret);
    $received = substr($signature, 7); // Remove "sha256=" prefix
    
    return hash_equals($expected, $received);
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
if ($secret && !verifySignature($payload, $signature, $secret)) {
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

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Reevit-Signature', '');
        $secret = config('services.reevit.webhook_secret');
        
        // Verify signature (required in production)
        if ($secret && !$this->verifySignature($payload, $signature, $secret)) {
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
    
    private function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (strpos($signature, 'sha256=') !== 0) {
            return false;
        }
        
        $expected = hash_hmac('sha256', $payload, $secret);
        $received = substr($signature, 7);
        
        return hash_equals($expected, $received);
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

---

## Environment Variables

```bash
REEVIT_API_KEY=pfk_live_xxx
REEVIT_ORG_ID=org_xxx
REEVIT_WEBHOOK_SECRET=whsec_xxx  # Get from Dashboard > Developers > Webhooks
```

---

## Release Notes

### v0.3.0

- Updated API client to connect to production and sandbox URLs based on API key
- Added Bearer authentication headers for secure API communication
- Removed orgId parameter from client initialization (simplified API)
- Added .gitignore file to exclude unnecessary files from version control
- Updated README.md with corrected quick start example

---

## Support

- **Documentation**: [https://docs.reevit.io](https://docs.reevit.io)
- **GitHub Issues**: [https://github.com/Reevit-Platform/backend/issues](https://github.com/Reevit-Platform/backend/issues)
- **Email**: support@reevit.io

## License

MIT License - see [LICENSE](LICENSE) for details.
