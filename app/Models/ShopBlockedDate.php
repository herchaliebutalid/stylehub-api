<?php

namespace App\Models;

use Database\Factories\ShopBlockedDateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopBlockedDate extends Model
{
    /** @use HasFactory<ShopBlockedDateFactory> */
    use HasFactory;

    /**
     * Only owner-editable closure fields are mass assignable.
     *
     * shop_id is deliberately excluded because ownership must come from the
     * authenticated Shop Owner's shop relationship.
     *
     * @var list<string>
     */
    protected $fillable = [
        'blocked_date',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocked_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Shop that owns this blocked calendar date.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Stable chronological ordering for management screens.
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'blocked_date'
            )
            ->orderBy(
                'id'
            );
    }
}