<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'sms_templates',
            function (
                Blueprint $table
            ): void {
                $table->engine =
                    'InnoDB';

                $table->charset =
                    'utf8mb4';

                $table->collation =
                    'utf8mb4_unicode_ci';

                $table->id();

                $table->string(
                    'type',
                    64
                )->unique();

                $table->text(
                    'template'
                );

                $table->foreignId(
                    'updated_by'
                )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();
            }
        );

        $now =
            now();

        DB::table(
            'sms_templates'
        )->insert([
            [
                'type' =>
                    'booking_received',

                'template' =>
                    'StyleHub: We received your booking at {shop_name} for {service_name} on {appointment_date} at {appointment_time}. Status: Pending.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'appointment_confirmed',

                'template' =>
                    'StyleHub: Your appointment at {shop_name} on {appointment_date} at {appointment_time} is confirmed.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'appointment_declined',

                'template' =>
                    'StyleHub: Your appointment request at {shop_name} on {appointment_date} at {appointment_time} was declined. Open StyleHub for details.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'appointment_cancelled',

                'template' =>
                    'StyleHub: Your appointment at {shop_name} on {appointment_date} at {appointment_time} was cancelled. Open StyleHub for details.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'reschedule_approved',

                'template' =>
                    'StyleHub: Your reschedule request was approved. New schedule: {appointment_date} at {appointment_time} at {shop_name}.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'reschedule_rejected',

                'template' =>
                    'StyleHub: Your reschedule request for {requested_date} at {requested_time} at {shop_name} was not approved. Your current appointment remains unchanged.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
            [
                'type' =>
                    'appointment_reminder',

                'template' =>
                    'StyleHub reminder: Your confirmed appointment at {shop_name} is on {appointment_date} at {appointment_time}.',

                'updated_by' =>
                    null,

                'created_at' =>
                    $now,

                'updated_at' =>
                    $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sms_templates'
        );
    }
};