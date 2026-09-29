<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppointmentNoShowFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_table_has_no_show_timestamp(): void
    {
        $this->assertTrue(
            Schema::hasColumn(
                'appointments',
                'no_show_at'
            )
        );
    }

    public function test_no_show_status_has_expected_label_and_lifecycle_semantics(): void
    {
        $this->assertSame(
            'no_show',
            AppointmentStatus::NoShow->value
        );

        $this->assertSame(
            'No-show',
            AppointmentStatus::NoShow->label()
        );

        $this->assertFalse(
            AppointmentStatus::NoShow
                ->blocksTimeSlot()
        );

        $this->assertTrue(
            AppointmentStatus::NoShow
                ->isTerminal()
        );
    }

    public function test_no_show_appointment_is_cast_correctly(): void
    {
        $appointment =
            Appointment::factory()
                ->create([
                    'status' =>
                        AppointmentStatus::NoShow,

                    'no_show_at' =>
                        '2026-09-11 10:15:00',
                ]);

        $appointment =
            $appointment->fresh();

        $this->assertSame(
            AppointmentStatus::NoShow,
            $appointment->status
        );

        $this->assertTrue(
            $appointment->isNoShow()
        );

        $this->assertInstanceOf(
            CarbonInterface::class,
            $appointment->no_show_at
        );

        $this->assertSame(
            '2026-09-11 10:15:00',
            $appointment
                ->no_show_at
                ->format(
                    'Y-m-d H:i:s'
                )
        );
    }

    public function test_no_show_timestamp_cannot_be_mass_assigned_by_client_input(): void
    {
        $appointment =
            new Appointment();

        $appointment->fill([
            'appointment_date' =>
                '2026-09-11',

            'starts_at' =>
                '10:00',

            'notes' =>
                'Allowed note.',

            'no_show_at' =>
                '2026-09-11 10:15:00',
        ]);

        $this->assertSame(
            '2026-09-11',
            $appointment
                ->appointment_date
                ->toDateString()
        );

        $this->assertSame(
            '10:00:00',
            $appointment->starts_at
        );

        $this->assertSame(
            'Allowed note.',
            $appointment->notes
        );

        $this->assertNull(
            $appointment->no_show_at
        );
    }

    public function test_existing_status_semantics_remain_unchanged(): void
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