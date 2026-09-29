<?php

namespace App\Support\Sms;

final class PhilippineSmsRecipient
{
    /**
     * Normalize supported Philippine mobile-number formats
     * into the local 09XXXXXXXXX format used by StyleHub SMS.
     *
     * Supported examples:
     *
     * 09171234567
     * 9171234567
     * 639171234567
     * +639171234567
     * +63 917 123 4567
     */
    public static function normalize(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(
            $value
        );

        if ($value === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Remove ordinary formatting characters only
        |--------------------------------------------------------------------------
        |
        | We intentionally do NOT remove arbitrary letters or symbols.
        |
        */

        $compact =
            preg_replace(
                '/[\s\-\(\)\.]+/',
                '',
                $value
            );

        if (
            ! is_string($compact)
            || $compact === ''
        ) {
            return null;
        }

        /*
         * Only an optional leading plus sign and digits are allowed.
         */
        if (
            preg_match(
                '/^\+?\d+$/',
                $compact
            ) !== 1
        ) {
            return null;
        }

        if (
            str_starts_with(
                $compact,
                '+'
            )
        ) {
            $compact =
                substr(
                    $compact,
                    1
                );
        }

        /*
        |--------------------------------------------------------------------------
        | +63 / 63 Format
        |--------------------------------------------------------------------------
        |
        | 639171234567
        |      ↓
        | 09171234567
        |
        */

        if (
            strlen($compact) === 12
            && str_starts_with(
                $compact,
                '639'
            )
        ) {
            $compact =
                '0'.substr(
                    $compact,
                    2
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile Format Without Leading Zero
        |--------------------------------------------------------------------------
        |
        | 9171234567
        |      ↓
        | 09171234567
        |
        */

        if (
            strlen($compact) === 10
            && str_starts_with(
                $compact,
                '9'
            )
        ) {
            $compact =
                '0'.$compact;
        }

        /*
        |--------------------------------------------------------------------------
        | Final Philippine Mobile Validation
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/^09\d{9}$/',
                $compact
            ) !== 1
        ) {
            return null;
        }

        return $compact;
    }

    /**
     * Produce a privacy-friendly representation for terminal output.
     *
     * Example:
     *
     * 09171234567
     *      ↓
     * 0917***4567
     */
    public static function mask(
        mixed $value
    ): string {
        $normalized =
            self::normalize(
                $value
            );

        if ($normalized === null) {
            return 'invalid-number';
        }

        return substr(
            $normalized,
            0,
            4
        )
            .'***'
            .substr(
                $normalized,
                -4
            );
    }
}