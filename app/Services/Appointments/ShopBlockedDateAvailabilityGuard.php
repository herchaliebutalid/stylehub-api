<?php

namespace App\Services\Appointments;

use App\Models\Shop;
use App\Models\ShopBlockedDate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShopBlockedDateAvailabilityGuard
{
    public const UNAVAILABLE_REASON =
        'shop_blocked_date';

    /**
     * Apply whole-day shop closures to an available-dates API payload.
     *
     * @param array<string, mixed> $availability
     * @return array<string, mixed>
     */
    public function applyToAvailableDates(
        int $shopId,
        array $availability
    ): array {
        if (
            ! isset($availability['dates'])
            || ! is_array($availability['dates'])
        ) {
            return $availability;
        }

        $dateValues = [];

        foreach (
            $availability['dates']
            as $dateAvailability
        ) {
            if (
                is_array(
                    $dateAvailability
                )
                &&
                isset(
                    $dateAvailability['date']
                )
                &&
                is_string(
                    $dateAvailability['date']
                )
            ) {
                $dateValues[] =
                    $dateAvailability['date'];
            }
        }

        if (
            $dateValues === []
        ) {
            return $availability;
        }

        /*
        |--------------------------------------------------------------------------
        | Load All Relevant Blocked Dates Once
        |--------------------------------------------------------------------------
        |
        | This avoids one database query per date in the availability preview.
        |
        */

        $blockedDates =
            $this->blockedDateSet(
                $shopId,
                $dateValues
            );

        foreach (
            $availability['dates']
            as $index =>
                $dateAvailability
        ) {
            if (
                ! is_array(
                    $dateAvailability
                )
            ) {
                continue;
            }

            $date =
                $dateAvailability['date']
                ??
                null;

            if (
                ! is_string(
                    $date
                )
                ||
                ! $blockedDates->has(
                    $date
                )
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Whole Day Is Closed
            |--------------------------------------------------------------------------
            |
            | We intentionally do not expose the owner's private blocked-date
            | reason here.
            |
            */

            $availability['dates'][$index] = [
                ...$dateAvailability,

                'is_open' =>
                    false,

                'is_available' =>
                    false,

                'available_slot_count' =>
                    0,

                'opens_at' =>
                    null,

                'closes_at' =>
                    null,

                'unavailable_reason' =>
                    self::UNAVAILABLE_REASON,
            ];
        }

        return $availability;
    }

    /**
     * Apply a whole-day closure to an available-slots API payload.
     *
     * @param array<string, mixed> $availability
     * @return array<string, mixed>
     */
    public function applyToAvailableSlots(
        int $shopId,
        string $date,
        array $availability
    ): array {
        if (
            ! $this->isBlocked(
                $shopId,
                $date
            )
        ) {
            return $availability;
        }

        if (
            isset(
                $availability['date']
            )
            &&
            is_array(
                $availability['date']
            )
        ) {
            $availability['date'] = [
                ...$availability['date'],

                'is_open' =>
                    false,

                'opens_at' =>
                    null,

                'closes_at' =>
                    null,

                'unavailable_reason' =>
                    self::UNAVAILABLE_REASON,
            ];
        }

        $availability['slots'] =
            [];

        return $availability;
    }

    /**
     * Protect appointment/reschedule mutations.
     *
     * The shop row is locked before checking the closure record.
     *
     * Blocked-date creation also locks this same shop row, so a booking and a
     * new closure cannot silently race each other into an inconsistent state.
     */
    public function assertMutationDateIsNotBlocked(
        int $shopId,
        string|DateTimeInterface $date,
        string $field
    ): void {
        Shop::query()
            ->whereKey(
                $shopId
            )
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $this->isBlocked(
                $shopId,
                $date
            )
        ) {
            throw ValidationException::withMessages([
                $field =>
                    'The selected date has been blocked by the shop and is not available for appointments.',
            ]);
        }
    }

    /**
     * Determine whether one calendar date is blocked for one shop.
     */
    public function isBlocked(
        int $shopId,
        string|DateTimeInterface $date
    ): bool {
        return ShopBlockedDate::query()
            ->where(
                'shop_id',
                $shopId
            )
            ->whereDate(
                'blocked_date',
                $this->dateString(
                    $date
                )
            )
            ->exists();
    }

    /**
     * Retrieve blocked dates as an efficient lookup set.
     *
     * @param list<string> $dates
     * @return Collection<string, true>
     */
    private function blockedDateSet(
        int $shopId,
        array $dates
    ): Collection {
        return ShopBlockedDate::query()
            ->where(
                'shop_id',
                $shopId
            )
            ->whereIn(
                'blocked_date',
                $dates
            )
            ->get([
                'blocked_date',
            ])
            ->mapWithKeys(
                static function (
                    ShopBlockedDate $blockedDate
                ): array {
                    return [
                        $blockedDate
                            ->blocked_date
                            ->format(
                                'Y-m-d'
                            ) =>
                            true,
                    ];
                }
            );
    }

    /**
     * Normalize supported date values into YYYY-MM-DD.
     */
    private function dateString(
        string|DateTimeInterface $date
    ): string {
        if (
            $date
            instanceof DateTimeInterface
        ) {
            return CarbonImmutable::instance(
                $date
            )->toDateString();
        }

        return CarbonImmutable::parse(
            $date,
            config(
                'app.timezone',
                'UTC'
            )
        )->toDateString();
    }
}