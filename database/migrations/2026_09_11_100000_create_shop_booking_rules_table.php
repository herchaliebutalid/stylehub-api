<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'shop_booking_rules',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Shop Ownership
                |--------------------------------------------------------------------------
                |
                | One rule set belongs to exactly one shop. shop_id is always derived
                | from the authenticated Shop Owner and is never accepted from clients.
                |
                */

                $table->foreignId(
                    'shop_id'
                )
                    ->constrained(
                        'shops'
                    )
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Booking Grid / Advance Rules
                |--------------------------------------------------------------------------
                */

                $table->unsignedTinyInteger(
                    'slot_interval_minutes'
                )->default(
                    15
                );

                $table->unsignedSmallInteger(
                    'minimum_lead_time_minutes'
                )->default(
                    0
                );

                $table->unsignedSmallInteger(
                    'booking_window_days'
                )->default(
                    60
                );

                /*
                |--------------------------------------------------------------------------
                | Customer Change Cutoffs
                |--------------------------------------------------------------------------
                |
                | A zero-minute cutoff preserves current behavior: customers may cancel
                | or request a reschedule until the appointment starts.
                |
                */

                $table->unsignedSmallInteger(
                    'cancellation_cutoff_minutes'
                )->default(
                    0
                );

                $table->unsignedSmallInteger(
                    'reschedule_cutoff_minutes'
                )->default(
                    0
                );

                $table->timestamps();

                $table->unique(
                    'shop_id',
                    'shop_booking_rules_shop_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'shop_booking_rules'
        );
    }
};