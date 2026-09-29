<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Models\Promo;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(
            '2026-08-20 12:00:00'
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_promo_discovery_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/customer/promos'
        )->assertUnauthorized();
    }

    public function test_shop_owner_cannot_use_customer_promo_discovery(): void
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
                '/api/v1/customer/promos'
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_use_customer_promo_discovery(): void
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
                '/api/v1/customer/promos'
            )
            ->assertForbidden();
    }

    public function test_customer_sees_currently_active_promo(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $promo =
            $this->currentPromo(
                $shop,
                'Current Promotion'
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.promos'
            )
            ->assertJsonPath(
                'data.promos.0.id',
                $promo->id
            )
            ->assertJsonPath(
                'data.promos.0.title',
                'Current Promotion'
            );
    }

    public function test_upcoming_promo_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Promo::factory()
            ->upcoming()
            ->create([
                'shop_id' =>
                    $shop->id,

                'title' =>
                    'Upcoming Promotion',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_expired_promo_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Promo::factory()
            ->expired()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_manually_disabled_promo_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Promo::factory()
            ->inactive()
            ->create([
                'shop_id' =>
                    $shop->id,

                'starts_at' =>
                    '2026-08-19 00:00:00',

                'ends_at' =>
                    '2026-08-21 23:59:59',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_soft_deleted_promo_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $promo =
            $this->currentPromo(
                $shop
            );

        $promo->delete();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_service_specific_promo_for_inactive_service_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $service =
            Service::factory()
                ->inactive()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $this->currentPromo(
            shop: $shop,
            serviceId: $service->id
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_service_specific_promo_for_soft_deleted_service_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $this->currentPromo(
            shop: $shop,
            serviceId: $service->id
        );

        $service->delete();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_promo_from_pending_shop_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        $shop =
            Shop::factory()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->currentPromo(
            $shop
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_promo_from_shop_with_suspended_owner_is_hidden(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $owner =
            User::factory()
                ->shopOwner()
                ->suspended()
                ->create();

        $shop =
            Shop::factory()
                ->approved()
                ->create([
                    'owner_id' =>
                        $owner->id,
                ]);

        $this->currentPromo(
            $shop
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.promos'
            );
    }

    public function test_customer_can_filter_current_promos_by_shop(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $firstShop] =
            $this->visibleShop(
                'First Shop'
            );

        [, $secondShop] =
            $this->visibleShop(
                'Second Shop'
            );

        $this->currentPromo(
            $firstShop,
            'First Promotion'
        );

        $this->currentPromo(
            $secondShop,
            'Second Promotion'
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos?shop_id='
                .$secondShop->id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.promos'
            )
            ->assertJsonPath(
                'data.promos.0.title',
                'Second Promotion'
            );
    }

    public function test_customer_can_search_current_promos(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $this->currentPromo(
            $shop,
            'Haircut Weekend Deal'
        );

        $this->currentPromo(
            $shop,
            'Beauty Treatment Special'
        );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/promos?search=Haircut'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.promos'
            )
            ->assertJsonPath(
                'data.promos.0.title',
                'Haircut Weekend Deal'
            );
    }

    public function test_customer_cannot_query_internal_promo_fields(): void
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
                '/api/v1/customer/promos?is_active=0'
            )
            ->assertUnprocessable();
    }

    public function test_customer_cannot_request_excessive_promo_page_size(): void
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
                '/api/v1/customer/promos?per_page=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_customer_can_view_current_promo_detail(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $promo =
            $this->currentPromo(
                $shop,
                'Visible Promotion'
            );

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/promos/{$promo->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.promo.id',
                $promo->id
            )
            ->assertJsonPath(
                'data.promo.title',
                'Visible Promotion'
            )
            ->assertJsonMissingPath(
                'data.promo.is_active'
            )
            ->assertJsonMissingPath(
                'data.promo.deleted_at'
            );
    }

    public function test_hidden_promo_detail_returns_not_found(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $promo =
            Promo::factory()
                ->expired()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/promos/{$promo->id}"
            )
            ->assertNotFound();
    }

    public function test_shop_detail_contains_only_current_customer_visible_promos(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $this->currentPromo(
            $shop,
            'Visible Promotion'
        );

        Promo::factory()
            ->upcoming()
            ->create([
                'shop_id' =>
                    $shop->id,

                'title' =>
                    'Upcoming Promotion',
            ]);

        Promo::factory()
            ->expired()
            ->create([
                'shop_id' =>
                    $shop->id,

                'title' =>
                    'Expired Promotion',
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.shop.promos'
            )
            ->assertJsonPath(
                'data.shop.promos.0.title',
                'Visible Promotion'
            );
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function visibleShop(
        string $name = 'StyleHub Shop'
    ): array {
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

                    'name' =>
                        $name,
                ]);

        return [
            $owner,
            $shop,
        ];
    }

    private function currentPromo(
        Shop $shop,
        string $title = 'Current Promotion',
        ?int $serviceId = null
    ): Promo {
        return Promo::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $serviceId,

                'title' =>
                    $title,

                'starts_at' =>
                    '2026-08-19 00:00:00',

                'ends_at' =>
                    '2026-08-21 23:59:59',

                'is_active' =>
                    true,
            ]);
    }
}