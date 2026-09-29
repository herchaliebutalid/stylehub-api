<?php

namespace App\Enums;

enum ShopAvailabilityStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case FullyBooked = 'fully_booked';
    case AcceptingWalkIns = 'accepting_walk_ins';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::FullyBooked => 'Fully Booked',
            self::AcceptingWalkIns => 'Accepting Walk-ins',
        };
    }

    /**
     * Determine whether customers may submit appointment bookings
     * while the shop is in this availability state.
     */
    public function acceptsAppointments(): bool
    {
        return match ($this) {
            self::Open,
            self::AcceptingWalkIns => true,

            self::Closed,
            self::FullyBooked => false,
        };
    }
}