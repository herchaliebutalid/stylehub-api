<?php

use App\Enums\SmsNotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'sms_notifications',
            function (Blueprint $table): void {
                /*
                |--------------------------------------------------------------------------
                | Storage Engine
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
                | Recipient Account
                |--------------------------------------------------------------------------
                |
                | The account can later be soft deleted. We preserve the SMS
                | history even if the relationship eventually disappears.
                |
                */

                $table->foreignId(
                    'user_id'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Optional Appointment Context
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'appointment_id'
                )
                    ->nullable()
                    ->constrained(
                        'appointments'
                    )
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | SMS Classification
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'type',
                    64
                )->index();

                /*
                |--------------------------------------------------------------------------
                | Recipient Snapshot
                |--------------------------------------------------------------------------
                |
                | We snapshot the destination number used at send time.
                |
                | If the customer's profile number is changed later, this
                | record still shows where the original notification went.
                |
                */

                $table->string(
                    'recipient',
                    32
                );

                /*
                |--------------------------------------------------------------------------
                | Message Snapshot
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'message'
                );

                /*
                |--------------------------------------------------------------------------
                | Internal Delivery State
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'status',
                    32
                )
                    ->default(
                        SmsNotificationStatus::Pending
                            ->value
                    )
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Provider
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'provider',
                    32
                )
                    ->default(
                        'semaphore'
                    );

                /*
                |--------------------------------------------------------------------------
                | Provider Result
                |--------------------------------------------------------------------------
                |
                | We deliberately do NOT store the complete Semaphore response.
                | Their response may contain account/user information that
                | StyleHub does not need to retain.
                |
                */

                $table->string(
                    'provider_message_id',
                    100
                )
                    ->nullable()
                    ->index();

                $table->string(
                    'provider_status',
                    64
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Attempt Tracking
                |--------------------------------------------------------------------------
                */

                $table->unsignedSmallInteger(
                    'attempts'
                )
                    ->default(0);

                $table->timestamp(
                    'last_attempt_at'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Final State Timestamps
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'accepted_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Safe Error Information
                |--------------------------------------------------------------------------
                |
                | Never store API keys or full provider responses here.
                |
                */

                $table->string(
                    'last_error',
                    1000
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Query Indexes
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'user_id',
                    'created_at',
                ]);

                $table->index([
                    'appointment_id',
                    'type',
                ]);

                $table->index([
                    'status',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sms_notifications'
        );
    }
};