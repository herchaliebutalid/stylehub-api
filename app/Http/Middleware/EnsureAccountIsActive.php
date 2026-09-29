<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Ensure the authenticated account remains active.
     *
     * This check runs on protected API requests so that an already-issued
     * token cannot bypass a later account suspension.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response|JsonResponse {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'This account is currently suspended.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}