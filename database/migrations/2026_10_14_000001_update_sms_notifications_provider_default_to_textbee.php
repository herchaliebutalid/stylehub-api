
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set TextBee as the default SMS provider.
     *
     * Existing SMS records will not be changed.
     */
    public function up(): void
    {
        Schema::table(
            'sms_notifications',
            function (Blueprint $table): void {
                $table->string(
                    'provider',
                    32
                )
                    ->default('textbee')
                    ->change();
            }
        );
    }

    /**
     * Restore the previous database default
     * if this migration is rolled back.
     */
    public function down(): void
    {
        Schema::table(
            'sms_notifications',
            function (Blueprint $table): void {
                $table->string(
                    'provider',
                    32
                )
                    ->default('semaphore')
                    ->change();
            }
        );
    }
};
