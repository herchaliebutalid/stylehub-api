<?php

namespace App\Observers\Audit;

use App\Enums\AuditEvent;
use App\Models\Promo;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class PromoAuditObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function created(
        Promo $promo
    ): void {
        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::PromoCreated,

                auditable:
                    $promo,

                metadata: [
                    'shop_id' =>
                        $promo->shop_id,

                    'service_id' =>
                        $promo->service_id,

                    'scope' =>
                        $promo->service_id === null
                            ? 'shop_wide'
                            : 'service_specific',

                    'title' =>
                        $promo->title,
                ],
            );
    }

    public function updated(
        Promo $promo
    ): void {
        $changedFields =
            array_values(
                array_intersect(
                    array_keys(
                        $promo->getChanges()
                    ),
                    [
                        'service_id',
                        'title',
                        'description',
                        'discount_type',
                        'discount_value',
                        'starts_at',
                        'ends_at',
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
                    AuditEvent::PromoUpdated,

                auditable:
                    $promo,

                metadata: [
                    'shop_id' =>
                        $promo->shop_id,

                    'service_id' =>
                        $promo->service_id,

                    'title' =>
                        $promo->title,

                    'changed_fields' =>
                        $changedFields,
                ],
            );
    }

    public function deleted(
        Promo $promo
    ): void {
        $this->audit
            ->recordCurrentSafely(
                event:
                    AuditEvent::PromoDeleted,

                auditable:
                    $promo,

                metadata: [
                    'shop_id' =>
                        $promo->shop_id,

                    'service_id' =>
                        $promo->service_id,

                    'title' =>
                        $promo->title,
                ],
            );
    }
}