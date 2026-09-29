<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_factory_creates_a_valid_active_customer(): void
    {
        $user = User::factory()
            ->customer()
            ->create();

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertInstanceOf(UserStatus::class, $user->status);

        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);

        $this->assertTrue($user->isCustomer());
        $this->assertTrue($user->isActive());

        $this->assertFalse($user->isShopOwner());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isSuspended());
    }

    public function test_shop_owner_factory_creates_shop_owner_role(): void
    {
        $user = User::factory()
            ->shopOwner()
            ->create();

        $this->assertSame(UserRole::ShopOwner, $user->role);
        $this->assertTrue($user->isShopOwner());
    }

    public function test_admin_factory_creates_admin_role(): void
    {
        $user = User::factory()
            ->admin()
            ->create();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->isAdmin());
    }

    public function test_suspended_factory_creates_suspended_account(): void
    {
        $user = User::factory()
            ->suspended()
            ->create();

        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertTrue($user->isSuspended());
        $this->assertFalse($user->isActive());
    }

    public function test_password_is_hashed_before_it_is_stored(): void
    {
        $plainPassword = 'StyleHub-Secure-Password-123!';

        $user = User::factory()->create([
            'password' => $plainPassword,
        ]);

        $this->assertNotSame(
            $plainPassword,
            $user->getRawOriginal('password')
        );

        $this->assertTrue(
            Hash::check(
                $plainPassword,
                $user->getRawOriginal('password')
            )
        );
    }

    public function test_password_and_remember_token_are_hidden_from_json(): void
    {
        $user = User::factory()->create();

        $data = $user->toArray();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_role_and_status_cannot_be_mass_assigned(): void
    {
        $user = new User();

        $user->fill([
            'first_name' => 'Attacker',
            'middle_name' => null,
            'last_name' => 'Attempt',
            'email' => 'attacker@example.com',
            'phone_number' => '09123456789',
            'password' => 'VerySecurePassword123!',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Suspended->value,
        ]);

        $this->assertNull($user->role);
        $this->assertNull($user->status);
    }
}