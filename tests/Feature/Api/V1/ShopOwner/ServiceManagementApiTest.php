<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/services'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_services(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_shop_owner_service_routes(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_services(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertForbidden();
    }

    public function test_rejected_shop_owner_cannot_manage_services(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()
            ->rejected()
            ->create([
                'owner_id' => $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/services'
            )
            ->assertForbidden();
    }

    public function test_approved_owner_lists_only_own_services(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        Service::factory()
            ->count(2)
            ->create([
                'shop_id' => $shop->id,
            ]);

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        Service::factory()
            ->count(3)
            ->create([
                'shop_id' => $otherShop->id,
            ]);

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/services'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonCount(
                2,
                'data.services'
            );
    }

    public function test_approved_owner_can_create_service_for_own_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/services',
            $this->validServicePayload()
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.service.name',
                'Classic Haircut'
            )
            ->assertJsonPath(
                'data.service.price',
                '250.00'
            )
            ->assertJsonPath(
                'data.service.duration_minutes',
                30
            )
            ->assertJsonPath(
                'data.service.is_active',
                true
            );

        $this->assertDatabaseHas(
            'services',
            [
                'shop_id' => $shop->id,
                'name' => 'Classic Haircut',
                'price' => '250.00',
                'duration_minutes' => 30,
                'is_active' => true,
            ]
        );
    }

    public function test_owner_cannot_inject_another_shop_id_when_creating_service(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $payload =
            $this->validServicePayload();

        $payload['shop_id'] =
            $otherShop->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/services',
            $payload
        )->assertUnprocessable();

        $this->assertDatabaseCount(
            'services',
            0
        );
    }

    public function test_owner_can_view_own_service(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $service = Service::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/shop-owner/services/{$service->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.service.id',
                $service->id
            );
    }

    public function test_owner_cannot_view_another_shops_service(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $otherService =
            Service::factory()->create([
                'shop_id' => $otherShop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/shop-owner/services/{$otherService->id}"
            )
            ->assertNotFound();
    }

    public function test_owner_can_update_own_service(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $service = Service::factory()
            ->create([
                'shop_id' => $shop->id,
                'name' => 'Old Service',
                'price' => '100.00',
                'duration_minutes' => 20,
            ]);

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/services/{$service->id}",
            [
                'name' => 'Premium Haircut',
                'price' => '450.50',
                'duration_minutes' => 60,
                'is_active' => false,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.service.name',
                'Premium Haircut'
            )
            ->assertJsonPath(
                'data.service.price',
                '450.50'
            )
            ->assertJsonPath(
                'data.service.duration_minutes',
                60
            )
            ->assertJsonPath(
                'data.service.is_active',
                false
            );

        $this->assertDatabaseHas(
            'services',
            [
                'id' => $service->id,
                'shop_id' => $shop->id,
                'name' => 'Premium Haircut',
                'price' => '450.50',
                'duration_minutes' => 60,
                'is_active' => false,
            ]
        );
    }

    public function test_owner_cannot_update_another_shops_service(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $otherService =
            Service::factory()->create([
                'shop_id' => $otherShop->id,
                'name' => 'Protected Service',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            "/api/v1/shop-owner/services/{$otherService->id}",
            [
                'name' => 'Hacked Service',
            ]
        )->assertNotFound();

        $this->assertSame(
            'Protected Service',
            $otherService->fresh()->name
        );
    }

    public function test_owner_can_soft_delete_own_service(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $service = Service::factory()
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->deleteJson(
                "/api/v1/shop-owner/services/{$service->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            );

        $this->assertSoftDeleted(
            'services',
            [
                'id' => $service->id,
            ]
        );
    }

    public function test_owner_cannot_delete_another_shops_service(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $otherService =
            Service::factory()->create([
                'shop_id' => $otherShop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->deleteJson(
                "/api/v1/shop-owner/services/{$otherService->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'services',
            [
                'id' => $otherService->id,
                'deleted_at' => null,
            ]
        );
    }

    public function test_invalid_price_and_duration_are_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validServicePayload();

        $payload['price'] = '-100.00';
        $payload['duration_minutes'] = 1;

        $this->actingAs(
            $owner,
            'sanctum'
        )->postJson(
            '/api/v1/shop-owner/services',
            $payload
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'price',
                'duration_minutes',
            ]);

        $this->assertDatabaseCount(
            'services',
            0
        );
    }

    /**
     * Create an approved shop-owner account and its shop.
     *
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(): array
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create([
                'owner_id' => $owner->id,
            ]);

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * Build valid service data.
     *
     * @return array<string, mixed>
     */
    private function validServicePayload(): array
    {
        return [
            'name' => 'Classic Haircut',

            'description' =>
                'Professional haircut service.',

            'price' => '250.00',

            'duration_minutes' => 30,

            'is_active' => true,
        ];
    }
}