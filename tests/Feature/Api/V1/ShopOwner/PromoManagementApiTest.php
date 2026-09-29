<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\PromoDiscountType;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/promos'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_promos(): void
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
                '/api/v1/shop-owner/promos'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_manage_promos(): void
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
                '/api/v1/shop-owner/promos'
            )
            ->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_promos(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()->create([
            'owner_id' =>
                $owner->id,
        ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/promos'
            )
            ->assertForbidden();
    }

    public function test_owner_lists_only_own_shop_promos(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        Promo::factory()
            ->count(2)
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        Promo::factory()->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                '/api/v1/shop-owner/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.promos'
            );
    }

    public function test_owner_can_create_shop_wide_promo(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )->postJson(
                '/api/v1/shop-owner/promos',
                $this->validPayload()
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.promo.scope',
                'shop_wide'
            )
            ->assertJsonPath(
                'data.promo.title',
                'Weekend Special'
            )
            ->assertJsonPath(
                'data.promo.discount.type',
                PromoDiscountType::Percentage
                    ->value
            )
            ->assertJsonPath(
                'data.promo.discount.value',
                '15.00'
            );

        $this->assertDatabaseHas(
            'promos',
            [
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    null,

                'title' =>
                    'Weekend Special',

                'discount_type' =>
                    PromoDiscountType::Percentage
                        ->value,

                'discount_value' =>
                    '15.00',

                'is_active' =>
                    true,
            ]
        );
    }

    public function test_owner_can_create_service_specific_promo(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Premium Haircut',
                ]);

        $payload =
            $this->validPayload();

        $payload['service_id'] =
            $service->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.promo.scope',
                'service_specific'
            )
            ->assertJsonPath(
                'data.promo.service.id',
                $service->id
            );

        $this->assertDatabaseHas(
            'promos',
            [
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,
            ]
        );
    }

    public function test_owner_cannot_target_another_shops_service(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $otherService =
            Service::factory()
                ->create();

        $payload =
            $this->validPayload();

        $payload['service_id'] =
            $otherService->id;

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'service_id',
            ]);

        $this->assertDatabaseCount(
            'promos',
            0
        );
    }

    public function test_owner_cannot_inject_shop_id(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $payload['shop_id'] =
            999;

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'promos',
            0
        );
    }

    public function test_percentage_discount_cannot_exceed_one_hundred(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $payload['discount_value'] =
            '100.01';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'discount_value',
            ]);
    }

    public function test_fixed_amount_discount_may_exceed_one_hundred(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $payload['discount_type'] =
            PromoDiscountType::FixedAmount
                ->value;

        $payload['discount_value'] =
            '250.00';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.promo.discount.value',
                '250.00'
            );
    }

    public function test_promo_end_time_must_be_after_start_time(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $payload['starts_at'] =
            '2026-08-30 10:00:00';

        $payload['ends_at'] =
            '2026-08-29 10:00:00';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ends_at',
            ]);
    }

    public function test_owner_can_view_own_promo(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $promo =
            Promo::factory()->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/shop-owner/promos/{$promo->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.promo.id',
                $promo->id
            );
    }

    public function test_owner_cannot_view_another_shops_promo(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $promo =
            Promo::factory()->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->getJson(
                "/api/v1/shop-owner/promos/{$promo->id}"
            )
            ->assertNotFound();
    }

    public function test_owner_can_update_own_promo(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $promo =
            Promo::factory()->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $payload =
            $this->validPayload();

        $payload['service_id'] =
            $service->id;

        $payload['title'] =
            'Updated Promotion';

        $payload['discount_type'] =
            PromoDiscountType::FixedAmount
                ->value;

        $payload['discount_value'] =
            '200.00';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->putJson(
                "/api/v1/shop-owner/promos/{$promo->id}",
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.promo.title',
                'Updated Promotion'
            )
            ->assertJsonPath(
                'data.promo.scope',
                'service_specific'
            )
            ->assertJsonPath(
                'data.promo.service.id',
                $service->id
            );

        $updated =
            $promo->fresh();

        $this->assertSame(
            'Updated Promotion',
            $updated->title
        );

        $this->assertSame(
            $service->id,
            $updated->service_id
        );

        $this->assertSame(
            PromoDiscountType::FixedAmount,
            $updated->discount_type
        );

        $this->assertSame(
            '200.00',
            $updated->discount_value
        );
    }

    public function test_owner_cannot_update_another_shops_promo(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $promo =
            Promo::factory()->create();

        $payload =
            $this->validPayload();

        $payload['title'] =
            'Unauthorized Change';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->putJson(
                "/api/v1/shop-owner/promos/{$promo->id}",
                $payload
            )
            ->assertNotFound();

        $this->assertNotSame(
            'Unauthorized Change',
            $promo->fresh()->title
        );
    }

    public function test_owner_can_soft_delete_own_promo(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $promo =
            Promo::factory()->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->deleteJson(
                "/api/v1/shop-owner/promos/{$promo->id}"
            )
            ->assertOk();

        $this->assertSoftDeleted(
            'promos',
            [
                'id' =>
                    $promo->id,
            ]
        );
    }

    public function test_owner_cannot_delete_another_shops_promo(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $promo =
            Promo::factory()->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->deleteJson(
                "/api/v1/shop-owner/promos/{$promo->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas(
            'promos',
            [
                'id' =>
                    $promo->id,

                'deleted_at' =>
                    null,
            ]
        );
    }

    public function test_invalid_discount_type_is_rejected(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $payload =
            $this->validPayload();

        $payload['discount_type'] =
            'malicious_discount_type';

        $this->actingAs(
            $owner,
            'sanctum'
        )
            ->postJson(
                '/api/v1/shop-owner/promos',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'discount_type',
            ]);
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(): array
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        return [
            $owner,
            $shop,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            /*
             * null = shop-wide promotion.
             */
            'service_id' =>
                null,

            'title' =>
                'Weekend Special',

            'description' =>
                'Save on selected StyleHub services this weekend.',

            'discount_type' =>
                PromoDiscountType::Percentage
                    ->value,

            'discount_value' =>
                '15.00',

            'starts_at' =>
                '2026-08-21 00:00:00',

            'ends_at' =>
                '2026-08-31 23:59:59',

            'is_active' =>
                true,
        ];
    }
}