<?php

declare(strict_types=1);

namespace Reevit\Tests\Webhooks;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Reevit\ReevitApiException;
use Reevit\Webhooks\SignatureVerifier;

final class SignatureVerifierTest extends TestCase
{
    // Canonical known-answer vector shared across the Node, Python and PHP SDK
    // tests. HMAC-SHA256(secret, body) hex, prefixed with "sha256=" -- exactly
    // what the Go backend emits in the X-Reevit-Signature header.
    private const SECRET = 'whsec_test_2x9aBcDeFgHiJkLmNoPqRsTuVwXyZ012';
    private const BODY = '{"event":"payment.updated","org_id":"org_123","signature_timestamp":"2026-06-13T12:00:00Z","data":{"id":"pay_abc","status":"succeeded"}}';
    private const EXPECTED_SIG = 'sha256=8fed6e24bd1c97ac5634ec88081a8299107706bf488290513bc3e5c5340e1950';

    public function testSignMatchesKnownVector(): void
    {
        $this->assertSame(self::EXPECTED_SIG, SignatureVerifier::sign(self::BODY, self::SECRET));
    }

    public function testVerifyAcceptsValidSignature(): void
    {
        $this->assertTrue(SignatureVerifier::verify(self::BODY, self::EXPECTED_SIG, self::SECRET));
    }

    public function testVerifyRejectsTamperedBody(): void
    {
        $tampered = str_replace('succeeded', 'failed', self::BODY);
        $this->assertFalse(SignatureVerifier::verify($tampered, self::EXPECTED_SIG, self::SECRET));
    }

    public function testVerifyRejectsWrongSecret(): void
    {
        $this->assertFalse(SignatureVerifier::verify(self::BODY, self::EXPECTED_SIG, 'whsec_wrong'));
    }

    public function testVerifyRejectsMissingOrEmptyInputs(): void
    {
        $this->assertFalse(SignatureVerifier::verify(self::BODY, null, self::SECRET));
        $this->assertFalse(SignatureVerifier::verify(self::BODY, '', self::SECRET));
        $this->assertFalse(SignatureVerifier::verify(self::BODY, self::EXPECTED_SIG, ''));
    }

    public function testVerifyRejectsSignatureWithoutPrefix(): void
    {
        $bare = substr(self::EXPECTED_SIG, strlen('sha256='));
        $this->assertFalse(SignatureVerifier::verify(self::BODY, $bare, self::SECRET));
    }

    // ---- replay protection -------------------------------------------------

    /** The instant the canonical vector's signature_timestamp names. */
    private const SIGNED_AT = '2026-06-13T12:00:00Z';

    public function testVerifyWithToleranceAcceptsAFreshDelivery(): void
    {
        $this->assertTrue(SignatureVerifier::verifyWithTolerance(
            self::BODY,
            self::EXPECTED_SIG,
            self::SECRET,
            300,
            new DateTimeImmutable('2026-06-13T12:04:59Z')
        ));
    }

    public function testVerifyWithToleranceRejectsAStaleDelivery(): void
    {
        $this->assertFalse(SignatureVerifier::verifyWithTolerance(
            self::BODY,
            self::EXPECTED_SIG,
            self::SECRET,
            300,
            new DateTimeImmutable('2026-06-13T12:05:01Z')
        ));
    }

    public function testVerifyWithToleranceRejectsAFutureDatedDeliveryBeyondSkew(): void
    {
        $this->assertFalse(SignatureVerifier::verifyWithTolerance(
            self::BODY,
            self::EXPECTED_SIG,
            self::SECRET,
            300,
            new DateTimeImmutable('2026-06-13T11:54:59Z')
        ));
    }

    public function testVerifyWithToleranceStillRejectsABadSignatureRegardlessOfFreshness(): void
    {
        $tampered = str_replace('succeeded', 'failed', self::BODY);

        $this->assertFalse(SignatureVerifier::verifyWithTolerance(
            $tampered,
            self::EXPECTED_SIG,
            self::SECRET,
            300,
            new DateTimeImmutable(self::SIGNED_AT)
        ));
    }

    public function testVerifyWithToleranceDefaultsTo300Seconds(): void
    {
        $this->assertSame(300, SignatureVerifier::DEFAULT_TOLERANCE_SECONDS);

        $this->assertTrue(SignatureVerifier::verifyWithTolerance(
            self::BODY,
            self::EXPECTED_SIG,
            self::SECRET,
            SignatureVerifier::DEFAULT_TOLERANCE_SECONDS,
            new DateTimeImmutable('2026-06-13T12:02:00Z')
        ));
    }

    public function testVerifyWithToleranceRejectsABodyWithNoSignatureTimestamp(): void
    {
        $body = '{"event":"payment.updated","data":{"id":"pay_abc"}}';
        $signature = SignatureVerifier::sign($body, self::SECRET);

        $this->assertTrue(SignatureVerifier::verify($body, $signature, self::SECRET));
        $this->assertFalse(SignatureVerifier::verifyWithTolerance($body, $signature, self::SECRET));
    }

    // ---- constructEvent ----------------------------------------------------

    public function testConstructEventReturnsTheDecodedEnvelope(): void
    {
        $event = SignatureVerifier::constructEvent(
            self::BODY,
            self::EXPECTED_SIG,
            self::SECRET,
            300,
            new DateTimeImmutable(self::SIGNED_AT)
        );

        $this->assertSame('payment.updated', $event['event']);
        $this->assertSame('org_123', $event['org_id']);
        $this->assertSame(['id' => 'pay_abc', 'status' => 'succeeded'], $event['data']);
    }

    public function testConstructEventRejectsABadSignature(): void
    {
        try {
            SignatureVerifier::constructEvent(self::BODY, 'sha256=deadbeef', self::SECRET);
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('invalid_signature', $e->errorCode);
        }
    }

    public function testConstructEventRejectsAStaleDelivery(): void
    {
        try {
            SignatureVerifier::constructEvent(
                self::BODY,
                self::EXPECTED_SIG,
                self::SECRET,
                300,
                new DateTimeImmutable('2026-06-13T13:00:00Z')
            );
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('timestamp_outside_tolerance', $e->errorCode);
            $this->assertStringContainsString('300s tolerance', $e->getMessage());
        }
    }

    public function testConstructEventRejectsAMissingTimestamp(): void
    {
        $body = '{"event":"payment.updated"}';

        try {
            SignatureVerifier::constructEvent($body, SignatureVerifier::sign($body, self::SECRET), self::SECRET);
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('missing_signature_timestamp', $e->errorCode);
        }
    }

    public function testConstructEventRejectsARelativeTimestampRatherThanResolvingIt(): void
    {
        // "now" would sail through a naive `new DateTimeImmutable($value)`.
        $body = '{"event":"payment.updated","signature_timestamp":"now"}';

        try {
            SignatureVerifier::constructEvent($body, SignatureVerifier::sign($body, self::SECRET), self::SECRET);
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('invalid_signature_timestamp', $e->errorCode);
        }
    }

    public function testConstructEventRejectsANonObjectBody(): void
    {
        $body = '"just-a-string"';

        try {
            SignatureVerifier::constructEvent($body, SignatureVerifier::sign($body, self::SECRET), self::SECRET);
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('missing_signature_timestamp', $e->errorCode);
        }
    }

    public function testConstructEventAcceptsANonZuluOffset(): void
    {
        $body = '{"event":"payment.updated","signature_timestamp":"2026-06-13T13:00:00+01:00"}';

        $event = SignatureVerifier::constructEvent(
            $body,
            SignatureVerifier::sign($body, self::SECRET),
            self::SECRET,
            300,
            new DateTimeImmutable(self::SIGNED_AT)
        );

        $this->assertSame('payment.updated', $event['event']);
    }
}
