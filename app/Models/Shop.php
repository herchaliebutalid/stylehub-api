<?php

namespace App\Models;

use App\Enums\ShopAdministrativeStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * Application-level defaults.
     *
     * The database also defaults administrative_status to active,
     * but this model default ensures a newly created Shop already
     * has the correct value before it is refreshed from the database.
     *
     * This is important during Shop Owner registration because the
     * newly created Shop is immediately passed to ShopResource.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'administrative_status' =>
            ShopAdministrativeStatus::Active->value,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'description',
        'phone_number',
        'email',
        'address',
        'city',
        'province',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' =>
                ShopType::class,

            'availability_status' =>
                ShopAvailabilityStatus::class,

            'approval_status' =>
                ShopApprovalStatus::class,

            'administrative_status' =>
                ShopAdministrativeStatus::class,

            'latitude' =>
                'float',

            'longitude' =>
                'float',

            'reviewed_at' =>
                'datetime',

            'suspended_at' =>
                'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    /**
     * Administrator who applied the shop's current suspension.
     */
    public function suspender(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'suspended_by'
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(
            Service::class
        );
    }

    /**
     * Gallery images belonging to this shop.
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(
            ShopGalleryImage::class
        );
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(
            ShopSchedule::class
        );
    }

    /**
     * Calendar dates on which this shop does not accept appointments.
     */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(
            ShopBlockedDate::class
        );
    }

    /**
     * Recurring weekly time ranges during which this shop pauses bookings.
     */
    public function breakPeriods(): HasMany
    {
        return $this->hasMany(
            ShopBreakPeriod::class
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(
            Appointment::class
        );
    }

    public function promos(): HasMany
    {
        return $this->hasMany(
            Promo::class
        );
    }

    /**
     * Feedback submitted for this shop.
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(
            Feedback::class
        );
    }

    /**
     * Restrict a query to shops that customers are allowed to discover.
     *
     * Approval, independent shop administration state, and owner account
     * state are deliberately separate gates.
     */
    public function scopeVisibleToCustomers(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'approval_status',
                ShopApprovalStatus::Approved
                    ->value
            )
            ->where(
                'administrative_status',
                ShopAdministrativeStatus::Active
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

    /**
     * Restrict a query to shops with a complete map location.
     */
    public function scopeLocated(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull(
                'latitude'
            )
            ->whereNotNull(
                'longitude'
            );
    }

    public function isPending(): bool
    {
        return $this->approval_status
            === ShopApprovalStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->approval_status
            === ShopApprovalStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->approval_status
            === ShopApprovalStatus::Rejected;
    }

    public function isAdministrativelyActive(): bool
    {
        return $this->administrative_status
            === ShopAdministrativeStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->administrative_status
            === ShopAdministrativeStatus::Suspended;
    }

    public function isCustomerVisible(): bool
    {
        return $this->isApproved()
            && $this->isAdministrativelyActive();
    }

    /**
     * Determine whether the shop has a complete coordinate pair.
     */
    public function hasLocation(): bool
    {
        return $this->latitude !== null
            && $this->longitude !== null;
    }
}