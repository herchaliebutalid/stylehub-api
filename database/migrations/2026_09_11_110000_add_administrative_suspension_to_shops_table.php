<?php

use App\Enums\ShopAdministrativeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'shops',
            function (Blueprint $table): void {
                /*
                |--------------------------------------------------------------------------
                | Independent Administrative Shop State
                |--------------------------------------------------------------------------
                |
                | This is intentionally separate from:
                |
                | - approval_status
                | - availability_status
                | - the Shop Owner's user account status
                |
                | Existing shops automatically remain active.
                |
                */

                $table->string(
                    'administrative_status',
                    32
                )
                    ->default(
                        ShopAdministrativeStatus::Active->value
                    )
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Current Suspension Information
                |--------------------------------------------------------------------------
                |
                | These fields are controlled only by trusted Administrator
                | workflows. They are never accepted through normal shop-owner
                | profile/update payloads.
                |
                */

                $table->foreignId(
                    'suspended_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'suspended_at'
                )
                    ->nullable();

                $table->text(
                    'suspension_reason'
                )
                    ->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'shops',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'suspended_by'
                );

                $table->dropColumn([
                    'administrative_status',
                    'suspended_at',
                    'suspension_reason',
                ]);
            }
        );
    }
};