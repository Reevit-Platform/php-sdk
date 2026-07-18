<?php

declare(strict_types=1);

namespace Reevit\Tests\Webhooks;

use PHPUnit\Framework\TestCase;
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
}
