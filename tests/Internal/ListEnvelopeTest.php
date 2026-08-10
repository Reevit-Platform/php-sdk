<?php

declare(strict_types=1);

namespace Reevit\Tests\Internal;

use PHPUnit\Framework\TestCase;
use Reevit\Internal\ListEnvelope;

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

    public function testMissingKeyReturnsEmptyArrayNotTheEnvelope(): void
    {
        // This is the bug being fixed: a response that is a non-empty
        // associative array with neither the legacy key nor a usable `data`
        // key must resolve to [], never to the raw envelope.
        $response = ['pagination' => ['total' => 0, 'has_more' => false]];

        $this->assertSame([], ListEnvelope::extractArray($response, 'customers'));
    }

    public function testNullResponseReturnsEmptyArray(): void
    {
        $this->assertSame([], ListEnvelope::extractArray(null, 'customers'));
    }

    public function testNonArrayResponseReturnsEmptyArray(): void
    {
        $this->assertSame([], ListEnvelope::extractArray('unexpected-string', 'customers'));
    }

    public function testDataKeyThatIsNotAListAndLacksTheExpectedKeyReturnsEmptyArray(): void
    {
        $response = [
            'data' => ['unrelated' => 'value'],
            'pagination' => ['total' => 0, 'has_more' => false],
        ];

        $this->assertSame([], ListEnvelope::extractArray($response, 'customers'));
    }
}
