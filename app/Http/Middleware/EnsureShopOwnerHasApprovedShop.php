<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopOwnerHasApprovedShop
{
    /**
     * Allow only verified shop owners whose shop is approved and
     * administratively active.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|JsonResponse {
        $user =
            $request->user();

        if (
            $user === null
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (
            ! $user->isShopOwner()
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
        */

        if (
            ! $user
                ->hasVerifiedPhoneNumber()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Please verify your phone number before managing your shop.',
            ], Response::HTTP_FORBIDDEN);
        }

        /*
        |--------------------------------------------------------------------------
        | Shop Approval
        |--------------------------------------------------------------------------
        */

        $shop =
            $user
                ->shop()
                ->first();

        if (
            $shop === null
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'No shop is associated with this account.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (
            ! $shop->isApproved()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Your shop must be approved before you can manage services.',
            ], Response::HTTP_FORBIDDEN);
        }

        /*
        |--------------------------------------------------------------------------
        | Independent Administrative Suspension
        |--------------------------------------------------------------------------
        |
        | The Shop Owner account remains active.
        |
        | The owner may still use:
        |
        | - /api/v1/auth/me
        | - logout
        | - password/security flows
        |
        | Only shop-management APIs are blocked.
        |
        */

        if (
            $shop->isSuspended()
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'Your shop is currently suspended by an administrator. Shop management is unavailable until the suspension is lifted.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next(
            $request
        );
    }
}