<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\Sms\SmsGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\VerifyPhoneNumberRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\PhoneVerificationService;
use App\Support\Sms\PhilippineSmsRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $phoneVerificationService
    ) {}

    /**
     * Send a verification code to the authenticated account's
     * registered mobile number.
     */
    public function send(
        Request $request
    ): JsonResponse {
        try {
            $verification =
                $this
                    ->phoneVerificationService
                    ->sendCode(
                        $request->user()
                    );
        } catch (
            SmsGatewayException
        ) {
            /*
             * Do not expose Semaphore/provider internals to the client.
             */
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Unable to send the verification code right now. Please try again later.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'success' =>
                true,

            'message' =>
                'A verification code was sent to your mobile number.',

            'data' => [
                /*
                 * Never return the full number unnecessarily.
                 *
                 * Example:
                 *
                 * 09171234567
                 * ↓
                 * 0917***4567
                 */
                'phone_number' =>
                    PhilippineSmsRecipient::mask(
                        $verification
                            ->phone_number
                    ),

                'expires_at' =>
                    $verification
                        ->expires_at
                        ->toISOString(),

                'resend_after_seconds' =>
                    $this
                        ->phoneVerificationService
                        ->resendCooldownSeconds(),
            ],
        ]);
    }

    /**
     * Verify the authenticated user's six-digit code.
     */
    public function verify(
        VerifyPhoneNumberRequest $request
    ): JsonResponse {
        $user =
            $this
                ->phoneVerificationService
                ->verifyCode(
                    user:
                        $request->user(),

                    code:
                        (string) $request
                            ->validated(
                                'code'
                            ),
                );

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Phone number verified successfully.',

            'data' => [
                'user' =>
                    (
                        new UserResource(
                            $user
                        )
                    )->resolve(
                        $request
                    ),
            ],
        ]);
    }
}