<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopBookingRule extends Model
{
    public const MIN_SLOT_INTERVAL_MINUTES = 5;

    public const MAX_SLOT_INTERVAL_MINUTES = 60;

    public const MIN_LEAD_TIME_MINUTES = 0;

    public const MAX_LEAD_TIME_MINUTES = 1440;

    public const MIN_BOOKING_WINDOW_DAYS = 1;

    public const MAX_BOOKING_WINDOW_DAYS = 365;

    public const MIN_CUTOFF_MINUTES = 0;

    public const MAX_CUTOFF_MINUTES = 10080;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slot_interval_minutes',
        'minimum_lead_time_minutes',
        'booking_window_days',
        'cancellation_cutoff_minutes',
        'reschedule_cutoff_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_interval_minutes' =>
                'integer',

            'minimum_lead_time_minutes' =>
                'integer',

            'booking_window_days' =>
                'integer',

            'cancellation_cutoff_minutes' =>
                'integer',

            'reschedule_cutoff_minutes' =>
                'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }
}