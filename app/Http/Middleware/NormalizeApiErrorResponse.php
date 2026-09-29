<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiErrorResponseNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiErrorResponse
{
    /**
     * Normalize error responses returned directly by controllers,
     * middleware, authentication logic, validation logic, and other
     * matched API routes.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response =
            $next(
                $request
            );

        return ApiErrorResponseNormalizer::normalize(
            $request,
            $response
        );
    }
}