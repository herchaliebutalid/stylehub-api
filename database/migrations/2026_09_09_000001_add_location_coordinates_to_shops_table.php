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
        Schema::table('shops', function (Blueprint $table): void {
            /*
            |--------------------------------------------------------------------------
            | Exact Map Coordinates
            |--------------------------------------------------------------------------
            |
            | Existing shops remain valid because both values are nullable.
            |
            | Seven decimal places provide sub-meter coordinate precision, which is
            | more than sufficient for a shop pin while remaining deterministic in
            | MySQL and SQLite tests.
            |
            */

            $table->decimal(
                'latitude',
                10,
                7
            )->nullable();

            $table->decimal(
                'longitude',
                10,
                7
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | Nearby-Shop Query Foundation
            |--------------------------------------------------------------------------
            |
            | Feature 4 will later use a bounded latitude/longitude query before
            | calculating precise customer-to-shop distances.
            |
            */

            $table->index([
                'latitude',
                'longitude',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex([
                'latitude',
                'longitude',
            ]);

            $table->dropColumn([
                'latitude',
                'longitude',
            ]);
        });
    }
};