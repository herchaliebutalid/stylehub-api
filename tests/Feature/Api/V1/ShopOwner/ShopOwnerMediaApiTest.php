<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopGalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ShopOwnerMediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * No real files are written during tests.
         */

        Storage::fake(
            'public'
        );
    }

    public function test_media_routes_require_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/gallery'
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/shop-owner/gallery'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/shop-owner/gallery/order',
            [
                'image_ids' => [
                    1,
                ],
            ]
        )->assertUnauthorized();

        $this->deleteJson(
            '/api/v1/shop-owner/gallery/1'
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/shop-owner/services/1/image'
        )->assertUnauthorized();

        $this->deleteJson(
            '/api/v1/shop-owner/services/1/image'
        )->assertUnauthorized();
    }

    public function test_customer_cannot_use_shop_owner_media_routes(): void
    {
        $customer =
            User::factory()
                ->customer()
                ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/gallery'
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_manage_media(): void
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
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/gallery'
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_media(): void
    {
        $owner =
            User::factory()
                ->shopOwner()
                ->create();

        Shop::factory()
            ->create([
                'owner_id' =>
                    $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/gallery'
        )->assertForbidden();
    }

    public function test_owner_lists_only_own_gallery_in_display_order_without_storage_paths(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

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
                        .'/first.png',

                    'position' =>
                        10,
                ]);

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        ShopGalleryImage::factory()
            ->create([
                'shop_id' =>
                    $otherShop->id,
            ]);

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )->getJson(
                '/api/v1/shop-owner/gallery'
            );

        $response
            ->assertOk()

            ->assertJsonCount(
                2,
                'data.images'
            )

            ->assertJsonPath(
                'data.images.0.id',
                $first->id
            )

            ->assertJsonPath(
                'data.images.1.id',
                $later->id
            )

            ->assertJsonPath(
                'data.max_images',
                10
            )

            ->assertJsonMissingPath(
                'data.images.0.image_path'
            );

        $this->assertIsString(
            $response->json(
                'data.images.0.image_url'
            )
        );
    }

    public function test_owner_can_upload_gallery_image_with_server_generated_path(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )->post(
                '/api/v1/shop-owner/gallery',

                [
                    'image' =>
                        $this->fakePng(
                            'my-custom-name.png'
                        ),
                ],

                [
                    'Accept' =>
                        'application/json',
                ]
            );

        $response
            ->assertCreated()

            ->assertJsonPath(
                'success',
                true
            )

            ->assertJsonPath(
                'data.image.position',
                0
            )

            ->assertJsonMissingPath(
                'data.image.image_path'
            );

        $galleryImage =
            ShopGalleryImage::query()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->firstOrFail();

        $this->assertStringStartsWith(
            'shop-gallery/'
            .$shop->id
            .'/',
            $galleryImage->image_path
        );

        /*
         * The original client filename must not become the
         * physical filename.
         */

        $this->assertStringNotContainsString(
            'my-custom-name',
            $galleryImage->image_path
        );

        Storage::disk(
            'public'
        )->assertExists(
            $galleryImage->image_path
        );
    }

    public function test_gallery_upload_rejects_invalid_image_and_svg(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        $invalid =
            File::createWithContent(
                'fake.png',
                '<?php echo "not an image";'
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $invalid,
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image',
            ]);

        /*
         * SVG is deliberately unsupported.
         */

        $svg =
            File::createWithContent(
                'image.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>'
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $svg,
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image',
            ]);

        $this->assertDatabaseCount(
            'shop_gallery_images',
            0
        );
    }

    public function test_gallery_upload_rejects_file_larger_than_five_megabytes(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        $oversized =
            $this
                ->fakePng(
                    'large.png'
                )
                ->size(
                    5121
                );

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $oversized,
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image',
            ]);
    }

    public function test_gallery_upload_rejects_unknown_client_controlled_fields(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $this->fakePng(),

                'shop_id' =>
                    $otherShop->id,

                'image_path' =>
                    '../../forged.png',
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'shop_id',
                'image_path',
            ]);

        $this->assertDatabaseCount(
            'shop_gallery_images',
            0
        );
    }

    public function test_gallery_limit_rejects_eleventh_image_and_cleans_rejected_file(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        ShopGalleryImage::factory()
            ->count(
                10
            )
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $this->fakePng(),
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image',
            ]);

        $this->assertSame(
            10,
            $shop
                ->galleryImages()
                ->count()
        );

        /*
         * The rejected physical upload must be removed.
         */

        $this->assertSame(
            [],
            Storage::disk(
                'public'
            )->allFiles()
        );
    }

    public function test_owner_can_delete_own_gallery_image_and_physical_file(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $path =
            'shop-gallery/'
            .$shop->id
            .'/delete-me.png';

        Storage::disk(
            'public'
        )->put(
            $path,
            'test-file'
        );

        $image =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'image_path' =>
                        $path,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/gallery/{$image->id}"
        )
            ->assertOk()

            ->assertJsonPath(
                'success',
                true
            );

        $this->assertDatabaseMissing(
            'shop_gallery_images',
            [
                'id' =>
                    $image->id,
            ]
        );

        Storage::disk(
            'public'
        )->assertMissing(
            $path
        );
    }

    public function test_owner_cannot_delete_another_shops_gallery_image(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $path =
            'shop-gallery/'
            .$otherShop->id
            .'/protected.png';

        Storage::disk(
            'public'
        )->put(
            $path,
            'protected'
        );

        $image =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $otherShop->id,

                    'image_path' =>
                        $path,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/gallery/{$image->id}"
        )->assertNotFound();

        $this->assertDatabaseHas(
            'shop_gallery_images',
            [
                'id' =>
                    $image->id,
            ]
        );

        Storage::disk(
            'public'
        )->assertExists(
            $path
        );
    }

    public function test_owner_can_reorder_complete_gallery(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $first =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        0,
                ]);

        $second =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        1,
                ]);

        $third =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        2,
                ]);

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )->putJson(
                '/api/v1/shop-owner/gallery/order',

                [
                    'image_ids' => [
                        $third->id,
                        $first->id,
                        $second->id,
                    ],
                ]
            );

        $response
            ->assertOk()

            ->assertJsonPath(
                'data.images.0.id',
                $third->id
            )

            ->assertJsonPath(
                'data.images.1.id',
                $first->id
            )

            ->assertJsonPath(
                'data.images.2.id',
                $second->id
            );

        $this->assertSame(
            0,
            $third
                ->fresh()
                ->position
        );

        $this->assertSame(
            1,
            $first
                ->fresh()
                ->position
        );

        $this->assertSame(
            2,
            $second
                ->fresh()
                ->position
        );
    }

    public function test_reorder_rejects_incomplete_or_foreign_gallery_ids(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $first =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        0,
                ]);

        $second =
            ShopGalleryImage::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'position' =>
                        1,
                ]);

        $otherImage =
            ShopGalleryImage::factory()
                ->create();

        /*
         * Same array length, but one ID belongs to another shop.
         */

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/gallery/order',

            [
                'image_ids' => [
                    $first->id,
                    $otherImage->id,
                ],
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image_ids',
            ]);

        $this->assertSame(
            0,
            $first
                ->fresh()
                ->position
        );

        $this->assertSame(
            1,
            $second
                ->fresh()
                ->position
        );
    }

    public function test_owner_can_upload_image_for_own_service(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $response =
            $this->actingAs(
                $owner,
                'sanctum'
            )->post(
                "/api/v1/shop-owner/services/{$service->id}/image",

                [
                    'image' =>
                        $this->fakePng(
                            'service.png'
                        ),
                ],

                [
                    'Accept' =>
                        'application/json',
                ]
            );

        $response
            ->assertOk()

            ->assertJsonPath(
                'data.service.image.has_image',
                true
            )

            ->assertJsonMissingPath(
                'data.service.image_path'
            );

        $service->refresh();

        $this->assertStringStartsWith(
            'service-images/'
            .$shop->id
            .'/'
            .$service->id
            .'/',
            $service->image_path
        );

        Storage::disk(
            'public'
        )->assertExists(
            $service->image_path
        );
    }

    public function test_replacing_service_image_deletes_previous_file_after_success(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $oldPath =
            'service-images/'
            .$shop->id
            .'/1/old.png';

        Storage::disk(
            'public'
        )->put(
            $oldPath,
            'old'
        );

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,

                    'image_path' =>
                        $oldPath,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            "/api/v1/shop-owner/services/{$service->id}/image",

            [
                'image' =>
                    $this->fakePng(
                        'replacement.png'
                    ),
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )->assertOk();

        $service->refresh();

        $this->assertNotSame(
            $oldPath,
            $service->image_path
        );

        Storage::disk(
            'public'
        )->assertMissing(
            $oldPath
        );

        Storage::disk(
            'public'
        )->assertExists(
            $service->image_path
        );
    }

    public function test_owner_can_remove_own_service_image(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $path =
            'service-images/'
            .$shop->id
            .'/'
            .$service->id
            .'/current.png';

        /*
         * Trusted server-side assignment.
         */

        $service->image_path =
            $path;

        $service->save();

        Storage::disk(
            'public'
        )->put(
            $path,
            'current'
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/services/{$service->id}/image"
        )
            ->assertOk()

            ->assertJsonPath(
                'data.service.image.has_image',
                false
            )

            ->assertJsonPath(
                'data.service.image.image_url',
                null
            );

        $this->assertNull(
            $service
                ->fresh()
                ->image_path
        );

        Storage::disk(
            'public'
        )->assertMissing(
            $path
        );
    }

    public function test_owner_cannot_modify_another_shops_service_image(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        $otherShop =
            Shop::factory()
                ->approved()
                ->create();

        $otherService =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $otherShop->id,
                ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            "/api/v1/shop-owner/services/{$otherService->id}/image",

            [
                'image' =>
                    $this->fakePng(),
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )->assertNotFound();

        $this->actingAs(
            $owner,
            'sanctum'
        )->deleteJson(
            "/api/v1/shop-owner/services/{$otherService->id}/image"
        )->assertNotFound();

        $this->assertNull(
            $otherService
                ->fresh()
                ->image_path
        );
    }

    public function test_service_image_upload_uses_same_secure_validation_contract(): void
    {
        [
            $owner,
            $shop,
        ] =
            $this->approvedOwnerAndShop();

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $svg =
            File::createWithContent(
                'service.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>'
            );

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            "/api/v1/shop-owner/services/{$service->id}/image",

            [
                'image' =>
                    $svg,

                'image_path' =>
                    'forged.png',
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )
            ->assertUnprocessable()

            ->assertJsonValidationErrors([
                'image',
                'image_path',
            ]);

        $this->assertNull(
            $service
                ->fresh()
                ->image_path
        );
    }

    public function test_gallery_upload_cleans_stored_file_if_database_creation_fails(): void
    {
        [
            $owner,
        ] =
            $this->approvedOwnerAndShop();

        /*
         * Simulate a DB/model-layer failure after the file has already
         * been written.
         */

        Event::listen(
            'eloquent.creating: '
            .ShopGalleryImage::class,

            function (): void {
                throw new RuntimeException(
                    'Simulated gallery database failure.'
                );
            }
        );

        $this->actingAs(
            $owner,
            'sanctum'
        )->post(
            '/api/v1/shop-owner/gallery',

            [
                'image' =>
                    $this->fakePng(),
            ],

            [
                'Accept' =>
                    'application/json',
            ]
        )->assertStatus(
            500
        );

        $this->assertDatabaseCount(
            'shop_gallery_images',
            0
        );

        /*
         * New file must not be orphaned after failed DB creation.
         */

        $this->assertSame(
            [],
            Storage::disk(
                'public'
            )->allFiles()
        );
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
     * Generate a real tiny PNG without requiring the GD extension.
     */
    private function fakePng(
        string $name = 'image.png'
    ): File {
        $content =
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwC'
                .'AAAAC0lEQVR42mP8/x8AAusB9Wl5Xz8AAAAASUVORK5CYII=',
                true
            );

        if (
            ! is_string(
                $content
            )
        ) {
            throw new RuntimeException(
                'Unable to build the test PNG image.'
            );
        }

        return File::createWithContent(
            $name,
            $content
        );
    }
}