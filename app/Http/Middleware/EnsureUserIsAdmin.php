<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Allow only authenticated administrator accounts.
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

        if (! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}