<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Observers\FeedbackObserver;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([
    FeedbackObserver::class,
])]
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    /**
     * StyleHub uses the singular table name "feedback".
     */
    protected $table = 'feedback';

    /**
     * Only ordinary review content may be mass assigned.
     *
     * appointment_id, customer_id and shop_id are assigned only
     * by trusted backend logic.
     *
     * @var list<string>
     */
    protected $fillable = [
        'rating',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' =>
                'integer',
        ];
    }

    /**
     * Appointment that authorized this feedback.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(
            Appointment::class
        );
    }

    /**
     * Customer who submitted this feedback.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'customer_id'
        );
    }

    /**
     * Shop being reviewed.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(
            Shop::class
        );
    }

    /**
     * Restrict feedback to records that can still be verified
     * against a completed StyleHub appointment.
     *
     * We additionally verify that the duplicated ownership fields
     * match the original appointment.
     */
    public function scopeVerified(
        Builder $query
    ): Builder {
        return $query->whereHas(
            'appointment',
            function (
                Builder $appointmentQuery
            ): void {
                $appointmentQuery
                    ->where(
                        'status',
                        AppointmentStatus::Completed
                            ->value
                    )
                    ->whereColumn(
                        'appointments.customer_id',
                        'feedback.customer_id'
                    )
                    ->whereColumn(
                        'appointments.shop_id',
                        'feedback.shop_id'
                    );
            }
        );
    }

    /**
     * Determine whether this rating is within StyleHub's
     * valid one-to-five range.
     */
    public function hasValidRating(): bool
    {
        return $this->rating >= 1
            && $this->rating <= 5;
    }
}