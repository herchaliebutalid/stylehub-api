<?php

namespace Tests\Feature\Api\V1\ShopOwner;

use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\ShopType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProfileManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_profile_requires_authentication(): void
    {
        $this->getJson(
            '/api/v1/shop-owner/shop'
        )->assertUnauthorized();

        $this->putJson(
            '/api/v1/shop-owner/shop',
            $this->validPayload()
        )->assertUnauthorized();
    }

    public function test_customer_cannot_manage_shop_profile(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        $this->actingAs(
            $customer,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/shop'
        )->assertForbidden();
    }

    public function test_admin_cannot_manage_shop_profile(): void
    {
        $admin = User::factory()
            ->admin()
            ->create();

        $this->actingAs(
            $admin,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_unverified_shop_owner_cannot_manage_shop_profile(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->unverifiedPhone()
            ->create();

        Shop::factory()
            ->approved()
            ->create([
                'owner_id' => $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_pending_shop_owner_cannot_manage_shop_profile(): void
    {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        Shop::factory()
            ->create([
                'owner_id' => $owner->id,
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_rejected_shop_owner_cannot_manage_shop_profile(): void
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
        )->putJson(
            '/api/v1/shop-owner/shop',
            $this->validPayload()
        )->assertForbidden();
    }

    public function test_approved_owner_can_view_only_own_shop_profile(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'name' => 'Owner Shop',
            ]);

        Shop::factory()
            ->approved()
            ->create([
                'name' => 'Another Shop',
            ]);

        $this->actingAs(
            $owner,
            'sanctum'
        )->getJson(
            '/api/v1/shop-owner/shop'
        )
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.shop.id',
                $shop->id
            )
            ->assertJsonPath(
                'data.shop.name',
                'Owner Shop'
            )
            ->assertJsonMissingPath(
                'data.shop.owner_id'
            )
            ->assertJsonMissingPath(
                'data.shop.reviewed_by'
            );
    }

    public function test_approved_owner_can_update_editable_shop_information(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
                'availability_status' =>
                    ShopAvailabilityStatus::Open,
            ]);

        $originalReviewerId =
            $shop->reviewed_by;

        $response = $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            [
                'name' => '  StyleHub Studio  ',
                'type' => 'beauty_parlor',
                'description' => '  Premium beauty services.  ',
                'phone_number' => '0917 222 3333',
                'email' => '  SHOP@EXAMPLE.COM  ',
                'address' => '  123 Main Street  ',
                'city' => '  Surigao City  ',
                'province' => '  Surigao del Norte  ',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'message',
                'Shop information updated successfully.'
            )
            ->assertJsonPath(
                'data.shop.name',
                'StyleHub Studio'
            )
            ->assertJsonPath(
                'data.shop.type',
                'beauty_parlor'
            )
            ->assertJsonPath(
                'data.shop.phone_number',
                '+639172223333'
            )
            ->assertJsonPath(
                'data.shop.email',
                'shop@example.com'
            );

        $freshShop = $shop->fresh();

        $this->assertSame(
            'StyleHub Studio',
            $freshShop->name
        );

        $this->assertSame(
            ShopType::BeautyParlor,
            $freshShop->type
        );

        $this->assertSame(
            'Premium beauty services.',
            $freshShop->description
        );

        $this->assertSame(
            '+639172223333',
            $freshShop->phone_number
        );

        $this->assertSame(
            'shop@example.com',
            $freshShop->email
        );

        $this->assertSame(
            '123 Main Street',
            $freshShop->address
        );

        $this->assertSame(
            'Surigao City',
            $freshShop->city
        );

        $this->assertSame(
            'Surigao del Norte',
            $freshShop->province
        );

        $this->assertSame(
            ShopApprovalStatus::Approved,
            $freshShop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Open,
            $freshShop->availability_status
        );

        $this->assertSame(
            $owner->id,
            $freshShop->owner_id
        );

        $this->assertSame(
            $originalReviewerId,
            $freshShop->reviewed_by
        );

        $this->assertEqualsWithDelta(
            9.7891234,
            $freshShop->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            125.4956789,
            $freshShop->longitude,
            0.0000001
        );
    }

    public function test_optional_description_and_email_can_be_cleared(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'description' => 'Old description',
                'email' => 'old@example.com',
            ]);

        $payload = $this->validPayload();
        $payload['description'] = '   ';
        $payload['email'] = '   ';

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $payload
        )
            ->assertOk()
            ->assertJsonPath(
                'data.shop.description',
                null
            )
            ->assertJsonPath(
                'data.shop.email',
                null
            );

        $this->assertNull(
            $shop->fresh()->description
        );

        $this->assertNull(
            $shop->fresh()->email
        );
    }

    public function test_owner_cannot_inject_server_controlled_shop_fields(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'availability_status' =>
                    ShopAvailabilityStatus::Open,
                'latitude' => 9.7891234,
                'longitude' => 125.4956789,
            ]);

        $otherShop = Shop::factory()
            ->approved()
            ->create();

        $payload = $this->validPayload();

        $payload['owner_id'] =
            $otherShop->owner_id;

        $payload['approval_status'] =
            ShopApprovalStatus::Rejected->value;

        $payload['availability_status'] =
            ShopAvailabilityStatus::Closed->value;

        $payload['latitude'] = 10.0;
        $payload['longitude'] = 123.0;

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $payload
        )->assertUnprocessable();

        $freshShop = $shop->fresh();

        $this->assertSame(
            $owner->id,
            $freshShop->owner_id
        );

        $this->assertSame(
            ShopApprovalStatus::Approved,
            $freshShop->approval_status
        );

        $this->assertSame(
            ShopAvailabilityStatus::Open,
            $freshShop->availability_status
        );

        $this->assertEqualsWithDelta(
            9.7891234,
            $freshShop->latitude,
            0.0000001
        );

        $this->assertEqualsWithDelta(
            125.4956789,
            $freshShop->longitude,
            0.0000001
        );
    }

    public function test_profile_update_never_modifies_another_shop(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop([
                'name' => 'My Original Shop',
            ]);

        $otherShop = Shop::factory()
            ->approved()
            ->create([
                'name' => 'Protected Other Shop',
                'phone_number' => '+639188887777',
            ]);

        $payload = $this->validPayload();
        $payload['name'] = 'My Updated Shop';

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            $payload
        )->assertOk();

        $this->assertSame(
            'My Updated Shop',
            $shop->fresh()->name
        );

        $this->assertSame(
            'Protected Other Shop',
            $otherShop->fresh()->name
        );

        $this->assertSame(
            '+639188887777',
            $otherShop->fresh()->phone_number
        );
    }

    public function test_invalid_shop_profile_values_are_rejected(): void
    {
        [$owner, $shop] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            [
                'name' => 'A',
                'type' => 'invalid_type',
                'description' => str_repeat('x', 2001),
                'phone_number' => '12345',
                'email' => 'not-an-email',
                'address' => '123',
                'city' => 'S',
                'province' => 'P',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'type',
                'description',
                'phone_number',
                'email',
                'address',
                'city',
                'province',
            ]);

        $this->assertNotSame(
            'A',
            $shop->fresh()->name
        );
    }

    public function test_put_requires_complete_shop_profile_payload(): void
    {
        [$owner] =
            $this->approvedOwnerAndShop();

        $this->actingAs(
            $owner,
            'sanctum'
        )->putJson(
            '/api/v1/shop-owner/shop',
            [
                'name' => 'Incomplete Shop',
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'phone_number',
                'address',
                'city',
                'province',
            ]);
    }

    /**
     * @param array<string, mixed> $shopAttributes
     * @return array{0: User, 1: Shop}
     */
    private function approvedOwnerAndShop(
        array $shopAttributes = []
    ): array {
        $owner = User::factory()
            ->shopOwner()
            ->create();

        $shop = Shop::factory()
            ->approved()
            ->create(
                array_merge(
                    [
                        'owner_id' =>
                            $owner->id,
                    ],
                    $shopAttributes
                )
            );

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
            'name' => 'StyleHub Barber Studio',
            'type' => 'barber_shop',
            'description' => 'Professional grooming services.',
            'phone_number' => '09171234567',
            'email' => 'shop@example.com',
            'address' => '123 Main Street',
            'city' => 'Surigao City',
            'province' => 'Surigao del Norte',
        ];
    }
}