<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'phone_verification_codes',
            function (
                Blueprint $table
            ): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Account
                |--------------------------------------------------------------------------
                |
                | One account can have only one current OTP.
                |
                | Requesting another code replaces the previous code.
                |
                */

                $table
                    ->foreignId(
                        'user_id'
                    )
                    ->unique()
                    ->constrained(
                        'users'
                    )
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Phone Snapshot
                |--------------------------------------------------------------------------
                |
                | This records the phone number for which this particular OTP
                | was generated.
                |
                */

                $table->string(
                    'phone_number',
                    20
                );

                /*
                |--------------------------------------------------------------------------
                | OTP Hash
                |--------------------------------------------------------------------------
                |
                | NEVER store the six-digit verification code itself.
                |
                */

                $table->string(
                    'code_hash',
                    64
                );

                /*
                |--------------------------------------------------------------------------
                | Brute-Force Protection
                |--------------------------------------------------------------------------
                */

                $table
                    ->unsignedTinyInteger(
                        'attempts'
                    )
                    ->default(0);

                /*
                |--------------------------------------------------------------------------
                | Timing
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'expires_at'
                );

                $table->timestamp(
                    'last_sent_at'
                );

                $table
                    ->timestamp(
                        'last_attempt_at'
                    )
                    ->nullable();

                $table->timestamps();

                $table->index(
                    'expires_at'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'phone_verification_codes'
        );
    }
};