<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'in_app_notifications',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Recipient
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'user_id'
                )
                    ->constrained(
                        'users'
                    )
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Notification Content
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'type',
                    64
                )->index();

                $table->string(
                    'title',
                    120
                );

                $table->text(
                    'message'
                );

                /*
                |--------------------------------------------------------------------------
                | Optional Source Records
                |--------------------------------------------------------------------------
                |
                | These allow the app to open the correct StyleHub screen later
                | without storing arbitrary client-controlled route strings.
                |
                */

                $table->foreignId(
                    'appointment_id'
                )
                    ->nullable()
                    ->constrained(
                        'appointments'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'reschedule_request_id'
                )
                    ->nullable()
                    ->constrained(
                        'reschedule_requests'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'feedback_id'
                )
                    ->nullable()
                    ->constrained(
                        'feedback'
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'promo_id'
                )
                    ->nullable()
                    ->constrained(
                        'promos'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Idempotency / Duplicate Protection
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'dedupe_key',
                    191
                )
                    ->nullable()
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Read State
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'read_at'
                )
                    ->nullable()
                    ->index();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Notification Center Query Indexes
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'user_id',
                        'created_at',
                    ],
                    'in_app_notifications_user_created_index'
                );

                $table->index(
                    [
                        'user_id',
                        'read_at',
                        'created_at',
                    ],
                    'in_app_notifications_user_read_created_index'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'in_app_notifications'
        );
    }
};