<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    /**
     * Allow only authenticated customers with verified phone numbers.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|JsonResponse {
        $user =
            $request->user();

        if (
            $user ===
            null
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (
            ! $user->isCustomer()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'You are not authorized to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        /*
        |--------------------------------------------------------------------------
        | Phone Verification Gate
        |--------------------------------------------------------------------------
        |
        | The React Native application cannot bypass this.
        |
        | Even a manually crafted API request remains blocked.
        |
        */

        if (
            ! $user
                ->hasVerifiedPhoneNumber()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Please verify your phone number before using customer features.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next(
            $request
        );
    }
}