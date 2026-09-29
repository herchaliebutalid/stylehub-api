<?php

namespace App\Models;

use App\Enums\PromoDiscountType;
use App\Enums\ShopApprovalStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * Customer/shop-owner editable promotion information.
     *
     * shop_id and service_id are deliberately excluded because ownership
     * and service targeting must be assigned by trusted backend logic.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' =>
                PromoDiscountType::class,

            'discount_value' =>
                'decimal:2',

            'starts_at' =>
                'immutable_datetime',

            'ends_at' =>
                'immutable_datetime',

            'is_active' =>
                'boolean',
        ];
    }

    /**
     * Shop that owns this promotion.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Optional service targeted by this promotion.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }

    /**
     * Restrict a query to promotions that are active at the
     * current application time.
     */
    public function scopeCurrentlyActive(
        Builder $query
    ): Builder {
        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'UTC'
            )
        );

        return $query
            ->where(
                'is_active',
                true
            )
            ->where(
                'starts_at',
                '<=',
                $now
            )
            ->where(
                'ends_at',
                '>=',
                $now
            );
    }

    /**
     * Restrict promotions to records that customers are
     * actually allowed to discover.
     *
     * Requirements:
     *
     * - Promo itself is not soft deleted.
     * - Shop is approved and not soft deleted.
     * - Shop owner is active and still has shop_owner role.
     * - Shop-wide promotions are allowed.
     * - Service-specific promotions require an active,
     *   non-soft-deleted service.
     */
    public function scopeVisibleToCustomers(
        Builder $query
    ): Builder {
        return $query
            ->whereHas(
                'shop',
                function (
                    Builder $shopQuery
                ): void {
                    $shopQuery
                        ->where(
                            'approval_status',
                            ShopApprovalStatus::Approved
                                ->value
                        )
                        ->whereHas(
                            'owner',
                            function (
                                Builder $ownerQuery
                            ): void {
                                $ownerQuery
                                    ->where(
                                        'role',
                                        UserRole::ShopOwner
                                            ->value
                                    )
                                    ->where(
                                        'status',
                                        UserStatus::Active
                                            ->value
                                    );
                            }
                        );
                }
            )
            ->where(
                function (
                    Builder $promoQuery
                ): void {
                    $promoQuery
                        ->whereNull(
                            'service_id'
                        )
                        ->orWhereHas(
                            'service',
                            function (
                                Builder $serviceQuery
                            ): void {
                                $serviceQuery->where(
                                    'is_active',
                                    true
                                );
                            }
                        );
                }
            );
    }

    /**
     * Determine whether this promotion applies to the whole shop.
     */
    public function isShopWide(): bool
    {
        return $this->service_id === null;
    }

    /**
     * Determine whether this promotion targets one service.
     */
    public function isServiceSpecific(): bool
    {
        return $this->service_id !== null;
    }

    /**
     * Determine whether the promotion is manually enabled.
     */
    public function isEnabled(): bool
    {
        return $this->is_active;
    }

    /**
     * Determine whether this promotion is active right now.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (
            $this->starts_at === null
            || $this->ends_at === null
        ) {
            return false;
        }

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'UTC'
            )
        );

        return $now->greaterThanOrEqualTo(
            $this->starts_at
        )
            && $now->lessThanOrEqualTo(
                $this->ends_at
            );
    }

    /**
     * Determine whether this promotion has not started yet.
     */
    public function isUpcoming(): bool
    {
        if ($this->starts_at === null) {
            return false;
        }

        return CarbonImmutable::now(
            config(
                'app.timezone',
                'UTC'
            )
        )->lessThan(
            $this->starts_at
        );
    }

    /**
     * Determine whether this promotion has already ended.
     */
    public function hasEnded(): bool
    {
        if ($this->ends_at === null) {
            return false;
        }

        return CarbonImmutable::now(
            config(
                'app.timezone',
                'UTC'
            )
        )->greaterThan(
            $this->ends_at
        );
    }
}