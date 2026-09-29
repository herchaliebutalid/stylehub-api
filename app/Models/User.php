<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * Attributes that may be mass assigned.
     *
     * Security note:
     * role, status and phone_verified_at are intentionally NOT fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone_number',
        'password',
    ];

    /**
     * Attributes hidden from arrays / JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' =>
                'datetime',

            'phone_verified_at' =>
                'datetime',

            'last_login_at' =>
                'datetime',

            'password' =>
                'hashed',

            'role' =>
                UserRole::class,

            'status' =>
                UserStatus::class,
        ];
    }

    /**
     * Shop owned by this account.
     */
    public function shop(): HasOne
    {
        return $this->hasOne(
            Shop::class,
            'owner_id'
        );
    }

    /**
     * Appointments booked by this user as a customer.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(
            Appointment::class,
            'customer_id'
        );
    }

    /**
     * Persistent in-app notifications addressed to this account.
     *
     * This relationship is intentionally named inAppNotifications() so it does
     * not collide with Laravel's Notifiable trait database-notification API.
     */
    public function inAppNotifications(): HasMany
    {
        return $this->hasMany(
            InAppNotification::class
        );
    }

    /**
     * Current phone verification code.
     */
    public function phoneVerificationCode(): HasOne
    {
        return $this->hasOne(
            PhoneVerificationCode::class
        );
    }

    /**
     * Return the user's complete display name.
     */
    public function getFullNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])
            ->filter()
            ->implode(' ');
    }

    public function isCustomer(): bool
    {
        return $this->role
            === UserRole::Customer;
    }

    public function isShopOwner(): bool
    {
        return $this->role
            === UserRole::ShopOwner;
    }

    public function isAdmin(): bool
    {
        return $this->role
            === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->status
            === UserStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status
            === UserStatus::Suspended;
    }

    /**
     * Determine whether this account has proven ownership
     * of its registered mobile number.
     */
    public function hasVerifiedPhoneNumber(): bool
    {
        return $this->phone_verified_at
            !== null;
    }
}