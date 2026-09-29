<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_pending_appointment(): void
    {
        $appointment =
            Appointment::factory()->create();

        $this->assertSame(
            AppointmentStatus::Pending,
            $appointment->status
        );

        $this->assertSame(
            '250.00',
            $appointment->price_snapshot
        );

        $this->assertSame(
            30,
            $appointment
                ->duration_minutes_snapshot
        );

        $this->assertSame(
            '10:00:00',
            $appointment->starts_at
        );

        $this->assertSame(
            '10:30:00',
            $appointment->ends_at
        );

        $this->assertTrue(
            $appointment->isPending()
        );

        $this->assertTrue(
            $appointment->blocksTimeSlot()
        );
    }

    public function test_appointment_belongs_to_customer_shop_and_service(): void
    {
        $appointment =
            Appointment::factory()->create();

        $this->assertInstanceOf(
            User::class,
            $appointment->customer
        );

        $this->assertInstanceOf(
            Shop::class,
            $appointment->shop
        );

        $this->assertInstanceOf(
            Service::class,
            $appointment->service
        );

        $this->assertTrue(
            $appointment->customer
                ->isCustomer()
        );

        $this->assertSame(
            $appointment->shop_id,
            $appointment->service->shop_id
        );
    }

    public function test_customer_has_many_appointments(): void
    {
        $customer = User::factory()
            ->customer()
            ->create();

        Appointment::factory()
            ->count(3)
            ->create([
                'customer_id' =>
                    $customer->id,
            ]);

        $this->assertCount(
            3,
            $customer->fresh()
                ->appointments
        );
    }

    public function test_shop_has_many_appointments(): void
    {
        $shop = Shop::factory()
            ->approved()
            ->create();

        $service = Service::factory()
            ->create([
                'shop_id' =>
                    $shop->id,
            ]);

        Appointment::factory()
            ->count(2)
            ->create([
                'shop_id' =>
                    $shop->id,

                'service_id' =>
                    $service->id,
            ]);

        $this->assertCount(
            2,
            $shop->fresh()
                ->appointments
        );
    }

    public function test_service_has_many_appointments(): void
    {
        $service =
            Service::factory()->create();

        Appointment::factory()
            ->count(2)
            ->create([
                'shop_id' =>
                    $service->shop_id,

                'service_id' =>
                    $service->id,
            ]);

        $this->assertCount(
            2,
            $service->fresh()
                ->appointments
        );
    }

    public function test_sensitive_appointment_fields_cannot_be_mass_assigned(): void
    {
        $appointment =
            new Appointment();

        $appointment->fill([
            'customer_id' => 999,
            'shop_id' => 999,
            'service_id' => 999,

            'appointment_date' =>
                '2026-09-01',

            'starts_at' =>
                '09:00',

            'ends_at' =>
                '10:00',

            'service_name' =>
                'Injected Service',

            'price_snapshot' =>
                '1.00',

            'duration_minutes_snapshot' =>
                60,

            'status' =>
                AppointmentStatus::Completed
                    ->value,

            'notes' =>
                'Allowed customer note.',

            'cancelled_by' =>
                999,

            'cancelled_at' =>
                now(),

            'completed_at' =>
                now(),
        ]);

        $this->assertNull(
            $appointment->customer_id
        );

        $this->assertNull(
            $appointment->shop_id
        );

        $this->assertNull(
            $appointment->service_id
        );

        $this->assertNull(
            $appointment->ends_at
        );

        $this->assertNull(
            $appointment->service_name
        );

        $this->assertNull(
            $appointment->price_snapshot
        );

        $this->assertNull(
            $appointment
                ->duration_minutes_snapshot
        );

        $this->assertNull(
            $appointment->status
        );

        $this->assertNull(
            $appointment->cancelled_by
        );

        $this->assertNull(
            $appointment->cancelled_at
        );

        $this->assertNull(
            $appointment->completed_at
        );

        $this->assertSame(
            'Allowed customer note.',
            $appointment->notes
        );

        $this->assertSame(
            '09:00:00',
            $appointment->starts_at
        );
    }

    public function test_appointment_statuses_report_slot_blocking_correctly(): void
    {
        $this->assertTrue(
            AppointmentStatus::Pending
                ->blocksTimeSlot()
        );

        $this->assertTrue(
            AppointmentStatus::Confirmed
                ->blocksTimeSlot()
        );

        $this->assertFalse(
            AppointmentStatus::Completed
                ->blocksTimeSlot()
        );

        $this->assertFalse(
            AppointmentStatus::Cancelled
                ->blocksTimeSlot()
        );

        $this->assertFalse(
            AppointmentStatus::Declined
                ->blocksTimeSlot()
        );
    }

    public function test_terminal_statuses_are_identified_correctly(): void
    {
        $this->assertFalse(
            AppointmentStatus::Pending
                ->isTerminal()
        );

        $this->assertFalse(
            AppointmentStatus::Confirmed
                ->isTerminal()
        );

        $this->assertTrue(
            AppointmentStatus::Completed
                ->isTerminal()
        );

        $this->assertTrue(
            AppointmentStatus::Cancelled
                ->isTerminal()
        );

        $this->assertTrue(
            AppointmentStatus::Declined
                ->isTerminal()
        );
    }
}