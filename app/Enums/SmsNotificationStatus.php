<?php

namespace App\Enums;

enum SmsNotificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Accepted => 'Accepted by Provider',
            self::Failed => 'Failed',
            self::Skipped => 'Skipped',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Accepted,
            self::Failed,
            self::Skipped => true,

            self::Pending,
            self::Processing => false,
        };
    }
}