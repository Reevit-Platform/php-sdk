<?php

declare(strict_types=1);

namespace Reevit;

use RuntimeException;
use Throwable;

/**
 * The typed error every Reevit API failure surfaces as.
 *
 * Before this existed, a failing request escaped the SDK as a raw
 * `GuzzleHttp\Exception\ClientException`, and reaching the API's error code or
 * message meant re-reading and re-parsing `$e->getResponse()->getBody()` by
 * hand. Catch this instead:
 *
 * ```php
 * try {
 *     $payment = $reevit->payments->get($id);
 * } catch (\Reevit\ReevitApiException $e) {
 *     if ($e->errorCode === 'payment_declined') { ... }
 *     if ($e->isRecoverable()) { ... retry ... }
 *     error_log("reevit {$e->requestId}: {$e->getMessage()}");
 * }
 * ```
 *
 * The originating Guzzle exception is preserved as `getPrevious()`, so code
 * that already inspects the transport layer keeps working.
 *
 * Field names mirror the Go and Python SDKs (`status`, `code`, `message`,
 * `details`, `request_id`) with one forced exception: `\Exception` already
 * declares an untyped `$code` holding an int, so the API's machine-readable
 * code lives on `$errorCode`. `getCode()` returns the HTTP status, matching
 * Guzzle's own convention.
 */
class ReevitApiException extends RuntimeException
{
    /**
     * HTTP status of the failed response, or 0 when the request never produced
     * one (connection refused, DNS failure, timeout).
     */
    public readonly int $status;

    /**
     * Machine-readable error code from the API body, e.g. `payment_declined`.
     * Defaults to `api_error`. Named `errorCode` because `\Exception::$code` is
     * taken; this is the `code` field of the other SDKs' error types.
     */
    public readonly string $errorCode;

    /** @var array<string, mixed> Structured error detail from the API body. */
    public readonly array $details;

    /** Value of `X-Request-Id` (or `X-Reevit-Request-Id`), for support tickets. */
    public readonly ?string $requestId;

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        string $message,
        int $status = 0,
        string $errorCode = 'api_error',
        array $details = [],
        ?string $requestId = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);

        $this->status = $status;
        $this->errorCode = $errorCode === '' ? 'api_error' : $errorCode;
        $this->details = $details;
        $this->requestId = $requestId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Whether retrying the same request could plausibly succeed.
     *
     * True for transport failures (status 0) and for the statuses that signal a
     * transient condition rather than a rejected request. Mirrors the Node
     * SDK's `recoverable` flag so retry policies port across languages.
     */
    public function isRecoverable(): bool
    {
        return $this->status === 0
            || $this->status === 408
            || $this->status === 409
            || $this->status === 425
            || $this->status === 429
            || $this->status >= 500;
    }

    public function __toString(): string
    {
        $rendered = sprintf(
            'reevit: request failed with status %d (%s): %s',
            $this->status,
            $this->errorCode,
            $this->getMessage()
        );

        if ($this->requestId !== null && $this->requestId !== '') {
            $rendered .= sprintf(' [request_id=%s]', $this->requestId);
        }

        return $rendered;
    }
}
