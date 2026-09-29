<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'duration_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' =>
                'decimal:2',

            'duration_minutes' =>
                'integer',

            'is_active' =>
                'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(
            Appointment::class
        );
    }

    /**
     * Promotions targeting this service.
     */
    public function promos(): HasMany
    {
        return $this->hasMany(
            Promo::class
        );
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isInactive(): bool
    {
        return ! $this->is_active;
    }

    /**
     * Determine whether the service currently has a stored display image.
     */
    public function hasImage(): bool
    {
        return is_string(
            $this->image_path
        )
            && trim(
                $this->image_path
            ) !== '';
    }
}