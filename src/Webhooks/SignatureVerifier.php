<?php

declare(strict_types=1);

namespace Reevit\Webhooks;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Reevit\ReevitApiException;

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
 *
 * {@see verifyWithTolerance()} and {@see constructEvent()} do the replay check
 * for you, so most handlers should use one of those rather than {@see verify()}.
 */
final class SignatureVerifier
{
    private const SIGNATURE_PREFIX = 'sha256=';

    /** Matches every other Reevit SDK's replay window. */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private const RFC3339_PATTERN = '/^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(\.\d+)?([Zz]|[+-]\d{2}:?\d{2})$/';

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

    /**
     * Verify the signature *and* that the signed `signature_timestamp` is
     * recent, which is what stops a captured-but-valid delivery being replayed.
     *
     * The timestamp is read from the signed body, so it cannot be tampered with
     * without breaking the signature. A body without a `signature_timestamp`
     * is rejected: freshness cannot be proven, and silently accepting it would
     * defeat the point of this helper. Use {@see verify()} if you deliberately
     * want the signature check alone.
     *
     * @param string                 $payload          Raw request body, exactly as received.
     * @param string|null            $signature        The `X-Reevit-Signature` header value.
     * @param string                 $secret           The signing secret for the webhook endpoint.
     * @param int                    $toleranceSeconds Maximum age (and future skew) to accept.
     * @param DateTimeInterface|null $now              Overridable clock, for tests.
     */
    public static function verifyWithTolerance(
        string $payload,
        ?string $signature,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?DateTimeInterface $now = null
    ): bool {
        if (!self::verify($payload, $signature, $secret)) {
            return false;
        }

        return self::timestampRejectionReason($payload, $toleranceSeconds, $now) === null;
    }

    /**
     * Verify a delivery and decode it, or throw explaining why it was rejected.
     *
     * The webhook counterpart of a successful API call: one call that leaves
     * you holding a trustworthy event.
     *
     * ```php
     * $event = SignatureVerifier::constructEvent(
     *     file_get_contents('php://input'),
     *     $_SERVER['HTTP_X_REEVIT_SIGNATURE'] ?? null,
     *     getenv('REEVIT_WEBHOOK_SECRET')
     * );
     * ```
     *
     * @param string                 $rawBody          Raw request body, exactly as received.
     * @param string|null            $signature        The `X-Reevit-Signature` header value.
     * @param string                 $secret           The signing secret for the webhook endpoint.
     * @param int                    $toleranceSeconds Maximum age (and future skew) to accept.
     * @param DateTimeInterface|null $now              Overridable clock, for tests.
     *
     * @return array<string, mixed> The decoded event envelope.
     *
     * @throws ReevitApiException With code `invalid_signature`,
     *                            `missing_signature_timestamp`,
     *                            `invalid_signature_timestamp`,
     *                            `timestamp_outside_tolerance` or
     *                            `invalid_payload`.
     */
    public static function constructEvent(
        string $rawBody,
        ?string $signature,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        ?DateTimeInterface $now = null
    ): array {
        if (!self::verify($rawBody, $signature, $secret)) {
            throw new ReevitApiException(
                'webhook signature verification failed',
                0,
                'invalid_signature'
            );
        }

        // Only now that the bytes are proven authentic do we interpret them.
        $reason = self::timestampRejectionReason($rawBody, $toleranceSeconds, $now);
        if ($reason !== null) {
            throw new ReevitApiException($reason[1], 0, $reason[0]);
        }

        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new ReevitApiException(
                'webhook body is not a JSON object',
                0,
                'invalid_payload'
            );
        }

        return $event;
    }

    /**
     * @return array{0: string, 1: string}|null A [code, message] pair, or null
     *                                          when the timestamp is acceptable.
     */
    private static function timestampRejectionReason(
        string $payload,
        int $toleranceSeconds,
        ?DateTimeInterface $now
    ): ?array {
        $body = json_decode($payload, true);
        $raw = is_array($body) ? ($body['signature_timestamp'] ?? null) : null;

        if (!is_string($raw) || $raw === '') {
            return [
                'missing_signature_timestamp',
                'webhook body has no signature_timestamp, so its freshness cannot be verified',
            ];
        }

        $signedAt = self::parseRfc3339($raw);
        if ($signedAt === null) {
            return [
                'invalid_signature_timestamp',
                sprintf('webhook signature_timestamp is not a valid RFC 3339 timestamp: %s', $raw),
            ];
        }

        $reference = $now ?? new DateTimeImmutable('now');
        $age = abs($reference->getTimestamp() - $signedAt->getTimestamp());
        if ($age > $toleranceSeconds) {
            return [
                'timestamp_outside_tolerance',
                sprintf(
                    'webhook signature_timestamp is %ds away from now, outside the %ds tolerance',
                    $age,
                    $toleranceSeconds
                ),
            ];
        }

        return null;
    }

    private static function parseRfc3339(string $value): ?DateTimeImmutable
    {
        // Guard against DateTimeImmutable's relative formats ("now", "+1 day")
        // before handing the string over.
        if (preg_match(self::RFC3339_PATTERN, $value) !== 1) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            return null;
        }
    }
}
