<?php

declare(strict_types=1);

namespace Reevit\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Reevit\Internal\ListEnvelope;
use Reevit\ReevitApiException;

final class ListEnvelopeTest extends TestCase
{
    public function testBareArrayIsReturnedAsIs(): void
    {
        $response = [['id' => 'cus_1'], ['id' => 'cus_2']];

        $this->assertSame($response, ListEnvelope::extractArray($response, 'customers'));
    }

    public function testEmptyBareArrayReturnsEmptyArray(): void
    {
        $this->assertSame([], ListEnvelope::extractArray([], 'customers'));
    }

    public function testLegacyFlatKeyIsUnwrapped(): void
    {
        $records = [['id' => 'cus_1'], ['id' => 'cus_2']];
        $response = ['customers' => $records];

        $this->assertSame($records, ListEnvelope::extractArray($response, 'customers'));
    }

    public function testPaginatedEnvelopeIsUnwrapped(): void
    {
        $records = [['id' => 'cus_1'], ['id' => 'cus_2']];
        $response = [
            'data' => $records,
            'pagination' => ['total' => 2, 'has_more' => false],
        ];

        $this->assertSame($records, ListEnvelope::extractArray($response, 'customers'));
    }

    public function testDoubleNestedEnvelopeIsUnwrapped(): void
    {
        $records = [['id' => 'log_1'], ['id' => 'log_2']];
        $response = [
            'data' => ['logs' => $records],
            'pagination' => ['total' => 2, 'has_more' => false],
        ];

        $this->assertSame($records, ListEnvelope::extractArray($response, 'logs'));
    }

    /**
     * The policy this encodes: `[]` means "a recognised container, and it was
     * empty". An unrecognised body raises, because a reconciliation sweep that
     * silently returns `[]` after a shape change reports zero settlements.
     * Matches the Go and Rust SDKs.
     *
     * @dataProvider unrecognisedShapeProvider
     *
     * @param mixed $response
     */
    public function testUnrecognisedShapeRaisesRatherThanReturningAnEmptyList($response): void
    {
        $this->assertNull(ListEnvelope::tryExtractArray($response, 'customers'));

        $this->expectException(ReevitApiException::class);
        ListEnvelope::extractArray($response, 'customers');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unrecognisedShapeProvider(): array
    {
        return [
            'envelope with neither the key nor data' => [['pagination' => ['total' => 0, 'has_more' => false]]],
            'null body (e.g. a 204)' => [null],
            'non-array body' => ['unexpected-string'],
            'data object lacking the expected key' => [[
                'data' => ['unrelated' => 'value'],
                'pagination' => ['total' => 0, 'has_more' => false],
            ]],
        ];
    }

    public function testUnrecognisedShapeCarriesTheSharedErrorCode(): void
    {
        try {
            ListEnvelope::extractArray(['pagination' => []], 'customers');
            $this->fail('expected ReevitApiException');
        } catch (ReevitApiException $e) {
            $this->assertSame('unexpected_response_shape', $e->errorCode);
            $this->assertSame(0, $e->status);
            $this->assertStringContainsString('customers', $e->getMessage());
        }
    }

    public function testARecognisedButEmptyContainerStillReturnsAnEmptyList(): void
    {
        $this->assertSame([], ListEnvelope::extractArray([], 'customers'));
        $this->assertSame([], ListEnvelope::extractArray(['customers' => []], 'customers'));
        $this->assertSame([], ListEnvelope::extractArray(['data' => [], 'pagination' => []], 'customers'));
        $this->assertSame([], ListEnvelope::extractArray(['data' => ['customers' => []]], 'customers'));
    }
}
