<?php

declare(strict_types=1);

namespace Reevit\Internal;

/**
 * Defensive unwrapping for list-endpoint responses.
 *
 * The backend may respond to a "list" endpoint in any of these shapes:
 *
 *   1. `[...]`                                      bare array (legacy)
 *   2. `{"customers": [...]}`                        legacy flat key
 *   3. `{"data": [...], "pagination": {...}}`         paginated envelope
 *   4. `{"data": {"logs": [...]}, "pagination": {...}}` double-nested envelope
 *
 * {@see extractArray()} normalizes all four shapes to the plain list of
 * records, and returns `[]` for anything else. It never returns the raw
 * envelope itself -- doing so would let a caller iterate `data` and
 * `pagination` as if they were records.
 *
 * This class intentionally does not read or expose pagination metadata
 * (`total`, `has_more`, cursors, ...); it exists solely to keep list methods
 * forward-compatible with an as-yet-unshipped envelope format.
 */
final class ListEnvelope
{
    /**
     * Extract the list of records from a decoded JSON response body.
     *
     * @param mixed  $response The decoded response body (typically an array, or null).
     * @param string $key      The legacy flat key for this resource, e.g. 'customers'.
     *
     * @return array<int, mixed>
     */
    public static function extractArray($response, string $key): array
    {
        if (!is_array($response)) {
            return [];
        }

        // Shape 1: bare list.
        if (self::isList($response)) {
            return $response;
        }

        // Shape 2: legacy flat key, e.g. {"customers": [...]}.
        if (isset($response[$key]) && is_array($response[$key])) {
            return $response[$key];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            // Shape 3: {"data": [...], "pagination": {...}}.
            if (self::isList($response['data'])) {
                return $response['data'];
            }

            // Shape 4: {"data": {"logs": [...]}, "pagination": {...}}.
            if (isset($response['data'][$key]) && is_array($response['data'][$key])) {
                return $response['data'][$key];
            }
        }

        return [];
    }

    /**
     * Equivalent of `array_is_list()` (PHP 8.1+), reimplemented so this helper
     * also works on the package's stated minimum PHP version (7.4).
     *
     * @param array<mixed> $array
     */
    private static function isList(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $expectedKey = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }

        return true;
    }
}
