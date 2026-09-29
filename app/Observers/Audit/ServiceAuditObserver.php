<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Models\Service;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ServiceAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        Service $service
    ): void {
        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::ServiceCreated,

                auditable:
                    $service,

                metadata: [
                    'shop_id' =>
                        $service->shop_id,

                    'name' =>
                        $service->name,

                    'is_active' =>
                        (bool) $service
                            ->is_active,
                ],
            );
    }

    public function updated(
        Service $service
    ): void {
        $changedFields =
            array_values(
                array_intersect(
                    array_keys(
                        $service->getChanges()
                    ),
                    [
                        'name',
                        'description',
                        'price',
                        'duration_minutes',
                        'is_active',
                    ]
                )
            );

        if ($changedFields === []) {
            return;
        }

        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::ServiceUpdated,

                auditable:
                    $service,

                metadata: [
                    'shop_id' =>
                        $service->shop_id,

                    'name' =>
                        $service->name,

                    'changed_fields' =>
                        $changedFields,
                ],
            );
    }

    public function deleted(
        Service $service
    ): void {
        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::ServiceDeleted,

                auditable:
                    $service,

                metadata: [
                    'shop_id' =>
                        $service->shop_id,

                    'name' =>
                        $service->name,
                ],
            );
    }
}