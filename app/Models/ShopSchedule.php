<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\ShopScheduleFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopSchedule extends Model
{
    /** @use HasFactory<ShopScheduleFactory> */
    use HasFactory;

    /**
     * Attributes that may safely be mass assigned.
     *
     * shop_id is deliberately excluded because the authenticated
     * shop-owner relationship must determine ownership.
     *
     * @var list<string>
     */
    protected $fillable = [
        'day_of_week',
        'is_open',
        'opens_at',
        'closes_at',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'is_open' => 'boolean',
        ];
    }

    /**
     * Shop that owns this operating schedule.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Normalize opening times before they are stored.
     *
     * API requests accept HH:MM for convenience, while the database
     * stores a consistent HH:MM:SS representation.
     */
    protected function opensAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed => $this->normalizeTime(
                $value
            ),
        );
    }

    /**
     * Normalize closing times before they are stored.
     */
    protected function closesAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed => $this->normalizeTime(
                $value
            ),
        );
    }

    /**
     * Determine whether this shop operates on this day.
     */
    public function isOpen(): bool
    {
        return $this->is_open;
    }

    /**
     * Determine whether the shop is closed on this day.
     */
    public function isClosed(): bool
    {
        return ! $this->is_open;
    }

    /**
     * Determine whether valid operating hours are present.
     */
    public function hasOperatingHours(): bool
    {
        return $this->is_open
            && $this->opens_at !== null
            && $this->closes_at !== null;
    }

    /**
     * Normalize a time value to HH:MM:SS.
     */
    private function normalizeTime(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^\d{2}:\d{2}$/',
                $value
            ) === 1
        ) {
            return $value.':00';
        }

        return $value;
    }
}