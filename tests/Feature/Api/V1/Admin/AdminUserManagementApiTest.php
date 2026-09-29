<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/admin/users'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_user_management(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users'
            )
            ->assertForbidden();
    }

    public function test_shop_owner_cannot_access_admin_user_management(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users'
            )
            ->assertForbidden();
    }

    public function test_admin_can_browse_customers_and_shop_owners_only(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->create();

        User::factory()
            ->shopOwner()
            ->create();

        User::factory()
            ->admin()
            ->create();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                '/api/v1/admin/users'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.users'
            )
            ->assertJsonPath(
                'data.pagination.total',
                2
            );

        foreach (
            $response->json(
                'data.users'
            ) as $user
        ) {
            $this->assertContains(
                $user['role'],
                [
                    UserRole::Customer->value,
                    UserRole::ShopOwner->value,
                ]
            );
        }
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->count(2)
            ->create();

        User::factory()
            ->shopOwner()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?role=customer'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.users'
            );
    }

    public function test_admin_cannot_filter_for_administrator_role(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?role=admin'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'role',
            ]);
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->create([
                'status' =>
                    UserStatus::Active,
            ]);

        User::factory()
            ->customer()
            ->suspended()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?status=suspended'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.users'
            )
            ->assertJsonPath(
                'data.users.0.status',
                UserStatus::Suspended->value
            );
    }

    public function test_admin_can_search_users(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->create([
                'first_name' =>
                    'Herchalie',

                'last_name' =>
                    'Butalid',

                'email' =>
                    'herchalie@example.com',
            ]);

        User::factory()
            ->customer()
            ->create([
                'first_name' =>
                    'Juan',

                'last_name' =>
                    'Cruz',
            ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?search=Herchalie'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.users'
            )
            ->assertJsonPath(
                'data.users.0.email',
                'herchalie@example.com'
            );
    }

    public function test_admin_user_list_is_paginated(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        User::factory()
            ->customer()
            ->count(3)
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?per_page=2'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.users'
            )
            ->assertJsonPath(
                'data.pagination.total',
                3
            )
            ->assertJsonPath(
                'data.pagination.per_page',
                2
            );
    }

    public function test_excessive_user_page_size_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_unknown_user_query_parameter_is_rejected(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/admin/users?password=test'
            )
            ->assertUnprocessable();
    }

    public function test_admin_can_view_customer_detail(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'email' =>
                        'detail@example.com',
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/users/{$customer->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $customer->id
            )
            ->assertJsonPath(
                'data.user.email',
                'detail@example.com'
            )
            ->assertJsonPath(
                'data.user.role',
                UserRole::Customer->value
            );
    }

    public function test_admin_account_cannot_be_accessed_through_managed_user_endpoint(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $otherAdmin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                "/api/v1/admin/users/{$otherAdmin->id}"
            )
            ->assertNotFound();
    }

    public function test_admin_can_suspend_customer(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create([
                    'status' =>
                        UserStatus::Active,
                ]);

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.user.status',
                UserStatus::Suspended->value
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $customer->id,

                'status' =>
                    UserStatus::Suspended->value,
            ]
        );
    }

    public function test_suspending_user_revokes_all_existing_tokens(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $customer->createToken(
            'Mobile 1'
        );

        $customer->createToken(
            'Mobile 2'
        );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2
        );

        $this->actingAs(
            $admin,
            'sanctum'
        )->patchJson(
            "/api/v1/admin/users/{$customer->id}/suspend"
        )->assertOk();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_already_suspended_user_cannot_be_suspended_again(): void
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

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/suspend"
            )
            ->assertStatus(
                409
            );
    }

    public function test_admin_can_reactivate_suspended_user(): void
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

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/reactivate"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.user.status',
                UserStatus::Active->value
            );

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $customer->id,

                'status' =>
                    UserStatus::Active->value,
            ]
        );
    }

    public function test_already_active_user_cannot_be_reactivated(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$customer->id}/reactivate"
            )
            ->assertStatus(
                409
            );
    }

    public function test_admin_cannot_suspend_another_administrator(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $otherAdmin =
            User::factory()
                ->admin()
                ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->patchJson(
                "/api/v1/admin/users/{$otherAdmin->id}/suspend"
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $otherAdmin->id,

                'status' =>
                    UserStatus::Active->value,
            ]
        );
    }

    public function test_admin_user_response_does_not_expose_password_or_tokens(): void
    {
        $admin =
            User::factory()
                ->admin()
                ->create();

        $customer =
            User::factory()
                ->customer()
                ->create();

        $response =
            $this->actingAs(
                $admin,
                'sanctum'
            )->getJson(
                "/api/v1/admin/users/{$customer->id}"
            );

        $response
            ->assertOk()
            ->assertJsonMissingPath(
                'data.user.password'
            )
            ->assertJsonMissingPath(
                'data.user.remember_token'
            );

        $content =
            strtolower(
                $response->getContent()
            );

        $this->assertStringNotContainsString(
            '"password"',
            $content
        );

        $this->assertStringNotContainsString(
            'remember_token',
            $content
        );

        $this->assertStringNotContainsString(
            'plain_text_token',
            $content
        );
    }
}