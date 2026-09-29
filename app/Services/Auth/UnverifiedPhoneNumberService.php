<?php

namespace App\Services\Auth;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UnverifiedPhoneNumberService
{
    /**
     * Replace the phone number of an unverified mobile account.
     *
     * The account must prove its current password. Any OTP issued for the old
     * number is invalidated and all other Sanctum sessions are revoked.
     */
    public function update(
        User $user,
        string $phoneNumber,
        string $currentPassword
    ): User {
        $currentTokenId =
            $user
                ->currentAccessToken()
                ?->getKey();

        $normalizedPhoneNumber =
            PhilippinePhoneNumber::normalize(
                $phoneNumber
            );

        if (
            $normalizedPhoneNumber === null
            || ! preg_match(
                '/^\+639\d{9}$/',
                $normalizedPhoneNumber
            )
        ) {
            throw ValidationException::withMessages([
                'phone_number' =>
                    'The phone number must be a valid Philippine mobile number.',
            ]);
        }

        try {
            $updatedUser =
                DB::transaction(
                    function () use (
                        $user,
                        $normalizedPhoneNumber,
                        $currentPassword,
                        $currentTokenId
                    ): User {
                        $lockedUser =
                            User::query()
                                ->whereKey(
                                    $user->id
                                )
                                ->lockForUpdate()
                                ->firstOrFail();

                        /*
                        |------------------------------------------------------------------
                        | Verified Numbers Use A Different Recovery Flow
                        |------------------------------------------------------------------
                        |
                        | This endpoint exists only to recover from a registration typo
                        | before the account has proven ownership of a phone number.
                        |
                        */

                        if (
                            $lockedUser
                                ->hasVerifiedPhoneNumber()
                        ) {
                            throw ValidationException::withMessages([
                                'phone_number' =>
                                    'A verified phone number cannot be changed from this verification flow.',
                            ]);
                        }

                        /*
                        |------------------------------------------------------------------
                        | Require Current Password
                        |------------------------------------------------------------------
                        |
                        | A stolen bearer token alone must not be enough to replace the
                        | number and unlock protected customer/shop-owner features.
                        |
                        */

                        if (
                            ! Hash::check(
                                $currentPassword,
                                $lockedUser->password
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'current_password' =>
                                    'The current password is incorrect.',
                            ]);
                        }

                        if (
                            hash_equals(
                                (string) $lockedUser->phone_number,
                                $normalizedPhoneNumber
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'phone_number' =>
                                    'The new phone number must be different from your current phone number.',
                            ]);
                        }

                        /*
                        |------------------------------------------------------------------
                        | Defense-In-Depth Uniqueness Check
                        |------------------------------------------------------------------
                        |
                        | The FormRequest checks this too. The database unique constraint
                        | remains the final authority if two requests race each other.
                        |
                        */

                        $phoneNumberInUse =
                            User::query()
                                ->where(
                                    'phone_number',
                                    $normalizedPhoneNumber
                                )
                                ->whereKeyNot(
                                    $lockedUser->id
                                )
                                ->exists();

                        if (
                            $phoneNumberInUse
                        ) {
                            throw ValidationException::withMessages([
                                'phone_number' =>
                                    'This phone number is already associated with another account.',
                            ]);
                        }

                        /*
                        |------------------------------------------------------------------
                        | Invalidate The Old OTP Before Switching Numbers
                        |------------------------------------------------------------------
                        |
                        | This happens in the same transaction. If the user update fails,
                        | deletion rolls back as well.
                        |
                        */

                        PhoneVerificationCode::query()
                            ->where(
                                'user_id',
                                $lockedUser->id
                            )
                            ->delete();

                        $lockedUser->phone_number =
                            $normalizedPhoneNumber;

                        $lockedUser->phone_verified_at =
                            null;

                        $lockedUser->save();

                        /*
                        |------------------------------------------------------------------
                        | Revoke Other Sessions
                        |------------------------------------------------------------------
                        |
                        | Keep the device that proved the password, but remove any other
                        | tokens that could have been copied before this recovery action.
                        |
                        */

                        $tokens =
                            $lockedUser->tokens();

                        if (
                            $currentTokenId !== null
                        ) {
                            $tokens->where(
                                'id',
                                '<>',
                                $currentTokenId
                            );
                        }

                        $tokens->delete();

                        return $lockedUser;
                    }
                );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw ValidationException::withMessages([
                'phone_number' =>
                    'This phone number is already associated with another account.',
            ]);
        }

        return $updatedUser->fresh();
    }
}