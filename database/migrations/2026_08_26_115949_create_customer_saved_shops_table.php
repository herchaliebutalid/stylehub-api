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
            'customer_saved_shops',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('customer_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table
                    ->foreignId('shop_id')
                    ->constrained('shops')
                    ->cascadeOnDelete();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Prevent Duplicate Saves
                |--------------------------------------------------------------------------
                |
                | One customer may save a shop only once.
                |
                */

                $table->unique([
                    'customer_id',
                    'shop_id',
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
            'customer_saved_shops'
        );
    }
};