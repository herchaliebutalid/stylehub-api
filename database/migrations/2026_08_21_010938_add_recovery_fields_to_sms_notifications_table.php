<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'sms_notifications',
            function (Blueprint $table): void {
                /*
                |--------------------------------------------------------------------------
                | Idempotency / Duplicate Protection
                |--------------------------------------------------------------------------
                |
                | Example reminder key:
                |
                | appointment-reminder:51:20260824:130000
                |
                | A nullable UNIQUE column lets ordinary SMS records remain
                | unaffected while reminder notifications can be deduplicated.
                |
                */

                $table->string(
                    'dedupe_key',
                    191
                )
                    ->nullable()
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Failure Classification
                |--------------------------------------------------------------------------
                |
                | definite:
                |     safe for an intentional manual retry
                |
                | uncertain:
                |     must not be automatically resent
                |
                */

                $table->string(
                    'failure_kind',
                    32
                )
                    ->nullable()
                    ->index();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'sms_notifications',
            function (Blueprint $table): void {
                $table->dropUnique([
                    'dedupe_key',
                ]);

                $table->dropIndex([
                    'failure_kind',
                ]);

                $table->dropColumn([
                    'dedupe_key',
                    'failure_kind',
                ]);
            }
        );
    }
};