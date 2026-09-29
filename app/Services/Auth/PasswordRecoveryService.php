<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordRecoveryService
{
    public function __construct(
        private readonly SemaphoreSmsGateway $smsGateway
    ) {}

    /**
     * Request a password-reset code for an eligible mobile account.
     *
     * This method intentionally returns no account-existence information.
     * The controller always responds with the same public message.
     *
     * @throws SmsGatewayException
     */
    public function requestReset(
        UserRole $role,
        string $email
    ): void {
        $user = User::query()
            ->where('email', $email)
            ->where('role', $role->value)
            ->first();

        if (
            $user === null
            || $user->status !== UserStatus::Active
            || $user->isAdmin()
        ) {
            return;
        }

        $code = $this->generateCode();

        $reset = DB::transaction(
            function () use (
                $user,
                $email,
                $role,
                $code
            ): ?PasswordResetCode {
                $lockedUser = User::query()
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $lockedUser === null
                    || $lockedUser->status !== UserStatus::Active
                    || $lockedUser->role !== $role
                    || $lockedUser->isAdmin()
                ) {
                    return null;
                }

                $phoneNumber = PhilippineSmsRecipient::normalize(
                    $lockedUser->phone_number
                );

                if ($phoneNumber === null) {
                    return null;
                }

                $reset = PasswordResetCode::query()
                    ->where(
                        'user_id',
                        $lockedUser->id
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | Database-Level Cooldown
                |--------------------------------------------------------------------------
                |
                | Route throttling is one layer.
                |
                | This is another layer protecting Semaphore credits from repeated
                | reset requests for the same account.
                |
                */

                if (
                    $reset !== null
                    && $reset->last_sent_at !== null
                    && $reset->last_sent_at->gt(
                        now()->subSeconds(
                            $this->resendCooldownSeconds()
                        )
                    )
                ) {
                    return null;
                }

                $reset ??=
                    new PasswordResetCode();

                $reset->user_id =
                    $lockedUser->id;

                $reset->email =
                    mb_strtolower(
                        trim($email)
                    );

                $reset->phone_number =
                    $phoneNumber;

                /*
                |--------------------------------------------------------------------------
                | Never Store Plain OTP
                |--------------------------------------------------------------------------
                */

                $reset->code_hash =
                    $this->hashCode(
                        userId:
                            $lockedUser->id,

                        email:
                            $reset->email,

                        phoneNumber:
                            $phoneNumber,

                        code:
                            $code,
                    );

                $reset->attempts =
                    0;

                $reset->expires_at =
                    now()->addMinutes(
                        $this->expirationMinutes()
                    );

                $reset->last_sent_at =
                    now();

                $reset->last_attempt_at =
                    null;

                $reset->save();

                return $reset;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | No Eligible Account / Cooldown
        |--------------------------------------------------------------------------
        |
        | Returning silently prevents the public endpoint from revealing whether
        | an account exists.
        |
        */

        if ($reset === null) {
            return;
        }

        try {
            $this->smsGateway->send(
                number:
                    $reset->phone_number,

                message:
                    sprintf(
                        'Your StyleHub password reset code is %s. It expires in %d minutes. Do not share this code.',
                        $code,
                        $this->expirationMinutes()
                    ),
            );
        } catch (SmsGatewayException $exception) {
            /*
            |--------------------------------------------------------------------------
            | Definite Failure
            |--------------------------------------------------------------------------
            |
            | Semaphore definitely rejected the request, so keeping this code
            | would leave an OTP that the user never received.
            |
            */

            if ($exception->isSafeToRetry()) {
                PasswordResetCode::query()
                    ->whereKey(
                        $reset->id
                    )
                    ->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Uncertain Failure
            |--------------------------------------------------------------------------
            |
            | If Semaphore timed out after accepting the SMS, keep the code because
            | the SMS may still arrive.
            |
            */

            throw $exception;
        }
    }

    /**
     * Reset a password using a one-time SMS code.
     */
    public function resetPassword(
        UserRole $role,
        string $email,
        string $code,
        string $newPassword
    ): void {
        $result = DB::transaction(
            function () use (
                $role,
                $email,
                $code,
                $newPassword
            ): bool {
                $user = User::query()
                    ->where(
                        'email',
                        $email
                    )
                    ->where(
                        'role',
                        $role->value
                    )
                    ->lockForUpdate()
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | Generic Invalid State
                |--------------------------------------------------------------------------
                */

                if (
                    $user === null
                    || $user->status !== UserStatus::Active
                    || $user->isAdmin()
                ) {
                    return false;
                }

                $reset = PasswordResetCode::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->lockForUpdate()
                    ->first();

                $phoneNumber =
                    PhilippineSmsRecipient::normalize(
                        $user->phone_number
                    );

                if (
                    $reset === null
                    || $phoneNumber === null
                    || ! hash_equals(
                        $reset->email,
                        mb_strtolower(
                            trim($email)
                        )
                    )
                    || ! hash_equals(
                        $reset->phone_number,
                        $phoneNumber
                    )
                    || $reset->expires_at->lte(
                        now()
                    )
                    || $reset->attempts >=
                        $this->maxAttempts()
                ) {
                    $reset?->delete();

                    return false;
                }

                /*
                |--------------------------------------------------------------------------
                | Count Verification Attempt
                |--------------------------------------------------------------------------
                */

                $reset->attempts++;

                $reset->last_attempt_at =
                    now();

                $expectedHash =
                    $this->hashCode(
                        userId:
                            $user->id,

                        email:
                            $reset->email,

                        phoneNumber:
                            $phoneNumber,

                        code:
                            $code,
                    );

                /*
                |--------------------------------------------------------------------------
                | Wrong Code
                |--------------------------------------------------------------------------
                */

                if (
                    ! hash_equals(
                        $reset->code_hash,
                        $expectedHash
                    )
                ) {
                    if (
                        $reset->attempts >=
                        $this->maxAttempts()
                    ) {
                        $reset->delete();
                    } else {
                        $reset->save();
                    }

                    return false;
                }

                /*
                |--------------------------------------------------------------------------
                | Don't Reuse Current Password
                |--------------------------------------------------------------------------
                */

                if (
                    Hash::check(
                        $newPassword,
                        $user->password
                    )
                ) {
                    throw ValidationException::withMessages([
                        'password' =>
                            'The new password must be different from your current password.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Change Password
                |--------------------------------------------------------------------------
                |
                | User.password uses Laravel's hashed cast.
                |
                */

                $user->password =
                    $newPassword;

                $user->remember_token =
                    Str::random(60);

                $user->save();

                /*
                |--------------------------------------------------------------------------
                | Recovery Event = Revoke Every Session
                |--------------------------------------------------------------------------
                |
                | If someone stole a Sanctum token before the password reset, it
                | must not remain valid afterward.
                |
                */

                $user->tokens()
                    ->delete();

                /*
                |--------------------------------------------------------------------------
                | One-Time Reset Code
                |--------------------------------------------------------------------------
                */

                $reset->delete();

                return true;
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Generic Reset Error
        |--------------------------------------------------------------------------
        |
        | Same response for:
        |
        | - nonexistent account
        | - wrong code
        | - expired code
        | - exhausted attempts
        | - mismatched phone snapshot
        |
        */

        if (! $result) {
            throw ValidationException::withMessages([
                'code' =>
                    'The password reset code is invalid or expired.',
            ]);
        }
    }

    /**
     * Change the password for an already authenticated account.
     *
     * The current session remains active.
     * Every other Sanctum token is revoked.
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword
    ): void {
        $currentTokenId =
            $user
                ->currentAccessToken()
                ?->getKey();

        DB::transaction(
            function () use (
                $user,
                $currentPassword,
                $newPassword,
                $currentTokenId
            ): void {
                $lockedUser = User::query()
                    ->whereKey(
                        $user->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                |--------------------------------------------------------------------------
                | Require Current Password
                |--------------------------------------------------------------------------
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

                /*
                |--------------------------------------------------------------------------
                | New Password Must Actually Be New
                |--------------------------------------------------------------------------
                */

                if (
                    Hash::check(
                        $newPassword,
                        $lockedUser->password
                    )
                ) {
                    throw ValidationException::withMessages([
                        'password' =>
                            'The new password must be different from your current password.',
                    ]);
                }

                $lockedUser->password =
                    $newPassword;

                $lockedUser->remember_token =
                    Str::random(60);

                $lockedUser->save();

                /*
                 * Any unused password-reset code should become invalid once the
                 * authenticated user changes their password.
                 */
                PasswordResetCode::query()
                    ->where(
                        'user_id',
                        $lockedUser->id
                    )
                    ->delete();

                /*
                |--------------------------------------------------------------------------
                | Revoke Other Devices
                |--------------------------------------------------------------------------
                */

                $tokens =
                    $lockedUser->tokens();

                if ($currentTokenId !== null) {
                    $tokens->where(
                        'id',
                        '<>',
                        $currentTokenId
                    );
                }

                $tokens->delete();
            }
        );
    }

    public function resendCooldownSeconds(): int
    {
        return max(
            0,
            min(
                3600,
                (int) config(
                    'password_recovery.resend_cooldown_seconds',
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
                    'password_recovery.expiration_minutes',
                    10
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
                    'password_recovery.max_attempts',
                    5
                )
            )
        );
    }

    /**
     * Cryptographically secure six-digit code.
     */
    private function generateCode(): string
    {
        $digits = max(
            6,
            min(
                8,
                (int) config(
                    'password_recovery.code_digits',
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
     * Bind the reset code to:
     *
     * - exact StyleHub account
     * - current email
     * - current phone number
     * - OTP
     */
    private function hashCode(
        int $userId,
        string $email,
        string $phoneNumber,
        string $code
    ): string {
        $key =
            (string) config(
                'app.key'
            );

        if (
            trim($key) ===
            ''
        ) {
            throw new \RuntimeException(
                'APP_KEY must be configured before password recovery can be used.'
            );
        }

        return hash_hmac(
            'sha256',

            $userId
                .'|'
                .mb_strtolower(
                    trim($email)
                )
                .'|'
                .$phoneNumber
                .'|'
                .$code,

            $key
        );
    }
}