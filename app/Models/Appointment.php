<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Observers\AppointmentObserver;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([
    AppointmentObserver::class,
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'appointment_date',
        'starts_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_snapshot' =>
                'decimal:2',

            'duration_minutes_snapshot' =>
                'integer',

            'status' =>
                AppointmentStatus::class,

            'cancelled_at' =>
                'datetime',

            'completed_at' =>
                'datetime',

            'no_show_at' =>
                'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'customer_id'
        );
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by'
        );
    }

    /**
     * Historical reschedule requests.
     */
    public function rescheduleRequests(): HasMany
    {
        return $this->hasMany(
            RescheduleRequest::class
        );
    }

    /**
     * SMS communication records connected to this appointment.
     */
    public function smsNotifications(): HasMany
    {
        return $this->hasMany(
            SmsNotification::class
        );
    }

    /**
     * A completed appointment can produce at most one feedback record.
     */
    public function feedback(): HasOne
    {
        return $this->hasOne(
            Feedback::class
        );
    }

    protected function appointmentDate(): Attribute
    {
        return Attribute::make(
            get: function (
                mixed $value
            ): ?CarbonImmutable {
                if ($value === null) {
                    return null;
                }

                return CarbonImmutable::parse(
                    $value
                )->startOfDay();
            },

            set: function (
                mixed $value
            ): mixed {
                if ($value === null) {
                    return null;
                }

                if (
                    $value
                    instanceof \DateTimeInterface
                ) {
                    return CarbonImmutable::parse(
                        $value
                    )->toDateString();
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

                return CarbonImmutable::parse(
                    $value
                )->toDateString();
            },
        );
    }

    protected function startsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed =>
                $this->normalizeTime(
                    $value
                ),
        );
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed =>
                $this->normalizeTime(
                    $value
                ),
        );
    }

    public function isPending(): bool
    {
        return $this->status
            === AppointmentStatus::Pending;
    }

    public function isConfirmed(): bool
    {
        return $this->status
            === AppointmentStatus::Confirmed;
    }

    public function isCompleted(): bool
    {
        return $this->status
            === AppointmentStatus::Completed;
    }

    public function isNoShow(): bool
    {
        return $this->status
            === AppointmentStatus::NoShow;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === AppointmentStatus::Cancelled;
    }

    public function isDeclined(): bool
    {
        return $this->status
            === AppointmentStatus::Declined;
    }

    public function blocksTimeSlot(): bool
    {
        return $this->status
            ->blocksTimeSlot();
    }

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