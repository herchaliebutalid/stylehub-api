<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Enums\ShopApprovalStatus;
use App\Models\Shop;
use App\Services\Audit\AuditLogger;
use BackedEnum;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ShopAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function updated(
        Shop $shop
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Administrator Approval / Rejection
        |--------------------------------------------------------------------------
        */

        if (
            $shop->wasChanged(
                'approval_status'
            )
        ) {
            $newStatus =
                $this->value(
                    $shop->approval_status
                );

            $previousStatus =
                $this->value(
                    $shop->getOriginal(
                        'approval_status'
                    )
                );

            $event =
                match ($newStatus) {
                    ShopApprovalStatus::Approved
                        ->value =>
                            AuditEvent::ShopApproved,

                    ShopApprovalStatus::Rejected
                        ->value =>
                            AuditEvent::ShopRejected,

                    default =>
                        null,
                };

            if ($event !== null) {
                $this->audit
                    ->recordCurrentSafely(
                        event:
                            $event,

                        auditable:
                            $shop,

                        metadata: [
                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                $newStatus,
                        ],
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Manual Availability
        |--------------------------------------------------------------------------
        */

        if (
            $shop->wasChanged(
                'availability_status'
            )
        ) {
            $this->audit
                ->recordCurrentSafely(
                    event:
                        AuditEvent::ShopAvailabilityUpdated,

                    auditable:
                        $shop,

                    metadata: [
                        'previous_status' =>
                            $this->value(
                                $shop->getOriginal(
                                    'availability_status'
                                )
                            ),

                        'new_status' =>
                            $this->value(
                                $shop
                                    ->availability_status
                            ),
                    ],
                );
        }
    }

    private function value(
        mixed $value
    ): mixed {
        return $value instanceof BackedEnum
            ? $value->value
            : $value;
    }
}