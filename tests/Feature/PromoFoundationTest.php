<?php

namespace Tests\Feature;

use App\Enums\PromoDiscountType;
use App\Models\Promo;
use App\Models\Service;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoFoundationTest extends TestCase
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

    public function test_factory_creates_valid_active_shop_wide_promo(): void
    {
        $promo =
            Promo::factory()->create([
                'starts_at' =>
                    '2026-08-19 00:00:00',

                'ends_at' =>
                    '2026-08-21 23:59:59',
            ]);

        $this->assertNull(
            $promo->service_id
        );

        $this->assertTrue(
            $promo->isShopWide()
        );

        $this->assertFalse(
            $promo->isServiceSpecific()
        );

        $this->assertTrue(
            $promo->isEnabled()
        );

        $this->assertTrue(
            $promo->isCurrentlyActive()
        );

        $this->assertSame(
            PromoDiscountType::Percentage,
            $promo->discount_type
        );

        $this->assertSame(
            '10.00',
            $promo->discount_value
        );
    }

    public function test_promo_belongs_to_shop(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        $promo =
            Promo::factory()->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->assertSame(
            $shop->id,
            $promo->shop->id
        );
    }

    public function test_shop_has_many_promos(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

        Promo::factory()
            ->count(3)
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        $this->assertCount(
            3,
            $shop->fresh()->promos
        );
    }

    public function test_promo_can_target_specific_service(): void
    {
        $shop =
            Shop::factory()
                ->approved()
                ->create();

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

                'service_id' =>
                    $service->id,
            ]);

        $this->assertFalse(
            $promo->isShopWide()
        );

        $this->assertTrue(
            $promo->isServiceSpecific()
        );

        $this->assertSame(
            $service->id,
            $promo->service->id
        );
    }

    public function test_service_has_many_promos(): void
    {
        $service =
            Service::factory()->create();

        Promo::factory()
            ->count(2)
            ->create([
                'shop_id' =>
                    $service->shop_id,

                'service_id' =>
                    $service->id,
            ]);

        $this->assertCount(
            2,
            $service->fresh()->promos
        );
    }

    public function test_sensitive_promo_ownership_fields_cannot_be_mass_assigned(): void
    {
        $promo =
            new Promo();

        $promo->fill([
            'shop_id' =>
                999,

            'service_id' =>
                999,

            'title' =>
                'Safe Promotion',

            'description' =>
                'Allowed promotional description.',

            'discount_type' =>
                PromoDiscountType::Percentage
                    ->value,

            'discount_value' =>
                '15.00',

            'starts_at' =>
                '2026-08-20 00:00:00',

            'ends_at' =>
                '2026-08-21 00:00:00',

            'is_active' =>
                true,
        ]);

        $this->assertNull(
            $promo->shop_id
        );

        $this->assertNull(
            $promo->service_id
        );

        $this->assertSame(
            'Safe Promotion',
            $promo->title
        );

        $this->assertSame(
            PromoDiscountType::Percentage,
            $promo->discount_type
        );
    }

    public function test_inactive_or_outside_date_range_promo_is_not_currently_active(): void
    {
        $inactive =
            Promo::factory()
                ->inactive()
                ->create([
                    'starts_at' =>
                        '2026-08-19 00:00:00',

                    'ends_at' =>
                        '2026-08-21 23:59:59',
                ]);

        $upcoming =
            Promo::factory()
                ->upcoming()
                ->create();

        $expired =
            Promo::factory()
                ->expired()
                ->create();

        $this->assertFalse(
            $inactive->isCurrentlyActive()
        );

        $this->assertFalse(
            $upcoming->isCurrentlyActive()
        );

        $this->assertTrue(
            $upcoming->isUpcoming()
        );

        $this->assertFalse(
            $expired->isCurrentlyActive()
        );

        $this->assertTrue(
            $expired->hasEnded()
        );
    }

    public function test_promo_supports_soft_deletion(): void
    {
        $promo =
            Promo::factory()->create();

        $promo->delete();

        $this->assertSoftDeleted(
            'promos',
            [
                'id' =>
                    $promo->id,
            ]
        );
    }
}