<?php

declare(strict_types=1);

namespace Reevit\Webhooks;

/**
 * Webhook signature verification for Reevit outbound webhooks.
 *
 * Reevit signs every outbound webhook with HMAC-SHA256 over the *raw request
 * body* and sends the result in the `X-Reevit-Signature` header as
 * `sha256=<hex>`. The signed body includes a `signature_timestamp` field, so the
 * signature also covers the timestamp (tamper protection). To additionally guard
 * against replay of a captured-but-recent delivery, check that
 * `signature_timestamp` is recent after the signature verifies.
 *
 * IMPORTANT: verify against the exact bytes you received (e.g.
 * `file_get_contents('php://input')`). Do not `json_decode` and re-encode the
 * body first -- key order and whitespace must match what Reevit signed, or the
 * signature will not match.
 */
final class SignatureVerifier
{
    private const SIGNATURE_PREFIX = 'sha256=';

    /**
     * Compute the `X-Reevit-Signature` header value for a raw webhook body.
     * Returns `sha256=<hex HMAC-SHA256 of the body>`. Primarily useful for tests.
     */
    public static function sign(string $payload, string $secret): string
    {
        return self::SIGNATURE_PREFIX . hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify a Reevit webhook signature in constant time.
     *
     * @param string      $payload   Raw request body, exactly as received.
     * @param string|null $signature The `X-Reevit-Signature` header value.
     * @param string      $secret    The signing secret for the webhook endpoint.
     *
     * @return bool True only if the signature is present and valid.
     */
    public static function verify(string $payload, ?string $signature, string $secret): bool
    {
        if ($signature === null || $signature === '' || $secret === '') {
            return false;
        }

        // hash_equals is constant-time and length-safe.
        return hash_equals(self::sign($payload, $secret), $signature);
    }
}
