<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserRole;
use App\Exceptions\Sms\SmsGatewayException;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Sms\SmsSendResult;
use App\Services\Sms\TextBeeSmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class PasswordSecurityApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Never allow these tests to contact the real SMS provider.
        Http::preventStrayRequests();

        config([
            'app.timezone' => 'Asia/Manila',
            'password_recovery.expiration_minutes' => 10,
            'password_recovery.max_attempts' => 5,
            'password_recovery.resend_cooldown_seconds' => 60,
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::create(
            year: 2026,
            month: 9,
            day: 8,
            hour: 9,
            minute: 0,
            second: 0,
            timezone: 'Asia/Manila',
        ));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_forgot_password_requires_mobile_role_and_valid_email(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'email']);
    }

    public function test_admin_role_cannot_use_mobile_password_recovery(): void
    {
        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Admin->value,
            'email' => 'admin@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors(['role']);
    }

    public function test_unknown_email_receives_generic_response_without_sms(): void
    {
        $this->mock(TextBeeSmsGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('send');
        });

        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Customer->value,
            'email' => 'missing@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resend_after_seconds', 60);

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_role_mismatch_receives_same_generic_response_without_sms(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.com',
        ]);

        $this->mock(TextBeeSmsGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('send');
        });

        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::ShopOwner->value,
            'email' => 'customer@example.com',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_active_customer_can_request_password_reset_code(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'phone_number' => '+639171234567',
        ]);

        $capturedCode = null;
        $this->fakeSuccessfulSmsGateway($capturedCode);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Customer->value,
            'email' => 'CUSTOMER@EXAMPLE.COM ',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.resend_after_seconds', 60)
            ->assertJsonMissingPath('data.phone_number')
            ->assertJsonMissingPath('data.code');

        $this->assertNotNull($capturedCode);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedCode);

        $reset = PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertSame('customer@example.com', $reset->email);
        $this->assertSame('09171234567', $reset->phone_number);
        $this->assertNotSame($capturedCode, $reset->code_hash);
        $this->assertStringNotContainsString($capturedCode, $reset->code_hash);
    }

    public function test_suspended_account_does_not_receive_password_reset_sms(): void
    {
        User::factory()->customer()->suspended()->create([
            'email' => 'customer@example.com',
        ]);

        $this->mock(TextBeeSmsGateway::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('send');
        });

        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Customer->value,
            'email' => 'customer@example.com',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_wrong_reset_code_increments_attempt_count(): void
    {
        [$user, $capturedCode] = $this->preparePasswordReset();
        $wrongCode = $capturedCode === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/auth/password/reset', $this->resetPayload(
            code: $wrongCode
        ))->assertUnprocessable()->assertJsonValidationErrors(['code']);

        $this->assertDatabaseHas('password_reset_codes', [
            'user_id' => $user->id,
            'attempts' => 1,
        ]);
    }

    public function test_fifth_wrong_reset_attempt_invalidates_code(): void
    {
        [$user, $capturedCode] = $this->preparePasswordReset();
        $wrongCode = $capturedCode === '000000' ? '111111' : '000000';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/password/reset', $this->resetPayload(
                code: $wrongCode
            ))->assertUnprocessable();
        }

        $this->assertDatabaseMissing('password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_expired_reset_code_is_rejected(): void
    {
        [, $capturedCode] = $this->preparePasswordReset();

        CarbonImmutable::setTestNow(now()->addMinutes(11));

        $this->postJson('/api/v1/auth/password/reset', $this->resetPayload(
            code: $capturedCode
        ))->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_correct_reset_code_changes_password_revokes_tokens_and_is_one_time_use(): void
    {
        [$user, $capturedCode] = $this->preparePasswordReset();

        $user->createToken('existing-session-one');
        $user->createToken('existing-session-two');
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->postJson('/api/v1/auth/password/reset', $this->resetPayload(
            code: $capturedCode
        ))->assertOk()->assertJsonPath('success', true);

        $freshUser = $user->fresh();

        $this->assertTrue(Hash::check('NewSecurePassword456!', $freshUser->password));
        $this->assertFalse(Hash::check('OldSecurePassword123!', $freshUser->password));

        // Recovery revokes all sessions, and the reset code can be used only once.
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('password_reset_codes', [
            'user_id' => $user->id,
        ]);

        $this->postJson('/api/v1/auth/password/reset', $this->resetPayload(
            code: $capturedCode
        ))->assertUnprocessable()->assertJsonValidationErrors(['code']);
    }

    public function test_definite_sms_failure_returns_generic_response_and_removes_code(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'customer@example.com',
        ]);

        $this->mock(TextBeeSmsGateway::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(SmsGatewayException::definite('Provider rejected request.'));
        });

        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Customer->value,
            'email' => 'customer@example.com',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('password_reset_codes', [
            'user_id' => $user->id,
        ]);
    }

    public function test_change_password_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/password/change', $this->changePasswordPayload())
            ->assertUnauthorized();
    }

    public function test_change_password_rejects_incorrect_current_password(): void
    {
        $user = User::factory()->customer()->create([
            'password' => 'OldSecurePassword123!',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/password/change', [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewSecurePassword456!',
                'password_confirmation' => 'NewSecurePassword456!',
            ])->assertUnprocessable()->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('OldSecurePassword123!', $user->fresh()->password));
    }

    public function test_change_password_keeps_current_token_and_revokes_other_tokens(): void
    {
        $user = User::factory()->customer()->create([
            'password' => 'OldSecurePassword123!',
        ]);

        $currentToken = $user->createToken(
            'current-device',
            ['role:customer'],
            now()->addDay()
        );

        $otherToken = $user->createToken(
            'other-device',
            ['role:customer'],
            now()->addDay()
        );

        $currentTokenId = $currentToken->accessToken->id;
        $otherTokenId = $otherToken->accessToken->id;

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/v1/auth/password/change', $this->changePasswordPayload())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('NewSecurePassword456!', $user->fresh()->password));

        // A password change keeps the current session but revokes other sessions.
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentTokenId,
            'tokenable_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherTokenId,
        ]);
    }

    public function test_change_password_rejects_reusing_current_password(): void
    {
        $user = User::factory()->customer()->create([
            'password' => 'OldSecurePassword123!',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/password/change', [
                'current_password' => 'OldSecurePassword123!',
                'password' => 'OldSecurePassword123!',
                'password_confirmation' => 'OldSecurePassword123!',
            ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function preparePasswordReset(): array
    {
        $user = User::factory()->customer()->create([
            'email' => 'customer@example.com',
            'phone_number' => '+639171234567',
            'password' => 'OldSecurePassword123!',
        ]);

        $capturedCode = null;
        $this->fakeSuccessfulSmsGateway($capturedCode);

        $this->postJson('/api/v1/auth/password/forgot', [
            'role' => UserRole::Customer->value,
            'email' => 'customer@example.com',
        ])->assertOk();

        $this->assertNotNull($capturedCode);

        return [$user, $capturedCode];
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(string $code): array
    {
        return [
            'role' => UserRole::Customer->value,
            'email' => 'customer@example.com',
            'code' => $code,
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function changePasswordPayload(): array
    {
        return [
            'current_password' => 'OldSecurePassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ];
    }

    /**
     * Use a mock of the production TextBee dependency.
     * No real SMS is sent during these tests.
     */
    private function fakeSuccessfulSmsGateway(?string &$capturedCode): void
    {
        $this->mock(TextBeeSmsGateway::class, function (MockInterface $mock) use (&$capturedCode): void {
            $mock->shouldReceive('send')
                ->once()
                ->andReturnUsing(function (
                    string $number,
                    string $message
                ) use (&$capturedCode): SmsSendResult {
                    $this->assertMatchesRegularExpression('/^09\d{9}$/', $number);

                    preg_match('/\b(\d{6})\b/', $message, $matches);
                    $capturedCode = $matches[1] ?? null;

                    return new SmsSendResult(
                        providerMessageId: 'password-reset-test-1',
                        providerStatus: 'Queued',
                    );
                });
        });
    }
}
