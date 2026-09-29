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
        Schema::create('services', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Shop Ownership
            |--------------------------------------------------------------------------
            |
            | Every service belongs to exactly one shop.
            |
            */

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Service Information
            |--------------------------------------------------------------------------
            */

            $table->string('name', 120);

            $table->text('description')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Pricing
            |--------------------------------------------------------------------------
            |
            | DECIMAL is used instead of FLOAT to avoid floating-point
            | rounding problems with money.
            |
            */

            $table->decimal(
                'price',
                10,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Duration
            |--------------------------------------------------------------------------
            |
            | Stored in minutes so scheduling logic can reliably calculate
            | appointment time blocks later.
            |
            */

            $table->unsignedSmallInteger(
                'duration_minutes'
            );

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            |
            | An inactive service remains in the database for historical
            | records but should not be bookable by customers.
            |
            */

            $table->boolean('is_active')
                ->default(true)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Audit / Soft Delete
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'shop_id',
                'is_active',
            ]);

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};