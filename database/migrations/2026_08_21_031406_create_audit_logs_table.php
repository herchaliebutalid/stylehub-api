<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'audit_logs',
            function (Blueprint $table): void {
                /*
                |--------------------------------------------------------------------------
                | Storage
                |--------------------------------------------------------------------------
                */

                $table->engine =
                    'InnoDB';

                $table->charset =
                    'utf8mb4';

                $table->collation =
                    'utf8mb4_unicode_ci';

                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Actor
                |--------------------------------------------------------------------------
                |
                | If a user is eventually hard-deleted, we preserve the audit
                | entry and simply remove the relational actor reference.
                |
                */

                $table->foreignId(
                    'actor_id'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                /*
                 * Snapshot of the actor's role at event time.
                 */
                $table->string(
                    'actor_role',
                    32
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Event
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'event',
                    100
                )->index();

                /*
                |--------------------------------------------------------------------------
                | Audited Resource
                |--------------------------------------------------------------------------
                |
                | Examples:
                |
                | Shop
                | Service
                | Appointment
                | RescheduleRequest
                | Promo
                | Feedback
                |
                */

                $table->nullableMorphs(
                    'auditable'
                );

                /*
                |--------------------------------------------------------------------------
                | Request Context
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'ip_address',
                    45
                )->nullable();

                $table->string(
                    'user_agent',
                    500
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Safe Metadata
                |--------------------------------------------------------------------------
                |
                | Never store:
                |
                | passwords
                | API keys
                | authentication tokens
                | cookies
                | authorization headers
                |
                */

                $table->json(
                    'metadata'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Immutable Timestamp
                |--------------------------------------------------------------------------
                |
                | Audit entries don't use updated_at.
                |
                */

                $table->timestamp(
                    'created_at'
                )
                    ->useCurrent();

                /*
                |--------------------------------------------------------------------------
                | Query Indexes
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'actor_id',
                    'created_at',
                ]);

                $table->index([
                    'event',
                    'created_at',
                ]);

                $table->index(
                    'created_at'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'audit_logs'
        );
    }
};