<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
            self::Declined => 'Declined',
        };
    }

    /**
     * Determine whether an appointment in this status
     * occupies its scheduled time slot.
     */
    public function blocksTimeSlot(): bool
    {
        return match ($this) {
            self::Pending,
            self::Confirmed => true,

            self::Completed,
            self::NoShow,
            self::Cancelled,
            self::Declined => false,
        };
    }

    /**
     * Determine whether no further normal appointment workflow
     * should occur after reaching this status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::NoShow,
            self::Cancelled,
            self::Declined => true,

            self::Pending,
            self::Confirmed => false,
        };
    }
}