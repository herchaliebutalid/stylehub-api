<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Audit Entries Have No updated_at
    |--------------------------------------------------------------------------
    */

    public const UPDATED_AT = null;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    |
    | Audit records must be created exclusively by trusted backend code.
    |
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
            'event' =>
                AuditEvent::class,

            'metadata' =>
                'array',

            'created_at' =>
                'immutable_datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Actor
    |--------------------------------------------------------------------------
    */

    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audited Resource
    |--------------------------------------------------------------------------
    */

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Application-Level Immutability
    |--------------------------------------------------------------------------
    |
    | Audit records must not be edited or deleted through normal Eloquent
    | model operations.
    |
    | This is defense in depth, not a claim that a database administrator
    | can never alter the underlying database.
    |
    */

    protected static function booted(): void
    {
        static::updating(
            function (): never {
                throw new LogicException(
                    'Audit logs are immutable and cannot be updated.'
                );
            }
        );

        static::deleting(
            function (): never {
                throw new LogicException(
                    'Audit logs are immutable and cannot be deleted.'
                );
            }
        );
    }
}