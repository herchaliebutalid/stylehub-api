<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\AuditEvent;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\Shop;
use App\Models\User;
use App\Observers\Audit\AppointmentAuditObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentNoShowAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_appointment_no_show_is_audited_with_status_transition(): void
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

        $service =
            Service::factory()
                ->create([
                    'shop_id' =>
                        $shop->id,
                ]);

        $appointment =
            Appointment::withoutEvents(
                fn (): Appointment =>
                    Appointment::factory()
                        ->create([
                            'shop_id' =>
                                $shop->id,

                            'service_id' =>
                                $service->id,

                            'status' =>
                                AppointmentStatus::Confirmed,
                        ])
            );

        $this->actingAs(
            $owner,
            'sanctum'
        );

        $appointment->status =
            AppointmentStatus::NoShow;

        $appointment->no_show_at =
            now();

        $appointment->saveQuietly();

        app(
            AppointmentAuditObserver::class
        )->updated(
            $appointment
        );

        $log =
            AuditLog::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            AuditEvent::AppointmentNoShow,
            $log->event
        );

        $this->assertSame(
            $owner->id,
            $log->actor_id
        );

        $this->assertSame(
            $appointment->id,
            $log->auditable_id
        );

        $this->assertSame(
            AppointmentStatus::Confirmed
                ->value,
            $log->metadata[
                'previous_status'
            ]
        );

        $this->assertSame(
            AppointmentStatus::NoShow
                ->value,
            $log->metadata[
                'new_status'
            ]
        );
    }
}