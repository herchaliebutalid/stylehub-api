<?php

namespace App\Support;

use InvalidArgumentException;

final class AuditMetadataSanitizer
{
    /**
     * Sensitive field names that must never be stored
     * inside audit metadata.
     *
     * @var list<string>
     */
    private const BLOCKED_KEY_PARTS = [
        'password',
        'passwd',
        'token',
        'secret',
        'api_key',
        'apikey',
        'authorization',
        'cookie',
        'remember_token',
        'access_token',
        'refresh_token',
    ];

    /**
     * Validate and return safe audit metadata.
     *
     * @param array<string|int, mixed> $metadata
     * @return array<string|int, mixed>
     */
    public static function sanitize(
        array $metadata
    ): array {
        return self::sanitizeArray(
            $metadata
        );
    }

    /**
     * @param array<string|int, mixed> $values
     * @return array<string|int, mixed>
     */
    private static function sanitizeArray(
        array $values
    ): array {
        $safe = [];

        foreach (
            $values as $key => $value
        ) {
            if (
                is_string($key)
                && self::isBlockedKey(
                    $key
                )
            ) {
                throw new InvalidArgumentException(
                    "Sensitive audit metadata field [{$key}] is not allowed."
                );
            }

            if (is_array($value)) {
                $safe[$key] =
                    self::sanitizeArray(
                        $value
                    );

                continue;
            }

            /*
             * Audit metadata must remain JSON-safe and predictable.
             */
            if (
                is_object($value)
                || is_resource($value)
            ) {
                throw new InvalidArgumentException(
                    'Audit metadata may contain only scalar values, null, and arrays.'
                );
            }

            $safe[$key] =
                $value;
        }

        return $safe;
    }

    private static function isBlockedKey(
        string $key
    ): bool {
        $normalized =
            strtolower(
                trim(
                    $key
                )
            );

        foreach (
            self::BLOCKED_KEY_PARTS
            as $blocked
        ) {
            if (
                str_contains(
                    $normalized,
                    $blocked
                )
            ) {
                return true;
            }
        }

        return false;
    }
}