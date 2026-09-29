<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Database\Factories\ShopBreakPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopBreakPeriod extends Model
{
    /** @use HasFactory<ShopBreakPeriodFactory> */
    use HasFactory;

    /**
     * Only owner-editable break fields are mass assignable.
     *
     * shop_id is deliberately excluded because ownership is always resolved
     * through the authenticated Shop Owner's shop relationship.
     *
     * @var list<string>
     */
    protected $fillable = [
        'day_of_week',
        'starts_at',
        'ends_at',
        'label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
        ];
    }

    /**
     * Shop that owns this recurring break period.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Stable weekday/time ordering for management screens.
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'day_of_week'
            )
            ->orderBy(
                'starts_at'
            )
            ->orderBy(
                'ends_at'
            )
            ->orderBy(
                'id'
            );
    }

    /**
     * Normalize start times before storage.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed => $this->normalizeTime(
                $value
            ),
        );
    }

    /**
     * Normalize end times before storage.
     */
    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed => $this->normalizeTime(
                $value
            ),
        );
    }

    /**
     * Normalize HH:MM API input into the database's HH:MM:SS format.
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

        $value = trim(
            $value
        );

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