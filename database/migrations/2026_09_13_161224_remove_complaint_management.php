<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove Historical Complaint Audit Entries
        |--------------------------------------------------------------------------
        |
        | Complaint AuditEvent enum values no longer exist.
        |
        | These rows must be removed before normal AuditLog models try to cast
        | their event values back into the AuditEvent enum.
        |
        */

        if (
            Schema::hasTable(
                'audit_logs'
            )
        ) {
            DB::table(
                'audit_logs'
            )
                ->where(
                    function (
                        $query
                    ): void {
                        $query
                            ->whereIn(
                                'event',
                                [
                                    'complaint_under_review',
                                    'complaint_resolved',
                                    'complaint_dismissed',
                                ]
                            )
                            ->orWhere(
                                'auditable_type',
                                'App\\Models\\Complaint'
                            );
                    }
                )
                ->delete();
        }

        /*
        |--------------------------------------------------------------------------
        | Remove Complaint Storage
        |--------------------------------------------------------------------------
        */

        Schema::dropIfExists(
            'complaints'
        );
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Schema-only Rollback
        |--------------------------------------------------------------------------
        |
        | Rolling this migration back can restore the old table structure,
        | but deleted complaint records and audit records cannot be recovered.
        |
        */

        if (
            Schema::hasTable(
                'complaints'
            )
        ) {
            return;
        }

        Schema::create(
            'complaints',
            function (
                Blueprint $table
            ): void {
                $table->id();

                $table->foreignId(
                    'appointment_id'
                )
                    ->unique()
                    ->constrained(
                        'appointments'
                    )
                    ->restrictOnDelete();

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

                $table->string(
                    'status',
                    32
                )->default(
                    'open'
                );

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
};