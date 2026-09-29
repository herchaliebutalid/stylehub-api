<?php

namespace App\Models;

use App\Enums\RescheduleRequestStatus;
use App\Observers\RescheduleRequestObserver;
use Carbon\CarbonImmutable;
use Database\Factories\RescheduleRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([
    RescheduleRequestObserver::class,
])]
class RescheduleRequest extends Model
{
    /** @use HasFactory<RescheduleRequestFactory> */
    use HasFactory;

    /**
     * Only ordinary customer-requested information is mass assignable.
     *
     * appointment_id, status and review metadata remain protected.
     *
     * @var list<string>
     */
    protected $fillable = [
        'requested_date',
        'requested_starts_at',
        'customer_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' =>
                RescheduleRequestStatus::class,

            'reviewed_at' =>
                'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(
            Appointment::class
        );
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reviewed_by'
        );
    }

    protected function requestedDate(): Attribute
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

    protected function requestedStartsAt(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): mixed =>
                $this->normalizeTime(
                    $value
                ),
        );
    }

    protected function requestedEndsAt(): Attribute
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
            === RescheduleRequestStatus::Pending;
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