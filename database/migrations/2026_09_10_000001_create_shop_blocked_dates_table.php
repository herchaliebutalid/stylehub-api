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
            'shop_blocked_dates',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Shop Ownership
                |--------------------------------------------------------------------------
                |
                | A blocked date always belongs to one shop. Ownership is resolved from
                | the authenticated Shop Owner and must never be accepted from the client.
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
                | Calendar Closure
                |--------------------------------------------------------------------------
                |
                | The whole calendar date is unavailable for new appointments regardless
                | of the shop's normal weekly opening schedule.
                |
                */

                $table->date(
                    'blocked_date'
                );

                $table->string(
                    'reason',
                    255
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Constraints
                |--------------------------------------------------------------------------
                |
                | The same shop cannot create two closure records for the same date.
                |
                */

                $table->unique(
                    [
                        'shop_id',
                        'blocked_date',
                    ],
                    'shop_blocked_dates_shop_date_unique'
                );

                $table->index(
                    'blocked_date',
                    'shop_blocked_dates_date_index'
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
            'shop_blocked_dates'
        );
    }
};