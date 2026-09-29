<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Exceptions\Sms\SmsGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordRecoveryService $passwordRecoveryService
    ) {}

    /**
     * Request a password reset SMS.
     *
     * Always use a generic response to prevent account enumeration.
     */
    public function forgot(
        ForgotPasswordRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        try {
            $this
                ->passwordRecoveryService
                ->requestReset(
                    role:
                        UserRole::from(
                            $validated['role']
                        ),

                    email:
                        $validated['email'],
                );
        } catch (
            SmsGatewayException $exception
        ) {
            /*
             * Do not reveal to a public caller whether:
             *
             * - the account exists;
             * - Semaphore is configured;
             * - an SMS provider failure occurred.
             *
             * Server logs still receive the exception.
             */
            report(
                $exception
            );
        }

        return response()->json([
            'success' =>
                true,

            'message' =>
                'If an eligible StyleHub account matches those details, a password reset code has been sent to its registered mobile number.',

            'data' => [
                'resend_after_seconds' =>
                    $this
                        ->passwordRecoveryService
                        ->resendCooldownSeconds(),
            ],
        ]);
    }

    /**
     * Reset a forgotten password using a one-time SMS code.
     */
    public function reset(
        ResetPasswordRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $this
            ->passwordRecoveryService
            ->resetPassword(
                role:
                    UserRole::from(
                        $validated['role']
                    ),

                email:
                    $validated['email'],

                code:
                    $validated['code'],

                newPassword:
                    $validated['password'],
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Password reset successfully. Please sign in with your new password.',
        ]);
    }

    /**
     * Change the password for the authenticated account.
     */
    public function change(
        ChangePasswordRequest $request
    ): JsonResponse {
        $validated =
            $request->validated();

        $this
            ->passwordRecoveryService
            ->changePassword(
                user:
                    $request->user(),

                currentPassword:
                    $validated[
                        'current_password'
                    ],

                newPassword:
                    $validated[
                        'password'
                    ],
            );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Password changed successfully. Other signed-in sessions were revoked.',
        ]);
    }
}