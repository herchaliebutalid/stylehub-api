<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Shop;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_factory_creates_valid_active_service(): void
    {
        $service = Service::factory()->create([
            'price' => '350.00',
            'duration_minutes' => 45,
        ]);

        $this->assertSame(
            '350.00',
            $service->price
        );

        $this->assertSame(
            45,
            $service->duration_minutes
        );

        $this->assertTrue(
            $service->is_active
        );

        $this->assertTrue(
            $service->isActive()
        );

        $this->assertFalse(
            $service->isInactive()
        );
    }

    public function test_service_belongs_to_shop(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $service = Service::factory()->create([
            'shop_id' => $shop->id,
        ]);

        $this->assertSame(
            $shop->id,
            $service->shop->id
        );
    }

    public function test_shop_has_many_services(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        Service::factory()
            ->count(3)
            ->create([
                'shop_id' => $shop->id,
            ]);

        $this->assertCount(
            3,
            $shop->fresh()->services
        );
    }

    public function test_shop_id_cannot_be_mass_assigned(): void
    {
        $service = new Service();

        $service->fill([
            'shop_id' => 999,
            'name' => 'Secure Haircut',
            'description' =>
                'Testing mass assignment protection.',
            'price' => '250.00',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->assertNull(
            $service->shop_id
        );

        $this->assertSame(
            'Secure Haircut',
            $service->name
        );
    }

    public function test_service_price_is_stored_with_two_decimal_places(): void
    {
        $service = Service::factory()->create([
            'price' => '199.50',
        ]);

        $this->assertSame(
            '199.50',
            $service->price
        );

        $this->assertDatabaseHas(
            'services',
            [
                'id' => $service->id,
                'price' => '199.50',
            ]
        );
    }

    public function test_inactive_service_reports_correct_state(): void
    {
        $service = Service::factory()
            ->inactive()
            ->create();

        $this->assertFalse(
            $service->is_active
        );

        $this->assertFalse(
            $service->isActive()
        );

        $this->assertTrue(
            $service->isInactive()
        );
    }

    public function test_service_supports_soft_deletion(): void
    {
        $service = Service::factory()->create();

        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(Service::class)
        );

        $service->delete();

        $this->assertSoftDeleted(
            'services',
            [
                'id' => $service->id,
            ]
        );
    }
}