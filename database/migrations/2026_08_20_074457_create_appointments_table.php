<?php

use App\Enums\AppointmentStatus;
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
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $table->foreignId('customer_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Shop
            |--------------------------------------------------------------------------
            */

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Service
            |--------------------------------------------------------------------------
            |
            | The relationship remains linked to the original service.
            | Services use soft deletion, so historical appointments
            | remain valid even if a service is later retired.
            |
            */

            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Scheduled Date and Time
            |--------------------------------------------------------------------------
            |
            | Date and time are intentionally separated because StyleHub's
            | weekly operating schedule is also represented by weekday +
            | opening/closing TIME values.
            |
            */

            $table->date('appointment_date')
                ->index();

            $table->time('starts_at');

            $table->time('ends_at');

            /*
            |--------------------------------------------------------------------------
            | Service Snapshot
            |--------------------------------------------------------------------------
            |
            | Historical appointment information must not change when the
            | shop later edits its service name, price, or duration.
            |
            */

            $table->string(
                'service_name',
                120
            );

            $table->decimal(
                'price_snapshot',
                10,
                2
            );

            $table->unsignedSmallInteger(
                'duration_minutes_snapshot'
            );

            /*
            |--------------------------------------------------------------------------
            | Appointment State
            |--------------------------------------------------------------------------
            */

            $table->string(
                'status',
                32
            )
                ->default(
                    AppointmentStatus::Pending->value
                )
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Customer Notes
            |--------------------------------------------------------------------------
            */

            $table->text('notes')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Cancellation / Decline Metadata
            |--------------------------------------------------------------------------
            */

            $table->text(
                'cancellation_reason'
            )->nullable();

            $table->foreignId(
                'cancelled_by'
            )
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp(
                'cancelled_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */

            $table->timestamp(
                'completed_at'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit Timestamps
            |--------------------------------------------------------------------------
            |
            | Appointments intentionally do not use soft deletion.
            | Historical bookings should normally change status rather
            | than disappear from the system.
            |
            */

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Booking / Overlap Query Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'shop_id',
                'appointment_date',
                'status',
            ]);

            $table->index([
                'shop_id',
                'appointment_date',
                'starts_at',
                'ends_at',
            ]);

            $table->index([
                'customer_id',
                'appointment_date',
            ]);

            $table->index([
                'service_id',
                'appointment_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'appointments'
        );
    }
};