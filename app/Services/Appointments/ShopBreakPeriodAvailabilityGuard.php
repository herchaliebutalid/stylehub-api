<?php

namespace App\Services\Appointments;

use App\Models\Shop;
use App\Models\ShopBreakPeriod;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

class ShopBreakPeriodAvailabilityGuard
{
    /**
     * Protect appointment/reschedule mutations from recurring break windows.
     *
     * The shop row is locked first so a booking/move and a break-period write
     * cannot silently race each other.
     */
    public function assertMutationDoesNotOverlapBreak(
        int $shopId,
        string|DateTimeInterface $date,
        string $startsAt,
        string $endsAt,
        string $field
    ): void {
        Shop::query()
            ->whereKey(
                $shopId
            )
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $this->overlapsBreak(
                $shopId,
                $date,
                $startsAt,
                $endsAt
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'The selected appointment time overlaps a shop break period.',
            ]);
        }
    }

    /**
     * Determine whether a date/time interval overlaps a recurring shop break.
     */
    public function overlapsBreak(
        int $shopId,
        string|DateTimeInterface $date,
        string $startsAt,
        string $endsAt
    ): bool {
        return ShopBreakPeriod::query()
            ->where(
                'shop_id',
                $shopId
            )
            ->where(
                'day_of_week',
                $this->weekday(
                    $date
                )
            )
            ->where(
                'starts_at',
                '<',
                $this->databaseTime(
                    $endsAt
                )
            )
            ->where(
                'ends_at',
                '>',
                $this->databaseTime(
                    $startsAt
                )
            )
            ->exists();
    }

    private function weekday(
        string|DateTimeInterface $date
    ): int {
        if (
            $date instanceof DateTimeInterface
        ) {
            return CarbonImmutable::instance(
                $date
            )->isoWeekday();
        }

        return CarbonImmutable::parse(
            $date,
            config(
                'app.timezone',
                'UTC'
            )
        )->isoWeekday();
    }

    private function databaseTime(
        string $time
    ): string {
        $time = trim(
            $time
        );

        return strlen($time) === 5
            ? $time.':00'
            : $time;
    }
}