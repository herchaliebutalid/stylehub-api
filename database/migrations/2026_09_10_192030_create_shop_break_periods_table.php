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
        Schema::create('shop_break_periods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('shops')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('day_of_week');

            $table->time('starts_at');

            $table->time('ends_at');

            $table->string('label', 120)
                ->nullable();

            $table->timestamps();

            $table->unique(
                [
                    'shop_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                ],
                'shop_break_periods_exact_unique'
            );

            $table->index(
                [
                    'shop_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                ],
                'shop_break_periods_lookup_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_break_periods');
    }
};