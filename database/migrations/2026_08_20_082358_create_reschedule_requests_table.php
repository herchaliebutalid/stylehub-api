<?php

use App\Enums\RescheduleRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reschedule_requests',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Original Appointment
                |--------------------------------------------------------------------------
                |
                | Ownership is derived from the appointment itself rather than
                | duplicating customer_id and shop_id in this table.
                |
                */

                $table->foreignId('appointment_id')
                    ->constrained('appointments')
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Requested New Slot
                |--------------------------------------------------------------------------
                */

                $table->date('requested_date')
                    ->index();

                $table->time(
                    'requested_starts_at'
                );

                $table->time(
                    'requested_ends_at'
                );

                /*
                |--------------------------------------------------------------------------
                | Request State
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'status',
                    32
                )
                    ->default(
                        RescheduleRequestStatus::Pending->value
                    )
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Customer Reason
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'customer_reason'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Shop Review Metadata
                |--------------------------------------------------------------------------
                |
                | These fields will be used by Step 11E.
                |
                */

                $table->text(
                    'rejection_reason'
                )->nullable();

                $table->foreignId(
                    'reviewed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Indexes
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'appointment_id',
                    'status',
                ]);

                $table->index([
                    'requested_date',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'reschedule_requests'
        );
    }
};