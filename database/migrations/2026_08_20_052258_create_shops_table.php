<?php

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
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
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Ownership
            |--------------------------------------------------------------------------
            |
            | StyleHub currently allows one shop per shop-owner account.
            | The UNIQUE constraint enforces this rule at the database level.
            |
            */

            $table->foreignId('owner_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Shop Information
            |--------------------------------------------------------------------------
            */

            $table->string('name', 150);

            $table->string('type', 32)
                ->index();

            $table->text('description')
                ->nullable();

            $table->string('phone_number', 20);

            $table->string('email')
                ->nullable();

            $table->string('address', 255);

            $table->string('city', 100);

            $table->string('province', 100);

            /*
            |--------------------------------------------------------------------------
            | Shop Availability
            |--------------------------------------------------------------------------
            |
            | Newly registered shops start as CLOSED until the owner has
            | configured the business and the registration is approved.
            |
            */

            $table->string('availability_status', 32)
                ->default(
                    ShopAvailabilityStatus::Closed->value
                )
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Administrator Approval
            |--------------------------------------------------------------------------
            */

            $table->string('approval_status', 32)
                ->default(
                    ShopApprovalStatus::Pending->value
                )
                ->index();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')
                ->nullable();

            $table->text('rejection_reason')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Audit Timestamps
            |--------------------------------------------------------------------------
            */

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Search Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'province',
                'city',
            ]);

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};