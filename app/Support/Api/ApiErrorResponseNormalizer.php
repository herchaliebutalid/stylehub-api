<?php

namespace App\Support\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiErrorResponseNormalizer
{
    /**
     * Normalize StyleHub API error responses.
     *
     * Successful responses and non-API responses are returned unchanged.
     */
    public static function normalize(
        Request $request,
        Response $response
    ): Response {
        if (! $request->is('api/*')) {
            return $response;
        }

        $status =
            $response->getStatusCode();

        if ($status < 400) {
            return $response;
        }

        $originalPayload =
            self::decodePayload(
                $response
            );

        $payload = [
            'success' => false,

            'message' =>
                self::messageForStatus(
                    $status,
                    $originalPayload
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | Validation Errors
        |--------------------------------------------------------------------------
        |
        | Field-level validation errors are safe and necessary for the
        | Administrator web application and Customer / Shop Owner mobile app.
        |
        */

        if (
            isset(
                $originalPayload['errors']
            )
            && is_array(
                $originalPayload['errors']
            )
        ) {
            $payload['errors'] =
                $originalPayload['errors'];
        }

        $normalized =
            response()->json(
                $payload,
                $status
            );

        /*
        |--------------------------------------------------------------------------
        | Preserve Important Response Headers
        |--------------------------------------------------------------------------
        |
        | Examples:
        |
        | - Retry-After
        | - X-RateLimit-*
        | - Allow
        | - CORS headers
        |
        | Content-Type is intentionally generated again by JsonResponse.
        |
        */

        foreach (
            $response->headers->all()
            as $name => $values
        ) {
            if (
                strtolower(
                    $name
                ) === 'content-type'
            ) {
                continue;
            }

            $normalized
                ->headers
                ->set(
                    $name,
                    $values
                );
        }

        foreach (
            $response
                ->headers
                ->getCookies()
            as $cookie
        ) {
            $normalized
                ->headers
                ->setCookie(
                    $cookie
                );
        }

        return $normalized;
    }

    /**
     * Decode an existing JSON error response.
     *
     * Debug HTML or malformed bodies are deliberately discarded.
     *
     * @return array<string, mixed>
     */
    private static function decodePayload(
        Response $response
    ): array {
        $content =
            $response->getContent();

        if (! is_string($content)) {
            return [];
        }

        $decoded =
            json_decode(
                $content,
                true
            );

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * Build a safe message for the HTTP status.
     *
     * Intentional application messages are preserved for statuses where
     * they provide useful workflow information.
     *
     * Debug-sensitive statuses such as 404 and 5xx are always replaced
     * with a generic message.
     *
     * @param array<string, mixed> $payload
     */
    private static function messageForStatus(
        int $status,
        array $payload
    ): string {
        if ($status >= 500) {
            return 'An unexpected error occurred.';
        }

        return match ($status) {
            400 =>
                self::originalMessage(
                    $payload
                )
                ?? 'The request could not be completed.',

            401 =>
                self::originalMessage(
                    $payload
                )
                ?? 'Unauthenticated.',

            403 =>
                self::originalMessage(
                    $payload
                )
                ?? 'This action is forbidden.',

            /*
            |--------------------------------------------------------------------------
            | 404
            |--------------------------------------------------------------------------
            |
            | Never preserve the framework-generated route/model message.
            |
            | This prevents:
            |
            | - internal route information
            | - model information
            | - tenant existence hints
            | - debug exception details
            |
            */

            404 =>
                'The requested resource was not found.',

            405 =>
                'The requested method is not allowed.',

            409 =>
                self::originalMessage(
                    $payload
                )
                ?? 'The request conflicts with the current resource state.',

            419 =>
                'The request has expired.',

            /*
            |--------------------------------------------------------------------------
            | 422
            |--------------------------------------------------------------------------
            |
            | Laravel's default validation summary varies depending on the
            | number of failing fields.
            |
            | Frontends should instead rely on the stable top-level message
            | plus the field-specific errors object.
            |
            */

            422 =>
                'Validation failed.',

            429 =>
                self::originalMessage(
                    $payload
                )
                ?? 'Too many requests. Please try again later.',

            default =>
                self::originalMessage(
                    $payload
                )
                ?? 'The request could not be completed.',
        };
    }

    /**
     * Return an existing application message when it is a reasonable,
     * short string.
     *
     * @param array<string, mixed> $payload
     */
    private static function originalMessage(
        array $payload
    ): ?string {
        $message =
            $payload['message']
            ?? null;

        if (! is_string($message)) {
            return null;
        }

        $message =
            trim(
                $message
            );

        if ($message === '') {
            return null;
        }

        /*
         * Do not allow unexpectedly huge exception messages through the
         * standardized API boundary.
         */
        if (
            mb_strlen(
                $message
            ) > 500
        ) {
            return null;
        }

        return $message;
    }
}