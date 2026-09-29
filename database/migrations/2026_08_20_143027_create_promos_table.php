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
        Schema::create('promos', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Shop Ownership
            |--------------------------------------------------------------------------
            |
            | Every promotion belongs to exactly one shop.
            |
            */

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Optional Service Target
            |--------------------------------------------------------------------------
            |
            | service_id = null
            |     → shop-wide promotion
            |
            | service_id = value
            |     → promotion applies to one specific service
            |
            | Services are already soft deleted in StyleHub.
            |
            */

            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Promotion Information
            |--------------------------------------------------------------------------
            */

            $table->string(
                'title',
                150
            );

            $table->text(
                'description'
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Discount
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | percentage + 10.00
            |     = 10% discount
            |
            | fixed_amount + 100.00
            |     = ₱100 discount
            |
            */

            $table->string(
                'discount_type',
                32
            );

            $table->decimal(
                'discount_value',
                10,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Promotion Period
            |--------------------------------------------------------------------------
            */

            $table->timestamp(
                'starts_at'
            );

            $table->timestamp(
                'ends_at'
            );

            /*
            |--------------------------------------------------------------------------
            | Manual Activation
            |--------------------------------------------------------------------------
            |
            | A promo must satisfy BOTH:
            |
            | is_active = true
            | current time is inside starts_at → ends_at
            |
            */

            $table->boolean(
                'is_active'
            )
                ->default(true)
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Audit / History
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Query Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'shop_id',
                'is_active',
            ]);

            $table->index([
                'shop_id',
                'starts_at',
                'ends_at',
            ]);

            $table->index([
                'service_id',
                'is_active',
            ]);

            $table->index(
                'created_at'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'promos'
        );
    }
};