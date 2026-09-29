<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiErrorResponseContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_response_uses_standard_contract(): void
    {
        $response =
            $this->getJson(
                '/api/v1/customer/appointments'
            );

        $response
            ->assertUnauthorized()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'message',
                'Unauthenticated.'
            );

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_validation_failure_uses_standard_contract_and_preserves_field_errors(): void
    {
        $response =
            $this->postJson(
                '/api/v1/auth/customers/login',
                []
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'message',
                'Validation failed.'
            )
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_unknown_api_route_uses_safe_not_found_contract(): void
    {
        $response =
            $this->getJson(
                '/api/v1/this-route-does-not-exist'
            );

        $response
            ->assertNotFound()
            ->assertJson([
                'success' =>
                    false,

                'message' =>
                    'The requested resource was not found.',
            ]);

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_method_not_allowed_uses_safe_contract(): void
    {
        $response =
            $this->postJson(
                '/api/v1/admin/reports/overview'
            );

        $response
            ->assertStatus(
                405
            )
            ->assertJson([
                'success' =>
                    false,

                'message' =>
                    'The requested method is not allowed.',
            ]);

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_forbidden_role_response_uses_standard_contract(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $response =
            $this
                ->actingAs(
                    $customer,
                    'sanctum'
                )
                ->getJson(
                    '/api/v1/admin/dashboard'
                );

        $response
            ->assertForbidden()
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertNotEmpty(
            $response->json(
                'message'
            )
        );

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_business_conflict_response_uses_standard_contract(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->suspended()
                ->create();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'sanctum'
                )
                ->patchJson(
                    "/api/v1/admin/users/{$customer->id}/suspend"
                );

        $response
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertNotEmpty(
            $response->json(
                'message'
            )
        );

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_rate_limited_response_uses_standard_contract(): void
    {
        $email =
            'api-contract-rate-limit@example.com';

        User::factory()
            ->customer()
            ->create([
                'email' =>
                    $email,

                'password' =>
                    'StyleHub#Secure2026',
            ]);

        $response = null;

        for (
            $attempt = 1;
            $attempt <= 6;
            $attempt++
        ) {
            $response =
                $this->postJson(
                    '/api/v1/auth/customers/login',
                    [
                        'email' =>
                            $email,

                        'password' =>
                            'DefinitelyWrong#2026',

                        'device_name' =>
                            'API Error Contract Test',
                    ]
                );
        }

        $this->assertNotNull(
            $response
        );

        $response
            ->assertStatus(
                429
            )
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertNotEmpty(
            $response->json(
                'message'
            )
        );

        $this->assertNoDebugDetails(
            $response->getContent()
        );
    }

    public function test_unexpected_api_exception_is_sanitized_even_when_debug_is_enabled(): void
    {
        /*
         * This route exists only inside this automated test.
         *
         * It is never added to the real StyleHub routes file.
         */

        Route::middleware(
            'api'
        )
            ->get(
                '/api/v1/_test/error-contract/server-error',
                static function (): never {
                    throw new RuntimeException(
                        'Sensitive internal test exception.'
                    );
                }
            );

        $response =
            $this->getJson(
                '/api/v1/_test/error-contract/server-error'
            );

        $response
            ->assertStatus(
                500
            )
            ->assertJson([
                'success' =>
                    false,

                'message' =>
                    'An unexpected error occurred.',
            ]);

        $content =
            $response->getContent();

        $this->assertStringNotContainsString(
            'Sensitive internal test exception.',
            $content
        );

        $this->assertStringNotContainsString(
            RuntimeException::class,
            $content
        );

        $this->assertNoDebugDetails(
            $content
        );
    }

    private function assertNoDebugDetails(
        string $content
    ): void {
        $lower =
            strtolower(
                $content
            );

        $this->assertStringNotContainsString(
            '"exception"',
            $lower
        );

        $this->assertStringNotContainsString(
            '"trace"',
            $lower
        );

        $this->assertStringNotContainsString(
            '"file"',
            $lower
        );

        $this->assertStringNotContainsString(
            'vendor\\\\laravel\\\\framework',
            $lower
        );

        $this->assertStringNotContainsString(
            'c:\\\\wamp64',
            $lower
        );
    }
}