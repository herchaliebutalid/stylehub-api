<?php

namespace Tests\Unit\Support;

use App\Support\Sms\PhilippineSmsRecipient;
use PHPUnit\Framework\TestCase;

class PhilippineSmsRecipientTest extends TestCase
{
    public function test_local_mobile_number_is_preserved(): void
    {
        $this->assertSame(
            '09171234567',
            PhilippineSmsRecipient::normalize(
                '09171234567'
            )
        );
    }

    public function test_number_without_leading_zero_is_normalized(): void
    {
        $this->assertSame(
            '09171234567',
            PhilippineSmsRecipient::normalize(
                '9171234567'
            )
        );
    }

    public function test_country_code_number_is_normalized(): void
    {
        $this->assertSame(
            '09171234567',
            PhilippineSmsRecipient::normalize(
                '639171234567'
            )
        );
    }

    public function test_plus_country_code_number_is_normalized(): void
    {
        $this->assertSame(
            '09171234567',
            PhilippineSmsRecipient::normalize(
                '+639171234567'
            )
        );
    }

    public function test_formatted_mobile_number_is_normalized(): void
    {
        $this->assertSame(
            '09171234567',
            PhilippineSmsRecipient::normalize(
                '+63 917 123 4567'
            )
        );
    }

    public function test_invalid_numbers_are_rejected(): void
    {
        $invalid = [
            null,
            '',
            '12345',
            '0281234567',
            '0917ABC4567',
            '+1234567890',
        ];

        foreach (
            $invalid as $number
        ) {
            $this->assertNull(
                PhilippineSmsRecipient::normalize(
                    $number
                )
            );
        }
    }
}