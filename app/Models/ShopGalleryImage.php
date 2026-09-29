<?php

namespace App\Models;

use Database\Factories\ShopGalleryImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopGalleryImage extends Model
{
    /** @use HasFactory<ShopGalleryImageFactory> */
    use HasFactory;

    /**
     * All gallery metadata is server-controlled.
     *
     * shop_id, image_path and position must never be accepted through ordinary
     * mass assignment from mobile request data.
     *
     * @var list<string>
     */
    protected $guarded = [
        '*',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' =>
                'integer',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Apply the stable gallery display order.
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'position'
            )
            ->orderBy(
                'id'
            );
    }
}