<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_admin_web_origin_receives_cors_headers(): void
    {
        $response =
            $this
                ->withHeaders([
                    'Origin' =>
                        'http://localhost:5173',

                    'Access-Control-Request-Method' =>
                        'POST',

                    'Access-Control-Request-Headers' =>
                        'Authorization, Content-Type',
                ])
                ->options(
                    '/api/v1/admin/auth/login'
                );

        $response
            ->assertSuccessful();

        $response
            ->assertHeader(
                'Access-Control-Allow-Origin',
                'http://localhost:5173'
            );

        $allowedMethods =
            (string) $response->headers->get(
                'Access-Control-Allow-Methods'
            );

        $this->assertStringContainsString(
            'POST',
            $allowedMethods
        );
    }

    public function test_second_allowed_admin_development_origin_receives_cors_headers(): void
    {
        $response =
            $this
                ->withHeaders([
                    'Origin' =>
                        'http://127.0.0.1:5173',

                    'Access-Control-Request-Method' =>
                        'POST',
                ])
                ->options(
                    '/api/v1/admin/auth/login'
                );

        $response
            ->assertSuccessful();

        $response
            ->assertHeader(
                'Access-Control-Allow-Origin',
                'http://127.0.0.1:5173'
            );
    }

    public function test_unknown_browser_origin_is_not_granted_cors_access(): void
    {
        $response =
            $this
                ->withHeaders([
                    'Origin' =>
                        'https://malicious.example',

                    'Access-Control-Request-Method' =>
                        'POST',
                ])
                ->options(
                    '/api/v1/admin/auth/login'
                );

        $this->assertFalse(
            $response
                ->headers
                ->has(
                    'Access-Control-Allow-Origin'
                )
        );
    }

    public function test_authorization_header_is_permitted_for_preflight_requests(): void
    {
        $response =
            $this
                ->withHeaders([
                    'Origin' =>
                        'http://localhost:5173',

                    'Access-Control-Request-Method' =>
                        'GET',

                    'Access-Control-Request-Headers' =>
                        'Authorization, Content-Type',
                ])
                ->options(
                    '/api/v1/admin/dashboard'
                );

        $response
            ->assertSuccessful();

        $allowedHeaders =
            strtolower(
                (string) $response
                    ->headers
                    ->get(
                        'Access-Control-Allow-Headers'
                    )
            );

        $this->assertStringContainsString(
            'authorization',
            $allowedHeaders
        );

        $this->assertStringContainsString(
            'content-type',
            $allowedHeaders
        );
    }

    public function test_non_api_route_does_not_receive_stylehub_api_cors_headers(): void
    {
        $response =
            $this
                ->withHeaders([
                    'Origin' =>
                        'http://localhost:5173',
                ])
                ->get(
                    '/'
                );

        $this->assertFalse(
            $response
                ->headers
                ->has(
                    'Access-Control-Allow-Origin'
                )
        );
    }
}