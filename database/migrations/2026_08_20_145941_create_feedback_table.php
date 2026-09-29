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
            'feedback',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Completed Appointment Source
                |--------------------------------------------------------------------------
                |
                | A review originates from one real StyleHub appointment.
                |
                | unique() is critical:
                |
                | one appointment
                |      ↓
                | maximum one feedback/rating
                |
                */

                $table->foreignId(
                    'appointment_id'
                )
                    ->unique()
                    ->constrained(
                        'appointments'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Reviewer
                |--------------------------------------------------------------------------
                |
                | customer_id is assigned by trusted backend code later.
                | It is never accepted blindly from the mobile client.
                |
                */

                $table->foreignId(
                    'customer_id'
                )
                    ->constrained(
                        'users'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Reviewed Shop
                |--------------------------------------------------------------------------
                |
                | shop_id is derived from the completed appointment.
                |
                */

                $table->foreignId(
                    'shop_id'
                )
                    ->constrained(
                        'shops'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Rating
                |--------------------------------------------------------------------------
                |
                | 1 = lowest
                | 5 = highest
                |
                */

                $table->unsignedTinyInteger(
                    'rating'
                );

                /*
                |--------------------------------------------------------------------------
                | Optional Written Feedback
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'comment'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Timestamps
                |--------------------------------------------------------------------------
                */

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Query Indexes
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'shop_id',
                    'rating',
                ]);

                $table->index([
                    'customer_id',
                    'created_at',
                ]);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'feedback'
        );
    }
};