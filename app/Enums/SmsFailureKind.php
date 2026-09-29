<?php

namespace App\Enums;

enum SmsFailureKind: string
{
    /*
    |--------------------------------------------------------------------------
    | Definite Failure
    |--------------------------------------------------------------------------
    |
    | We know the SMS was not successfully accepted for delivery.
    |
    | A deliberate manual retry is therefore considered safe.
    |
    */

    case Definite = 'definite';

    /*
    |--------------------------------------------------------------------------
    | Uncertain Failure
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | - network timeout;
    | - HTTP 5xx after request submission;
    | - malformed success response.
    |
    | Semaphore may already have accepted the message.
    |
    | Retrying automatically could therefore send duplicates.
    |
    */

    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Definite =>
                'Definite Failure',

            self::Uncertain =>
                'Uncertain Delivery Outcome',
        };
    }

    public function isSafeToRetry(): bool
    {
        return $this === self::Definite;
    }
}