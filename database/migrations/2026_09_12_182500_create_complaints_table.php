<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'complaints',
            function (
                Blueprint $table
            ): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Verified Appointment Source
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'appointment_id'
                )
                    ->unique()
                    ->constrained(
                        'appointments'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Trusted Ownership Snapshot
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'customer_id'
                )
                    ->constrained(
                        'users'
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'shop_id'
                )
                    ->constrained(
                        'shops'
                    )
                    ->restrictOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Original Customer Complaint
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'category',
                    50
                );

                $table->string(
                    'subject',
                    150
                );

                $table->text(
                    'description'
                );

                /*
                |--------------------------------------------------------------------------
                | Administrator Moderation State
                |--------------------------------------------------------------------------
                |
                | This is now a historical migration only.
                |
                | Use a literal instead of the removed ComplaintStatus enum so
                | migrate:fresh can still replay historical migrations safely.
                |
                */

                $table->string(
                    'status',
                    32
                )->default(
                    'open'
                );

                /*
                |--------------------------------------------------------------------------
                | Review Information
                |--------------------------------------------------------------------------
                */

                $table->foreignId(
                    'reviewed_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'reviewed_at'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Closure Information
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'resolution_note'
                )->nullable();

                $table->foreignId(
                    'closed_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'closed_at'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Monitoring Indexes
                |--------------------------------------------------------------------------
                */

                $table->index([
                    'status',
                    'created_at',
                ]);

                $table->index([
                    'shop_id',
                    'status',
                ]);

                $table->index([
                    'customer_id',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'complaints'
        );
    }
};