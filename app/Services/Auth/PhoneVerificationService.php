<?php

namespace App\Services\Auth;

use App\Exceptions\Sms\SmsGatewayException;
use App\Models\PhoneVerificationCode;
use App\Models\User;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(
        private readonly SemaphoreSmsGateway $smsGateway
    ) {}

    /**
     * Generate and send a verification code.
     */
    public function sendCode(
        User $user
    ): PhoneVerificationCode {
        /*
        |--------------------------------------------------------------------------
        | Generate Secure Random OTP
        |--------------------------------------------------------------------------
        */

        $code =
            $this->generateCode();

        /*
        |--------------------------------------------------------------------------
        | Persist the OTP Hash
        |--------------------------------------------------------------------------
        |
        | Locking the user row serializes concurrent verification requests for
        | the same account.
        |
        */

        $verification =
            DB::transaction(
                function () use (
                    $user,
                    $code
                ): PhoneVerificationCode {
                    $lockedUser =
                        User::query()
                            ->whereKey(
                                $user->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Already Verified
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $lockedUser
                            ->hasVerifiedPhoneNumber()
                    ) {
                        throw ValidationException::withMessages([
                            'phone_number' =>
                                'This phone number is already verified.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Normalize Destination For Semaphore
                    |--------------------------------------------------------------------------
                    */

                    $phoneNumber =
                        PhilippineSmsRecipient::normalize(
                            $lockedUser
                                ->phone_number
                        );

                    if (
                        $phoneNumber ===
                        null
                    ) {
                        throw ValidationException::withMessages([
                            'phone_number' =>
                                'Your account does not have a valid Philippine mobile number.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Existing OTP
                    |--------------------------------------------------------------------------
                    */

                    $verification =
                        PhoneVerificationCode::query()
                            ->where(
                                'user_id',
                                $lockedUser->id
                            )
                            ->lockForUpdate()
                            ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | Server-Side Resend Cooldown
                    |--------------------------------------------------------------------------
                    |
                    | The route rate limiter is not our only defense.
                    |
                    */

                    if (
                        $verification
                            !== null
                        &&
                        $verification
                            ->last_sent_at
                            !== null
                        &&
                        $verification
                            ->last_sent_at
                            ->gt(
                                now()->subSeconds(
                                    $this
                                        ->resendCooldownSeconds()
                                )
                            )
                    ) {
                        throw ValidationException::withMessages([
                            'phone_number' =>
                                'Please wait before requesting another verification code.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | New / Replacement OTP
                    |--------------------------------------------------------------------------
                    |
                    | Only one current OTP exists for each user.
                    |
                    */

                    $verification ??=
                        new PhoneVerificationCode();

                    $verification->user_id =
                        $lockedUser->id;

                    $verification->phone_number =
                        $phoneNumber;

                    /*
                    |--------------------------------------------------------------------------
                    | HMAC Instead Of Plain OTP
                    |--------------------------------------------------------------------------
                    |
                    | Database:
                    |
                    | code_hash = 9b1f...
                    |
                    | NOT:
                    |
                    | code = 123456
                    |
                    */

                    $verification->code_hash =
                        $this->hashCode(
                            userId:
                                $lockedUser->id,

                            phoneNumber:
                                $phoneNumber,

                            code:
                                $code,
                        );

                    $verification->attempts =
                        0;

                    $verification->expires_at =
                        now()->addMinutes(
                            $this
                                ->expirationMinutes()
                        );

                    $verification->last_sent_at =
                        now();

                    $verification->last_attempt_at =
                        null;

                    $verification->save();

                    return $verification;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Send Through Existing Semaphore Gateway
        |--------------------------------------------------------------------------
        |
        | This intentionally does NOT use sms_notifications because that table
        | stores the SMS message body. Doing that would persist the plaintext
        | OTP inside the database.
        |
        */

        try {
            $this
                ->smsGateway
                ->send(
                    number:
                        $verification
                            ->phone_number,

                    message:
                        sprintf(
                            'Your StyleHub verification code is %s. It expires in %d minutes. Do not share this code.',
                            $code,
                            $this
                                ->expirationMinutes()
                        ),
                );
        } catch (
            SmsGatewayException $exception
        ) {
            /*
            |--------------------------------------------------------------------------
            | Definite Failure
            |--------------------------------------------------------------------------
            |
            | We know the provider did not accept the SMS, so this OTP should
            | not remain valid.
            |
            */

            if (
                $exception
                    ->isSafeToRetry()
            ) {
                PhoneVerificationCode::query()
                    ->whereKey(
                        $verification->id
                    )
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Uncertain Failure
            |--------------------------------------------------------------------------
            |
            | A timeout may happen after Semaphore already accepted the SMS.
            | We deliberately do NOT blindly issue another code here.
            |
            */

            throw $exception;
        } catch (
            \Throwable $exception
        ) {
            PhoneVerificationCode::query()
                ->whereKey(
                    $verification->id
                )
                ->delete();

            throw $exception;
        }

        return $verification->fresh();
    }

    /**
     * Verify a submitted OTP.
     */
    public function verifyCode(
        User $user,
        string $code
    ): User {
        $result =
            DB::transaction(
                function () use (
                    $user,
                    $code
                ): array {
                    /*
                    |--------------------------------------------------------------------------
                    | Lock Account
                    |--------------------------------------------------------------------------
                    */

                    $lockedUser =
                        User::query()
                            ->whereKey(
                                $user->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    /*
                    |--------------------------------------------------------------------------
                    | Idempotent Success
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $lockedUser
                            ->hasVerifiedPhoneNumber()
                    ) {
                        PhoneVerificationCode::query()
                            ->where(
                                'user_id',
                                $lockedUser->id
                            )
                            ->delete();

                        return [
                            'verified' =>
                                true,

                            'user' =>
                                $lockedUser,
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Lock OTP Record
                    |--------------------------------------------------------------------------
                    */

                    $verification =
                        PhoneVerificationCode::query()
                            ->where(
                                'user_id',
                                $lockedUser->id
                            )
                            ->lockForUpdate()
                            ->first();

                    /*
                    |--------------------------------------------------------------------------
                    | Current Account Phone
                    |--------------------------------------------------------------------------
                    */

                    $phoneNumber =
                        PhilippineSmsRecipient::normalize(
                            $lockedUser
                                ->phone_number
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Invalid / Expired State
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $verification ===
                            null
                        ||
                        $phoneNumber ===
                            null
                        ||
                        ! hash_equals(
                            $verification
                                ->phone_number,

                            $phoneNumber
                        )
                        ||
                        $verification
                            ->expires_at
                            ->lte(
                                now()
                            )
                        ||
                        $verification
                            ->attempts
                            >=
                            $this
                                ->maxAttempts()
                    ) {
                        $verification
                            ?->delete();

                        return [
                            'verified' =>
                                false,

                            'user' =>
                                $lockedUser,
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Count Every Verification Attempt
                    |--------------------------------------------------------------------------
                    */

                    $verification->attempts++;

                    $verification->last_attempt_at =
                        now();

                    /*
                    |--------------------------------------------------------------------------
                    | Recalculate Expected HMAC
                    |--------------------------------------------------------------------------
                    */

                    $expectedHash =
                        $this->hashCode(
                            userId:
                                $lockedUser->id,

                            phoneNumber:
                                $phoneNumber,

                            code:
                                $code,
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Incorrect OTP
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ! hash_equals(
                            $verification
                                ->code_hash,

                            $expectedHash
                        )
                    ) {
                        if (
                            $verification
                                ->attempts
                                >=
                                $this
                                    ->maxAttempts()
                        ) {
                            /*
                             * Five wrong attempts invalidate the OTP.
                             */
                            $verification
                                ->delete();
                        } else {
                            /*
                             * Persist the failed attempt.
                             *
                             * We do not throw inside this transaction because
                             * throwing would roll back the attempt counter.
                             */
                            $verification
                                ->save();
                        }

                        return [
                            'verified' =>
                                false,

                            'user' =>
                                $lockedUser,
                        ];
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Verified
                    |--------------------------------------------------------------------------
                    |
                    | Only trusted server code can write this field.
                    |
                    */

                    $lockedUser->phone_verified_at =
                        now();

                    $lockedUser->save();

                    /*
                    |--------------------------------------------------------------------------
                    | One-Time OTP
                    |--------------------------------------------------------------------------
                    */

                    $verification
                        ->delete();

                    return [
                        'verified' =>
                            true,

                        'user' =>
                            $lockedUser,
                    ];
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Generic Failure
        |--------------------------------------------------------------------------
        |
        | Do not tell attackers whether the code:
        |
        | - was wrong;
        | - expired;
        | - exceeded attempts;
        | - never existed.
        |
        */

        if (
            ! $result[
                'verified'
            ]
        ) {
            throw ValidationException::withMessages([
                'code' =>
                    'The verification code is invalid or expired.',
            ]);
        }

        return $result[
            'user'
        ]->fresh();
    }

    public function resendCooldownSeconds(): int
    {
        return max(
            0,
            min(
                3600,

                (int) config(
                    'phone_verification.resend_cooldown_seconds',
                    60
                )
            )
        );
    }

    public function expirationMinutes(): int
    {
        return max(
            1,
            min(
                30,

                (int) config(
                    'phone_verification.expiration_minutes',
                    5
                )
            )
        );
    }

    public function maxAttempts(): int
    {
        return max(
            1,
            min(
                10,

                (int) config(
                    'phone_verification.max_attempts',
                    5
                )
            )
        );
    }

    /**
     * Generate a cryptographically secure numeric OTP.
     */
    private function generateCode(): string
    {
        $digits =
            max(
                6,
                min(
                    8,

                    (int) config(
                        'phone_verification.code_digits',
                        6
                    )
                )
            );

        $maximum =
            (10 ** $digits)
            - 1;

        return str_pad(
            (string) random_int(
                0,
                $maximum
            ),
            $digits,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Bind the OTP to:
     *
     * - this StyleHub account;
     * - this exact phone number;
     * - this exact code.
     */
    private function hashCode(
        int $userId,
        string $phoneNumber,
        string $code
    ): string {
        $key =
            (string) config(
                'app.key'
            );

        if (
            trim(
                $key
            ) === ''
        ) {
            throw new \RuntimeException(
                'APP_KEY must be configured before phone verification can be used.'
            );
        }

        return hash_hmac(
            'sha256',

            $userId
            .'|'
            .$phoneNumber
            .'|'
            .$code,

            $key
        );
    }
}