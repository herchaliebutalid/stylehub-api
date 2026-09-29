<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopGalleryImage;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopMediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_schema_exists_and_existing_services_remain_valid(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'shop_gallery_images',
                [
                    'id',
                    'shop_id',
                    'image_path',
                    'position',
                    'created_at',
                    'updated_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumn(
                'services',
                'image_path'
            )
        );

        $service =
            Service::factory()
                ->create();

        $this->assertNull(
            $service->image_path
        );

        $this->assertFalse(
            $service->hasImage()
        );
    }

    public function test_gallery_image_belongs_to_shop_and_shop_has_gallery_images(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $image =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        3,
                ]);

        $this->assertSame(
            $shop->id,
            $image->shop->id
        );

        $this->assertSame(
            3,
            $image->position
        );

        $this->assertTrue(
            $shop
                ->galleryImages()
                ->whereKey(
                    $image->id
                )
                ->exists()
        );
    }

    public function test_gallery_images_have_stable_position_then_id_ordering(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $second =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        20,
                ]);

        $first =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        10,
                ]);

        $third =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        20,
                ]);

        $orderedIds =
            $shop
                ->galleryImages()
                ->ordered()
                ->pluck(
                    'id'
                )
                ->all();

        $this->assertSame(
            [
                $first->id,
                $second->id,
                $third->id,
            ],
            $orderedIds
        );
    }

    public function test_gallery_ownership_path_and_position_cannot_be_mass_assigned(): void
    {
        $image =
            new ShopGalleryImage();

        $this->expectException(
            MassAssignmentException::class
        );

        $image->fill([
            'shop_id' =>
                999,

            'image_path' =>
                'forged/path.jpg',

            'position' =>
                999,
        ]);
    }

    public function test_service_image_path_is_server_controlled(): void
    {
        $service =
            new Service();

        $service->fill([
            'name' =>
                'Secure Service',

            'description' =>
                'Testing media mass assignment protection.',

            'price' =>
                '350.00',

            'duration_minutes' =>
                45,

            'is_active' =>
                true,

            'image_path' =>
                'forged/service.jpg',
        ]);

        $this->assertSame(
            'Secure Service',
            $service->name
        );

        $this->assertNull(
            $service->image_path
        );
    }

    public function test_trusted_service_image_path_is_detected(): void
    {
        $service =
            Service::factory()
                ->create([
                    'image_path' =>
                        'service-images/1/example.webp',
                ])
                ->fresh();

        $this->assertNotNull(
            $service
        );

        $this->assertSame(
            'service-images/1/example.webp',
            $service->image_path
        );

        $this->assertTrue(
            $service->hasImage()
        );
    }

    public function test_hard_deleted_shop_cascades_gallery_metadata(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $image =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $shop->forceDelete();

        $this->assertDatabaseMissing(
            'shop_gallery_images',
            [
                'id' =>
                    $image->id,
            ]
        );
    }
}