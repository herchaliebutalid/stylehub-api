<?php

namespace App\Support;

final class PhilippinePhoneNumber
{
    /**
     * Normalize common Philippine mobile-number formats into E.164 format.
     *
     * Examples:
     *
     * 09171234567   -> +639171234567
     * 639171234567  -> +639171234567
     * +639171234567 -> +639171234567
     */
    public static function normalize(?string $phoneNumber): ?string
    {
        if ($phoneNumber === null) {
            return null;
        }

        $normalized = preg_replace(
            '/[\s\-\(\)]+/',
            '',
            trim($phoneNumber)
        );

        if ($normalized === null || $normalized === '') {
            return $normalized;
        }

        if (str_starts_with($normalized, '09')) {
            return '+63'.substr($normalized, 1);
        }

        if (str_starts_with($normalized, '639')) {
            return '+'.$normalized;
        }

        return $normalized;
    }
}