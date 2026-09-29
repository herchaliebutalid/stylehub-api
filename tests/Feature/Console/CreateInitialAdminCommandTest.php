<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateInitialAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_administrator_can_be_created_securely(): void
    {
        $this->artisan(
            'stylehub:admin:create'
        )
            ->expectsQuestion(
                'First name',
                'System'
            )
            ->expectsQuestion(
                'Middle name (optional)',
                ''
            )
            ->expectsQuestion(
                'Last name',
                'Administrator'
            )
            ->expectsQuestion(
                'Email',
                'ADMIN@STYLEHUB.TEST'
            )
            ->expectsQuestion(
                'Philippine mobile number',
                '0917-123-4567'
            )
            ->expectsQuestion(
                'Password',
                'SecureAdmin123!'
            )
            ->expectsQuestion(
                'Confirm password',
                'SecureAdmin123!'
            )
            ->expectsConfirmation(
                'Create this administrator account?',
                'yes'
            )
            ->expectsOutput(
                'StyleHub administrator created successfully.'
            )
            ->assertSuccessful();

        $admin = User::query()
            ->where(
                'email',
                'admin@stylehub.test'
            )
            ->firstOrFail();

        $this->assertSame(
            UserRole::Admin,
            $admin->role
        );

        $this->assertSame(
            UserStatus::Active,
            $admin->status
        );

        $this->assertSame(
            '+639171234567',
            $admin->phone_number
        );

        $this->assertTrue(
            Hash::check(
                'SecureAdmin123!',
                $admin->getRawOriginal(
                    'password'
                )
            )
        );

        $this->assertNotSame(
            'SecureAdmin123!',
            $admin->getRawOriginal(
                'password'
            )
        );
    }

    public function test_bootstrap_command_refuses_to_create_second_administrator(): void
    {
        User::factory()
            ->admin()
            ->create();

        $this->artisan(
            'stylehub:admin:create'
        )
            ->expectsOutputToContain(
                'An administrator account already exists.'
            )
            ->assertFailed();

        $this->assertDatabaseCount(
            'users',
            1
        );

        $this->assertSame(
            1,
            User::query()
                ->where(
                    'role',
                    UserRole::Admin->value
                )
                ->count()
        );
    }

    public function test_weak_administrator_password_is_rejected(): void
    {
        $this->artisan(
            'stylehub:admin:create'
        )
            ->expectsQuestion(
                'First name',
                'System'
            )
            ->expectsQuestion(
                'Middle name (optional)',
                ''
            )
            ->expectsQuestion(
                'Last name',
                'Administrator'
            )
            ->expectsQuestion(
                'Email',
                'admin@stylehub.test'
            )
            ->expectsQuestion(
                'Philippine mobile number',
                '09171234567'
            )
            ->expectsQuestion(
                'Password',
                'password'
            )
            ->expectsQuestion(
                'Confirm password',
                'password'
            )
            ->expectsOutputToContain(
                'Administrator account was not created because validation failed.'
            )
            ->assertFailed();

        $this->assertDatabaseCount(
            'users',
            0
        );
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $this->artisan(
            'stylehub:admin:create'
        )
            ->expectsQuestion(
                'First name',
                'System'
            )
            ->expectsQuestion(
                'Middle name (optional)',
                ''
            )
            ->expectsQuestion(
                'Last name',
                'Administrator'
            )
            ->expectsQuestion(
                'Email',
                'admin@stylehub.test'
            )
            ->expectsQuestion(
                'Philippine mobile number',
                '09171234567'
            )
            ->expectsQuestion(
                'Password',
                'SecureAdmin123!'
            )
            ->expectsQuestion(
                'Confirm password',
                'DifferentAdmin123!'
            )
            ->expectsOutputToContain(
                'The password confirmation does not match.'
            )
            ->assertFailed();

        $this->assertDatabaseCount(
            'users',
            0
        );
    }
}