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
        | Preflight Existing Accounts
        |--------------------------------------------------------------------------
        |
        | Existing StyleHub accounts existed before phone verification.
        |
        | Before changing anything, normalize all existing numbers in memory and
        | verify that:
        |
        | - every account has a valid Philippine mobile number;
        | - two accounts do not resolve to the same normalized number.
        |
        | The migration stops BEFORE changing the database if legacy data would
        | violate our new security rules.
        |
        */

        $users =
            DB::table(
                'users'
            )
                ->select([
                    'id',
                    'phone_number',
                ])
                ->orderBy(
                    'id'
                )
                ->get();

        $normalizedByUserId =
            [];

        $ownerByNormalizedPhone =
            [];

        foreach (
            $users as $user
        ) {
            $normalized =
                $this
                    ->normalizePhilippinePhoneNumber(
                        (string) $user->phone_number
                    );

            if (
                preg_match(
                    '/^\+639\d{9}$/',
                    $normalized
                ) !== 1
            ) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot enable phone verification because user ID %d has an invalid Philippine mobile number.',
                        $user->id
                    )
                );
            }

            if (
                isset(
                    $ownerByNormalizedPhone[
                        $normalized
                    ]
                )
            ) {
                throw new \RuntimeException(
                    sprintf(
                        'Cannot enable phone verification because users %d and %d resolve to the same phone number.',
                        $ownerByNormalizedPhone[
                            $normalized
                        ],
                        $user->id
                    )
                );
            }

            $ownerByNormalizedPhone[
                $normalized
            ] =
                $user->id;

            $normalizedByUserId[
                $user->id
            ] =
                $normalized;
        }

        /*
        |--------------------------------------------------------------------------
        | Add Verification Timestamp
        |--------------------------------------------------------------------------
        */

        Schema::table(
            'users',
            function (
                Blueprint $table
            ): void {
                $table
                    ->timestamp(
                        'phone_verified_at'
                    )
                    ->nullable();
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Grandfather Existing Accounts
        |--------------------------------------------------------------------------
        |
        | Accounts that existed before this feature are marked verified so that
        | introducing OTP does not suddenly lock every existing capstone account
        | out of the system.
        |
        | All accounts created AFTER this migration will start with NULL.
        |
        */

        $verifiedAt =
            now();

        foreach (
            $normalizedByUserId
            as
            $userId =>
            $normalized
        ) {
            DB::table(
                'users'
            )
                ->where(
                    'id',
                    $userId
                )
                ->update([
                    'phone_number' =>
                        $normalized,

                    'phone_verified_at' =>
                        $verifiedAt,
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Database-Level Phone Uniqueness
        |--------------------------------------------------------------------------
        |
        | Validation alone is not enough because two concurrent registration
        | requests could theoretically pass application validation together.
        |
        | The database remains the final authority.
        |
        */

        Schema::table(
            'users',
            function (
                Blueprint $table
            ): void {
                $table->unique(
                    'phone_number',
                    'users_phone_number_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'users',
            function (
                Blueprint $table
            ): void {
                $table->dropUnique(
                    'users_phone_number_unique'
                );

                $table->dropColumn(
                    'phone_verified_at'
                );
            }
        );
    }

    private function normalizePhilippinePhoneNumber(
        string $phoneNumber
    ): string {
        $normalized =
            preg_replace(
                '/[\s\-\(\)]+/',
                '',
                trim(
                    $phoneNumber
                )
            );

        $normalized =
            $normalized
            ?? '';

        if (
            str_starts_with(
                $normalized,
                '09'
            )
        ) {
            return '+63'
                .substr(
                    $normalized,
                    1
                );
        }

        if (
            str_starts_with(
                $normalized,
                '639'
            )
        ) {
            return '+'
                .$normalized;
        }

        return $normalized;
    }
};