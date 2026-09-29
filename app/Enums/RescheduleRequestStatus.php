<?php

namespace App\Enums;

enum RescheduleRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved,
            self::Rejected,
            self::Withdrawn => true,

            self::Pending => false,
        };
    }
}
