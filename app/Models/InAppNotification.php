<?php

namespace App\Models;

use App\Enums\InAppNotificationType;
use Database\Factories\InAppNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotification extends Model
{
    /** @use HasFactory<InAppNotificationFactory> */
    use HasFactory;

    /**
     * Only user-visible content may be mass assigned.
     *
     * Recipient ownership, source relationships, deduplication and read state
     * are assigned only by trusted backend code.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'title',
        'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' =>
                InAppNotificationType::class,

            'read_at' =>
                'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(
            Appointment::class
        );
    }

    public function rescheduleRequest(): BelongsTo
    {
        return $this->belongsTo(
            RescheduleRequest::class
        );
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(
            Feedback::class
        );
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(
            Promo::class
        );
    }

    /**
     * Restrict a query to unread notifications.
     */
    public function scopeUnread(
        Builder $query
    ): Builder {
        return $query->whereNull(
            'read_at'
        );
    }

    public function isRead(): bool
    {
        return $this->read_at
            !== null;
    }

    public function isUnread(): bool
    {
        return ! $this->isRead();
    }
}