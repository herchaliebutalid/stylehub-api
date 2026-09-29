<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'password_reset_codes',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('email', 255);
                $table->string('phone_number', 20);
                $table->string('code_hash', 64);

                $table
                    ->unsignedTinyInteger('attempts')
                    ->default(0);

                $table->timestamp('expires_at');
                $table->timestamp('last_sent_at');
                $table->timestamp('last_attempt_at')->nullable();

                $table->timestamps();

                $table->index('expires_at');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'password_reset_codes'
        );
    }
};