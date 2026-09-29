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
        Schema::create('shop_schedules', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Shop
            |--------------------------------------------------------------------------
            |
            | Every operating schedule belongs to exactly one shop.
            |
            */

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Day
            |--------------------------------------------------------------------------
            |
            | ISO-8601 numbering:
            |
            | 1 = Monday
            | 2 = Tuesday
            | ...
            | 7 = Sunday
            |
            */

            $table->unsignedTinyInteger(
                'day_of_week'
            );

            /*
            |--------------------------------------------------------------------------
            | Business Hours
            |--------------------------------------------------------------------------
            |
            | A closed day has:
            |
            | is_open   = false
            | opens_at  = null
            | closes_at = null
            |
            */

            $table->boolean('is_open')
                ->default(false);

            $table->time('opens_at')
                ->nullable();

            $table->time('closes_at')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Constraints
            |--------------------------------------------------------------------------
            |
            | A shop may have only one operating schedule for each weekday.
            |
            */

            $table->unique([
                'shop_id',
                'day_of_week',
            ]);

            $table->index([
                'shop_id',
                'is_open',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'shop_schedules'
        );
    }
};