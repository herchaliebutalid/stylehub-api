<?php

namespace App\Services\Customer;

use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Support\PhilippinePhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerPersonalInformationService
{
    /**
     * @return array{
     *     user: User,
     *     email_changed: bool,
     *     phone_changed: bool
     * }
     */
    public function update(
        User $customer,
        array $data
    ): array {
        $currentTokenId =
            $customer
                ->currentAccessToken()
                ?->getKey();

        try {
            return DB::transaction(
                function () use (
                    $customer,
                    $data,
                    $currentTokenId
                ): array {
                    $lockedCustomer =
                        User::query()
                            ->whereKey(
                                $customer->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize Sensitive Identity Fields
                    |--------------------------------------------------------------------------
                    */

                    $email =
                        mb_strtolower(
                            trim(
                                $data['email']
                            )
                        );

                    $phoneNumber =
                        PhilippinePhoneNumber::normalize(
                            $data['phone_number']
                        );

                    if (
                        $phoneNumber === null
                        || ! preg_match(
                            '/^\+639\d{9}$/',
                            $phoneNumber
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'phone_number' =>
                                'The phone number must be a valid Philippine mobile number.',
                        ]);
                    }

                    $emailChanged =
                        $email
                        !==
                        mb_strtolower(
                            (string) $lockedCustomer->email
                        );

                    $phoneChanged =
                        $phoneNumber
                        !==
                        (string) $lockedCustomer->phone_number;

                    $sensitiveIdentityChanged =
                        $emailChanged
                        || $phoneChanged;

                    /*
                    |--------------------------------------------------------------------------
                    | Require Current Password For Email / Phone Changes
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $sensitiveIdentityChanged
                    ) {
                        $currentPassword =
                            $data[
                                'current_password'
                            ]
                            ?? null;

                        if (
                            ! is_string(
                                $currentPassword
                            )
                            || trim(
                                $currentPassword
                            ) === ''
                        ) {
                            throw ValidationException::withMessages([
                                'current_password' =>
                                    'Your current password is required when changing your email address or mobile number.',
                            ]);
                        }

                        if (
                            ! Hash::check(
                                $currentPassword,
                                $lockedCustomer->password
                            )
                        ) {
                            throw ValidationException::withMessages([
                                'current_password' =>
                                    'The current password is incorrect.',
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Defense-In-Depth Uniqueness
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $emailChanged
                        &&
                        User::query()
                            ->withTrashed()
                            ->where(
                                'email',
                                $email
                            )
                            ->whereKeyNot(
                                $lockedCustomer->id
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'email' =>
                                'This email address is already associated with another account.',
                        ]);
                    }

                    if (
                        $phoneChanged
                        &&
                        User::query()
                            ->withTrashed()
                            ->where(
                                'phone_number',
                                $phoneNumber
                            )
                            ->whereKeyNot(
                                $lockedCustomer->id
                            )
                            ->exists()
                    ) {
                        throw ValidationException::withMessages([
                            'phone_number' =>
                                'This phone number is already associated with another account.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Editable Personal Information
                    |--------------------------------------------------------------------------
                    */

                    $lockedCustomer->first_name =
                        $data['first_name'];

                    $lockedCustomer->middle_name =
                        $data['middle_name']
                        ?? null;

                    $lockedCustomer->last_name =
                        $data['last_name'];

                    $lockedCustomer->email =
                        $email;

                    $lockedCustomer->phone_number =
                        $phoneNumber;

                    /*
                    |--------------------------------------------------------------------------
                    | Email Verification
                    |--------------------------------------------------------------------------
                    |
                    | A verification timestamp belongs to the previous email.
                    | Never carry it onto a different email address.
                    |
                    */

                    if (
                        $emailChanged
                    ) {
                        $lockedCustomer->email_verified_at =
                            null;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Phone Verification
                    |--------------------------------------------------------------------------
                    |
                    | Verification proves ownership of one specific number.
                    | Therefore a new phone number must be verified again.
                    |
                    */

                    if (
                        $phoneChanged
                    ) {
                        PhoneVerificationCode::query()
                            ->where(
                                'user_id',
                                $lockedCustomer->id
                            )
                            ->delete();

                        $lockedCustomer->phone_verified_at =
                            null;
                    }

                    $lockedCustomer->save();

                    /*
                    |--------------------------------------------------------------------------
                    | Revoke Other Sessions For Sensitive Identity Changes
                    |--------------------------------------------------------------------------
                    |
                    | Keep the current mobile session so the customer can continue
                    | directly into phone verification when necessary.
                    |
                    */

                    if (
                        $sensitiveIdentityChanged
                        && $currentTokenId !== null
                    ) {
                        $lockedCustomer
                            ->tokens()
                            ->where(
                                'id',
                                '<>',
                                $currentTokenId
                            )
                            ->delete();
                    }

                    return [
                        'user' =>
                            $lockedCustomer
                                ->fresh(),

                        'email_changed' =>
                            $emailChanged,

                        'phone_changed' =>
                            $phoneChanged,
                    ];
                },
                3
            );
        } catch (
            UniqueConstraintViolationException
        ) {
            throw ValidationException::withMessages([
                'email' =>
                    'The email address or mobile number is already associated with another account.',

                'phone_number' =>
                    'The email address or mobile number is already associated with another account.',
            ]);
        }
    }
}