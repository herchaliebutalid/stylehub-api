<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\ShopAvailabilityStatus;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\PhoneVerificationCode;
use App\Models\Shop;
use App\Models\User;
use App\Services\Sms\SemaphoreSmsGateway;
use App\Services\Sms\SmsSendResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class PhoneVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.timezone' =>
                'Asia/Manila',

            'phone_verification.expiration_minutes' =>
                5,

            'phone_verification.max_attempts' =>
                5,

            'phone_verification.resend_cooldown_seconds' =>
                60,
        ]);

        CarbonImmutable::setTestNow(
            CarbonImmutable::create(
                year: 2026,
                month: 9,
                day: 7,
                hour: 8,
                minute: 0,
                second: 0,
                timezone:
                    'Asia/Manila',
            )
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_send_code_requires_authentication(): void
    {
        $this->postJson(
            '/api/v1/auth/phone-verification/send'
        )
            ->assertUnauthorized();
    }

    public function test_verify_code_requires_authentication(): void
    {
        $this->postJson(
            '/api/v1/auth/phone-verification/verify',
            [
                'code' =>
                    '123456',
            ]
        )
            ->assertUnauthorized();
    }

    public function test_new_customer_registration_starts_with_unverified_phone(): void
    {
        $response =
            $this->postJson(
                '/api/v1/auth/customers/register',
                $this
                    ->customerRegistrationPayload()
            );

        $response
            ->assertCreated()

            ->assertJsonPath(
                'data.user.phone_verified',
                false
            );

        $user =
            User::query()
                ->where(
                    'email',
                    'customer@example.com'
                )
                ->firstOrFail();

        $this->assertNull(
            $user->phone_verified_at
        );
    }

    public function test_duplicate_phone_number_registration_is_rejected(): void
    {
        User::factory()
            ->customer()
            ->create([
                'phone_number' =>
                    '+639171234567',
            ]);

        $this->postJson(
            '/api/v1/auth/customers/register',
            $this
                ->customerRegistrationPayload()
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'phone_number',
            ]);
    }

    public function test_unverified_customer_can_request_verification_code(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171234567',
                ]);

        $capturedCode =
            null;

        $this->fakeSuccessfulSmsGateway(
            $capturedCode
        );

        $response =
            $this
                ->actingAs(
                    $user,
                    'sanctum'
                )
                ->postJson(
                    '/api/v1/auth/phone-verification/send'
                );

        $response
            ->assertOk()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonPath(
                'data.phone_number',
                '0917***4567'
            )

            ->assertJsonPath(
                'data.resend_after_seconds',
                60
            );

        $this->assertNotNull(
            $capturedCode
        );

        $this->assertMatchesRegularExpression(
            '/^\d{6}$/',
            $capturedCode
        );

        $verification =
            PhoneVerificationCode::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->firstOrFail();

        /*
         * Database contains a hash, not the plaintext OTP.
         */
        $this->assertNotSame(
            $capturedCode,
            $verification->code_hash
        );

        $this->assertStringNotContainsString(
            $capturedCode,
            $verification->code_hash
        );
    }

    public function test_correct_code_verifies_phone_and_deletes_code_record(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create([
                    'phone_number' =>
                        '+639171234567',
                ]);

        $capturedCode =
            null;

        $this->fakeSuccessfulSmsGateway(
            $capturedCode
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/send'
            )
            ->assertOk();

        $this->assertNotNull(
            $capturedCode
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/verify',
                [
                    'code' =>
                        $capturedCode,
                ]
            )
            ->assertOk()

            ->assertJsonPath(
                'data.user.phone_verified',
                true
            );

        $this->assertNotNull(
            $user
                ->fresh()
                ->phone_verified_at
        );

        /*
         * OTP is one-time use.
         */
        $this->assertDatabaseMissing(
            'phone_verification_codes',
            [
                'user_id' =>
                    $user->id,
            ]
        );
    }

    public function test_wrong_code_increments_attempt_count(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $capturedCode =
            null;

        $this->fakeSuccessfulSmsGateway(
            $capturedCode
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/send'
            )
            ->assertOk();

        $wrongCode =
            $capturedCode ===
                '000000'
                ? '111111'
                : '000000';

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/verify',
                [
                    'code' =>
                        $wrongCode,
                ]
            )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertDatabaseHas(
            'phone_verification_codes',
            [
                'user_id' =>
                    $user->id,

                'attempts' =>
                    1,
            ]
        );

        $this->assertNull(
            $user
                ->fresh()
                ->phone_verified_at
        );
    }

    public function test_fifth_wrong_attempt_invalidates_code(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $capturedCode =
            null;

        $this->fakeSuccessfulSmsGateway(
            $capturedCode
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/send'
            )
            ->assertOk();

        $wrongCode =
            $capturedCode ===
                '000000'
                ? '111111'
                : '000000';

        for (
            $attempt = 1;
            $attempt <= 5;
            $attempt++
        ) {
            $this
                ->actingAs(
                    $user,
                    'sanctum'
                )
                ->postJson(
                    '/api/v1/auth/phone-verification/verify',
                    [
                        'code' =>
                            $wrongCode,
                    ]
                )
                ->assertUnprocessable();
        }

        $this->assertDatabaseMissing(
            'phone_verification_codes',
            [
                'user_id' =>
                    $user->id,
            ]
        );

        $this->assertNull(
            $user
                ->fresh()
                ->phone_verified_at
        );
    }

    public function test_expired_code_cannot_verify_phone(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $capturedCode =
            null;

        $this->fakeSuccessfulSmsGateway(
            $capturedCode
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/send'
            )
            ->assertOk();

        CarbonImmutable::setTestNow(
            now()->addMinutes(6)
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/verify',
                [
                    'code' =>
                        $capturedCode,
                ]
            )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'code',
            ]);

        $this->assertNull(
            $user
                ->fresh()
                ->phone_verified_at
        );
    }

    public function test_unverified_customer_is_blocked_from_customer_features(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->getJson(
                '/api/v1/customer/shops'
            )
            ->assertForbidden()

            ->assertJsonPath(
                'message',
                'Please verify your phone number before using customer features.'
            );
    }

    public function test_verified_customer_can_access_customer_features(): void
    {
        $user =
            User::factory()
                ->customer()
                ->create();

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->getJson(
                '/api/v1/customer/shops'
            )
            ->assertOk();
    }

    public function test_unverified_shop_owner_is_blocked_from_shop_management(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->unverifiedPhone()
                ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' =>
                    $owner->id,

                'availability_status' =>
                    ShopAvailabilityStatus::Open,
            ]);

        $this
            ->actingAs(
                $owner,
                'sanctum'
            )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertForbidden()

            ->assertJsonPath(
                'message',
                'Please verify your phone number before managing your shop.'
            );
    }

    public function test_definite_sms_failure_does_not_leave_valid_code(): void
    {
        $user =
            User::factory()
                ->customer()
                ->unverifiedPhone()
                ->create();

        $this->mock(
            SemaphoreSmsGateway::class,
            function (
                MockInterface $mock
            ): void {
                $mock
                    ->shouldReceive(
                        'send'
                    )
                    ->once()
                    ->andThrow(
                        SmsGatewayException::definite(
                            'Provider rejected request.'
                        )
                    );
            }
        );

        $this
            ->actingAs(
                $user,
                'sanctum'
            )
            ->postJson(
                '/api/v1/auth/phone-verification/send'
            )
            ->assertStatus(
                503
            );

        $this->assertDatabaseMissing(
            'phone_verification_codes',
            [
                'user_id' =>
                    $user->id,
            ]
        );
    }

    /**
     * Replace the real Semaphore gateway during automated testing.
     *
     * No real SMS and no Semaphore credits are used.
     */
    private function fakeSuccessfulSmsGateway(
        ?string &$capturedCode
    ): void {
        $this->mock(
            SemaphoreSmsGateway::class,
            function (
                MockInterface $mock
            ) use (
                &$capturedCode
            ): void {
                $mock
                    ->shouldReceive(
                        'send'
                    )
                    ->once()
                    ->andReturnUsing(
                        function (
                            string $number,
                            string $message
                        ) use (
                            &$capturedCode
                        ): SmsSendResult {
                            $this
                                ->assertMatchesRegularExpression(
                                    '/^09\d{9}$/',
                                    $number
                                );

                            preg_match(
                                '/\b(\d{6})\b/',
                                $message,
                                $matches
                            );

                            $capturedCode =
                                $matches[1]
                                ?? null;

                            return new SmsSendResult(
                                providerMessageId:
                                    'otp-test-1',

                                providerStatus:
                                    'Queued',
                            );
                        }
                    );
            }
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function customerRegistrationPayload(): array
    {
        return [
            'first_name' =>
                'Juan',

            'middle_name' =>
                'Santos',

            'last_name' =>
                'Dela Cruz',

            'email' =>
                'customer@example.com',

            'phone_number' =>
                '09171234567',

            'password' =>
                'SecurePassword123!',

            'password_confirmation' =>
                'SecurePassword123!',

            'device_name' =>
                'StyleHub Test Device',
        ];
    }
}