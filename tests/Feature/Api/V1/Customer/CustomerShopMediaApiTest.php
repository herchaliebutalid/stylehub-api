<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Enums\ShopAvailabilityStatus;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopGalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerShopMediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'public'
        );
    }

    public function test_shop_list_stays_lightweight_and_does_not_include_gallery_collection(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        ShopGalleryImage::factory()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                '/api/v1/customer/shops'
            )
            ->assertOk()
            ->assertJsonMissingPath(
                'data.shops.0.gallery'
            );
    }

    public function test_shop_detail_exposes_ordered_customer_safe_gallery(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $later =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'image_path' =>
                        'shop-gallery/'
                        .$shop->id
                        .'/later.png',

                    'position' =>
                        20,
                ]);

        $first =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'image_path' =>
                        'shop-gallery/'
                        .$shop->id
                        .'/first.webp',

                    'position' =>
                        10,
                ]);

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.shop.gallery'
            )
            ->assertJsonPath(
                'data.shop.gallery.0.id',
                $first->id
            )
            ->assertJsonPath(
                'data.shop.gallery.0.position',
                10
            )
            ->assertJsonPath(
                'data.shop.gallery.1.id',
                $later->id
            )
            ->assertJsonMissingPath(
                'data.shop.gallery.0.shop_id'
            )
            ->assertJsonMissingPath(
                'data.shop.gallery.0.image_path'
            );

        $this->assertIsString(
            $response->json(
                'data.shop.gallery.0.image_url'
            )
        );
    }

    public function test_shop_detail_returns_empty_gallery_when_shop_has_no_images(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.gallery',
                []
            )
            ->assertJsonCount(
                0,
                'data.shop.gallery'
            );
    }

    public function test_active_service_exposes_customer_safe_optional_image(): void
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

                    'name' =>
                        'Premium Haircut',

                    'is_active' =>
                        true,
                ]);

        $service->image_path =
            'service-images/'
            .$shop->id
            .'/'.$service->id
            .'/display.jpg';

        $service->save();

        $response =
            $this->actingAs(
                $customer,
                'sanctum'
            )->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.shop.services.0.id',
                $service->id
            )
            ->assertJsonPath(
                'data.shop.services.0.image.has_image',
                true
            )
            ->assertJsonMissingPath(
                'data.shop.services.0.image_path'
            );

        $this->assertIsString(
            $response->json(
                'data.shop.services.0.image.image_url'
            )
        );
    }

    public function test_service_without_image_returns_explicit_unset_image_state(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        Service::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'name' =>
                    'Basic Haircut',

                'is_active' =>
                    true,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.services.0.image.has_image',
                false
            )
            ->assertJsonPath(
                'data.shop.services.0.image.image_url',
                null
            );
    }

    public function test_inactive_service_and_its_image_remain_hidden_from_customer(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        [, $shop] =
            $this->visibleShop();

        $visibleService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Visible Service',

                    'is_active' =>
                        true,
                ]);

        $hiddenService =
            Service::factory()
                ->inactive()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'name' =>
                        'Hidden Service',
                ]);

        $hiddenService->image_path =
            'service-images/'
            .$shop->id
            .'/'.$hiddenService->id
            .'/hidden.png';

        $hiddenService->save();

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
                'data.shop.services'
            )
            ->assertJsonPath(
                'data.shop.services.0.id',
                $visibleService->id
            );
    }

    public function test_media_from_non_customer_visible_shop_cannot_be_discovered(): void
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

        ShopGalleryImage::factory()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        Service::factory()
            ->create([
                'shop_id' =>
                    $shop->id,

                'is_active' =>
                    true,
            ]);

        $this->actingAs(
            $customer,
            'sanctum'
        )
            ->getJson(
                "/api/v1/customer/shops/{$shop->id}"
            )
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Shop}
     */
    private function visibleShop(): array
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

                    'availability_status' =>
                        ShopAvailabilityStatus::Open,
                ]);

        return [
            $owner,
            $shop,
        ];
    }
}