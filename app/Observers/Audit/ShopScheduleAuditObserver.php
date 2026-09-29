<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Models\ShopSchedule;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ShopScheduleAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        ShopSchedule $schedule
    ): void {
        $this->record(
            schedule:
                $schedule,

            changedFields: [
                'day_of_week',
                'is_open',
                'opens_at',
                'closes_at',
            ],
        );
    }

    public function updated(
        ShopSchedule $schedule
    ): void {
        $changedFields =
            array_values(
                array_intersect(
                    array_keys(
                        $schedule->getChanges()
                    ),
                    [
                        'day_of_week',
                        'is_open',
                        'opens_at',
                        'closes_at',
                    ]
                )
            );

        if ($changedFields === []) {
            return;
        }

        $this->record(
            schedule:
                $schedule,

            changedFields:
                $changedFields,
        );
    }

    /**
     * @param list<string> $changedFields
     */
    private function record(
        ShopSchedule $schedule,
        array $changedFields
    ): void {
        $day =
            $schedule->day_of_week;

        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::ShopScheduleUpdated,

                auditable:
                    $schedule,

                metadata: [
                    'shop_id' =>
                        $schedule->shop_id,

                    'day_of_week' =>
                        $day instanceof BackedEnum
                            ? $day->value
                            : $day,

                    'changed_fields' =>
                        $changedFields,
                ],
            );
    }
}