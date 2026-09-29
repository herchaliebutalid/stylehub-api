<?php

namespace App\Services\Admin;

use App\Enums\AuditEvent;
use App\Enums\ShopAdministrativeStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminShopSuspensionService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * Suspend one approved shop without changing its owner's account state.
     */
    public function suspend(
        int $shopId,
        User $administrator,
        string $reason
    ): Shop {
        $this->assertAdministrator(
            $administrator
        );

        return DB::transaction(
            function () use (
                $shopId,
                $administrator,
                $reason
            ): Shop {
                $shop =
                    $this->lockedShop(
                        $shopId
                    );

                if (
                    ! $shop->isApproved()
                ) {
                    throw new ConflictHttpException(
                        'Only approved shops can be suspended.'
                    );
                }

                if (
                    $shop->isSuspended()
                ) {
                    throw new ConflictHttpException(
                        'This shop is already suspended.'
                    );
                }

                $previousStatus =
                    $shop
                        ->administrative_status
                        ->value;

                /*
                |--------------------------------------------------------------------------
                | Independent Shop Suspension
                |--------------------------------------------------------------------------
                |
                | Do not modify the owner's User.status and do not revoke the
                | owner's Sanctum sessions. This action applies only to the shop.
                |
                */

                $shop->forceFill([
                    'administrative_status' =>
                        ShopAdministrativeStatus::Suspended,

                    'suspended_by' =>
                        $administrator->id,

                    'suspended_at' =>
                        now(),

                    'suspension_reason' =>
                        $reason,
                ])->save();

                $this->auditLogger
                    ->recordSafely(
                        actor:
                            $administrator,

                        event:
                            AuditEvent::ShopSuspended,

                        auditable:
                            $shop,

                        metadata: [
                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                ShopAdministrativeStatus::Suspended
                                    ->value,

                            'reason' =>
                                $reason,
                        ],
                    );

                $shop->load([
                    'owner',
                    'suspender',
                ]);

                return $shop;
            },
            3
        );
    }

    /**
     * Reactivate one independently suspended approved shop.
     *
     * The current suspension fields are cleared, while the immutable audit
     * history preserves who suspended/reactivated the shop and why.
     */
    public function reactivate(
        int $shopId,
        User $administrator
    ): Shop {
        $this->assertAdministrator(
            $administrator
        );

        return DB::transaction(
            function () use (
                $shopId,
                $administrator
            ): Shop {
                $shop =
                    $this->lockedShop(
                        $shopId
                    );

                if (
                    ! $shop->isApproved()
                ) {
                    throw new ConflictHttpException(
                        'Only approved shops can be reactivated.'
                    );
                }

                if (
                    ! $shop->isSuspended()
                ) {
                    throw new ConflictHttpException(
                        'This shop is already active.'
                    );
                }

                $previousStatus =
                    $shop
                        ->administrative_status
                        ->value;

                $shop->forceFill([
                    'administrative_status' =>
                        ShopAdministrativeStatus::Active,

                    'suspended_by' =>
                        null,

                    'suspended_at' =>
                        null,

                    'suspension_reason' =>
                        null,
                ])->save();

                $this->auditLogger
                    ->recordSafely(
                        actor:
                            $administrator,

                        event:
                            AuditEvent::ShopReactivated,

                        auditable:
                            $shop,

                        metadata: [
                            'previous_status' =>
                                $previousStatus,

                            'new_status' =>
                                ShopAdministrativeStatus::Active
                                    ->value,
                        ],
                    );

                $shop->load([
                    'owner',
                    'suspender',
                ]);

                return $shop;
            },
            3
        );
    }

    private function lockedShop(
        int $shopId
    ): Shop {
        $shop =
            Shop::query()
                ->whereKey(
                    $shopId
                )
                ->lockForUpdate()
                ->first();

        if (
            $shop === null
        ) {
            throw (
                new ModelNotFoundException()
            )->setModel(
                Shop::class,
                [
                    $shopId,
                ]
            );
        }

        return $shop;
    }

    /**
     * Route middleware already guarantees this, but keeping the service
     * protected prevents accidental use from an unauthorized code path.
     */
    private function assertAdministrator(
        User $administrator
    ): void {
        if (
            ! $administrator->isAdmin()
        ) {
            throw new AuthorizationException(
                'Administrator access is required.'
            );
        }
    }
}