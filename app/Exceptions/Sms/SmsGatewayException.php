<?php

namespace App\Exceptions\Sms;

use App\Enums\SmsFailureKind;
use RuntimeException;
use Throwable;

class SmsGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly SmsFailureKind $failureKind,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message:
                $message,

            code:
                0,

            previous:
                $previous,
        );
    }

    public static function definite(
        string $message,
        ?Throwable $previous = null
    ): self {
        return new self(
            message:
                $message,

            failureKind:
                SmsFailureKind::Definite,

            previous:
                $previous,
        );
    }

    public static function uncertain(
        string $message,
        ?Throwable $previous = null
    ): self {
        return new self(
            message:
                $message,

            failureKind:
                SmsFailureKind::Uncertain,

            previous:
                $previous,
        );
    }

    public function isSafeToRetry(): bool
    {
        return $this->failureKind
            ->isSafeToRetry();
    }
}