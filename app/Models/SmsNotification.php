<?php

namespace App\Models;

use App\Enums\SmsFailureKind;
use App\Enums\SmsNotificationStatus;
use App\Enums\SmsNotificationType;
use Database\Factories\SmsNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsNotification extends Model
{
    /** @use HasFactory<SmsNotificationFactory> */
    use HasFactory;

    /**
     * Only ordinary notification content may be mass assigned.
     *
     * Ownership, delivery state, deduplication, attempts and
     * provider metadata are deliberately protected.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'recipient',
        'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' =>
                SmsNotificationType::class,

            'status' =>
                SmsNotificationStatus::class,

            'failure_kind' =>
                SmsFailureKind::class,

            'attempts' =>
                'integer',

            'last_attempt_at' =>
                'datetime',

            'accepted_at' =>
                'datetime',

            'failed_at' =>
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

    public function isPending(): bool
    {
        return $this->status
            === SmsNotificationStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status
            === SmsNotificationStatus::Processing;
    }

    public function isAccepted(): bool
    {
        return $this->status
            === SmsNotificationStatus::Accepted;
    }

    public function hasFailed(): bool
    {
        return $this->status
            === SmsNotificationStatus::Failed;
    }

    /**
     * Manual retry is allowed only when:
     *
     * - the notification actually failed; and
     * - we know that retrying cannot reasonably duplicate
     *   an already accepted message.
     */
    public function isSafeToRetry(): bool
    {
        return $this->status
            === SmsNotificationStatus::Failed
            && $this->failure_kind
            === SmsFailureKind::Definite;
    }
}