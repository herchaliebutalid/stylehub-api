<?php

namespace App\Services\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\RescheduleRequestStatus;
use App\Enums\ShopApprovalStatus;
use App\Enums\ShopAvailabilityStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\RescheduleRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminReportService
{
    private const TOP_SHOPS_LIMIT = 5;

    private const TOP_SERVICES_LIMIT = 5;

    /**
     * Build the secure Administrator reporting overview.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function overview(
        array $filters
    ): array {
        $appointmentStatistics =
            $this->appointmentStatistics(
                $filters
            );

        return [
            'period' =>
                $this->periodData(
                    $filters
                ),

            /*
            |--------------------------------------------------------------------------
            | Current State
            |--------------------------------------------------------------------------
            |
            | These figures describe the system's current state and therefore
            | are intentionally not restricted by the activity date range.
            |
            */

            'current_state' => [
                'users' =>
                    $this->userStatistics(),

                'shops' =>
                    $this->shopStatistics(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Activity
            |--------------------------------------------------------------------------
            |
            | The optional date_from/date_to filters apply to these sections.
            |
            */

            'activity' => [
                'appointments' =>
                    $appointmentStatistics,

                'feedback' =>
                    $this->feedbackStatistics(
                        $filters
                    ),

                'reschedule_requests' =>
                    $this->rescheduleRequestStatistics(
                        $filters
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Performance Analytics
            |--------------------------------------------------------------------------
            |
            | Rankings and no-show performance use the same scheduled appointment
            | date range as the appointment activity section above.
            |
            */

            'analytics' => [
                'no_show' =>
                    $this->noShowAnalytics(
                        $appointmentStatistics
                    ),

                'top_shops' =>
                    $this->topShops(
                        $filters
                    ),

                'top_services' =>
                    $this->topServices(
                        $filters
                    ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function periodData(
        array $filters
    ): array {
        $dateFrom =
            $filters['date_from']
            ?? null;

        $dateTo =
            $filters['date_to']
            ?? null;

        return [
            'date_from' =>
                $dateFrom,

            'date_to' =>
                $dateTo,

            'is_all_time' =>
                $dateFrom === null
                && $dateTo === null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userStatistics(): array
    {
        return [
            'customers' => [
                'total' =>
                    $this->countUsers(
                        UserRole::Customer
                    ),

                'active' =>
                    $this->countUsers(
                        UserRole::Customer,
                        UserStatus::Active
                    ),

                'suspended' =>
                    $this->countUsers(
                        UserRole::Customer,
                        UserStatus::Suspended
                    ),
            ],

            'shop_owners' => [
                'total' =>
                    $this->countUsers(
                        UserRole::ShopOwner
                    ),

                'active' =>
                    $this->countUsers(
                        UserRole::ShopOwner,
                        UserStatus::Active
                    ),

                'suspended' =>
                    $this->countUsers(
                        UserRole::ShopOwner,
                        UserStatus::Suspended
                    ),
            ],
        ];
    }

    private function countUsers(
        UserRole $role,
        ?UserStatus $status = null
    ): int {
        return User::query()
            ->where(
                'role',
                $role->value
            )
            ->when(
                $status !== null,
                fn (
                    Builder $query
                ) =>
                    $query->where(
                        'status',
                        $status->value
                    )
            )
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function shopStatistics(): array
    {
        return [
            'total' =>
                Shop::query()
                    ->count(),

            'approval' => [
                'pending' =>
                    Shop::query()
                        ->where(
                            'approval_status',
                            ShopApprovalStatus::Pending
                                ->value
                        )
                        ->count(),

                'approved' =>
                    Shop::query()
                        ->where(
                            'approval_status',
                            ShopApprovalStatus::Approved
                                ->value
                        )
                        ->count(),

                'rejected' =>
                    Shop::query()
                        ->where(
                            'approval_status',
                            ShopApprovalStatus::Rejected
                                ->value
                        )
                        ->count(),
            ],

            'availability' => [
                'open' =>
                    Shop::query()
                        ->where(
                            'availability_status',
                            ShopAvailabilityStatus::Open
                                ->value
                        )
                        ->count(),

                'closed' =>
                    Shop::query()
                        ->where(
                            'availability_status',
                            ShopAvailabilityStatus::Closed
                                ->value
                        )
                        ->count(),

                'fully_booked' =>
                    Shop::query()
                        ->where(
                            'availability_status',
                            ShopAvailabilityStatus::FullyBooked
                                ->value
                        )
                        ->count(),

                'accepting_walk_ins' =>
                    Shop::query()
                        ->where(
                            'availability_status',
                            ShopAvailabilityStatus::AcceptingWalkIns
                                ->value
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, int>
     */
    private function appointmentStatistics(
        array $filters
    ): array {
        $query =
            $this->appointmentActivityQuery(
                $filters
            );

        $rawStatusCounts =
            (clone $query)
                ->selectRaw(
                    'status, COUNT(*) as aggregate_count'
                )
                ->groupBy(
                    'status'
                )
                ->pluck(
                    'aggregate_count',
                    'status'
                );

        $countStatus =
            fn (
                AppointmentStatus $status
            ): int =>
                (int) $rawStatusCounts->get(
                    $status->value,
                    0
                );

        return [
            'total' =>
                (int) $rawStatusCounts->sum(),

            'pending' =>
                $countStatus(
                    AppointmentStatus::Pending
                ),

            'confirmed' =>
                $countStatus(
                    AppointmentStatus::Confirmed
                ),

            'completed' =>
                $countStatus(
                    AppointmentStatus::Completed
                ),

            'no_show' =>
                $countStatus(
                    AppointmentStatus::NoShow
                ),

            'cancelled' =>
                $countStatus(
                    AppointmentStatus::Cancelled
                ),

            'declined' =>
                $countStatus(
                    AppointmentStatus::Declined
                ),

            'unique_customers' =>
                (clone $query)
                    ->distinct()
                    ->count(
                        'customer_id'
                    ),

            'unique_shops' =>
                (clone $query)
                    ->distinct()
                    ->count(
                        'shop_id'
                    ),
        ];
    }

    /**
     * Appointment report periods use the scheduled appointment date.
     *
     * @param array<string, mixed> $filters
     *
     * @return Builder<Appointment>
     */
    private function appointmentActivityQuery(
        array $filters
    ): Builder {
        return Appointment::query()
            ->when(
                isset(
                    $filters['date_from']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'appointment_date',
                        '>=',
                        $filters['date_from']
                    )
            )
            ->when(
                isset(
                    $filters['date_to']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'appointment_date',
                        '<=',
                        $filters['date_to']
                    )
            );
    }

    /**
     * No-show rate intentionally uses reached attendance outcomes as its
     * denominator: completed + no-show.
     *
     * Cancelled, declined, pending and confirmed appointments have not reached
     * an attendance outcome and therefore do not dilute the no-show rate.
     *
     * @param array<string, int> $appointmentStatistics
     *
     * @return array<string, int|float|null>
     */
    private function noShowAnalytics(
        array $appointmentStatistics
    ): array {
        $completed =
            $appointmentStatistics[
                'completed'
            ];

        $noShow =
            $appointmentStatistics[
                'no_show'
            ];

        $attendanceOutcomes =
            $completed
            + $noShow;

        return [
            'total_bookings' =>
                $appointmentStatistics[
                    'total'
                ],

            'attendance_outcomes' =>
                $attendanceOutcomes,

            'completed' =>
                $completed,

            'no_show' =>
                $noShow,

            'completion_rate' =>
                $this->percentage(
                    $completed,
                    $attendanceOutcomes
                ),

            'no_show_rate' =>
                $this->percentage(
                    $noShow,
                    $attendanceOutcomes
                ),
        ];
    }

    /**
     * Rank shops by appointment volume for the selected report period.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string|null>>
     */
    private function topShops(
        array $filters
    ): array {
        $rows =
            $this->appointmentActivityQuery(
                $filters
            )
                ->select([
                    'shop_id',
                    'status',
                ])
                ->selectRaw(
                    'COUNT(*) as aggregate_count'
                )
                ->selectRaw(
                    'SUM(price_snapshot) as aggregate_revenue'
                )
                ->groupBy([
                    'shop_id',
                    'status',
                ])
                ->get();

        $shopIds =
            $rows
                ->pluck(
                    'shop_id'
                )
                ->filter()
                ->unique()
                ->values();

        $shopNames =
            Shop::withTrashed()
                ->whereIn(
                    'id',
                    $shopIds
                )
                ->pluck(
                    'name',
                    'id'
                );

        $shops = [];

        foreach (
            $rows as $row
        ) {
            $shopId =
                (int) $row
                    ->shop_id;

            if (
                ! isset(
                    $shops[$shopId]
                )
            ) {
                $shops[$shopId] = [
                    'shop_id' =>
                        $shopId,

                    'shop_name' =>
                        (string) (
                            $shopNames->get(
                                $shopId
                            )
                            ?? 'Unavailable Shop'
                        ),

                    'total_bookings' =>
                        0,

                    'completed_bookings' =>
                        0,

                    'no_show_count' =>
                        0,

                    'completed_revenue' =>
                        0.0,
                ];
            }

            $this->accumulatePerformanceRow(
                target:
                    $shops[$shopId],

                row:
                    $row,
            );
        }

        return $this->rankPerformanceRows(
            $shops,
            self::TOP_SHOPS_LIMIT,
            'shop_name'
        );
    }

    /**
     * Rank individual services across the platform by appointment volume for
     * the selected report period. Appointment service_name is a booking-time
     * snapshot, so deleted or later-renamed services remain reportable.
     *
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, int|float|string|null>>
     */
    private function topServices(
        array $filters
    ): array {
        $rows =
            $this->appointmentActivityQuery(
                $filters
            )
                ->select([
                    'service_id',
                    'service_name',
                    'shop_id',
                    'status',
                ])
                ->selectRaw(
                    'COUNT(*) as aggregate_count'
                )
                ->selectRaw(
                    'SUM(price_snapshot) as aggregate_revenue'
                )
                ->groupBy([
                    'service_id',
                    'service_name',
                    'shop_id',
                    'status',
                ])
                ->get();

        $shopIds =
            $rows
                ->pluck(
                    'shop_id'
                )
                ->filter()
                ->unique()
                ->values();

        $shopNames =
            Shop::withTrashed()
                ->whereIn(
                    'id',
                    $shopIds
                )
                ->pluck(
                    'name',
                    'id'
                );

        $services = [];

        foreach (
            $rows as $row
        ) {
            $serviceId =
                (int) $row
                    ->service_id;

            $shopId =
                (int) $row
                    ->shop_id;

            if (
                ! isset(
                    $services[$serviceId]
                )
            ) {
                $services[$serviceId] = [
                    'service_id' =>
                        $serviceId,

                    'service_name' =>
                        (string) $row
                            ->service_name,

                    'shop_id' =>
                        $shopId,

                    'shop_name' =>
                        (string) (
                            $shopNames->get(
                                $shopId
                            )
                            ?? 'Unavailable Shop'
                        ),

                    'total_bookings' =>
                        0,

                    'completed_bookings' =>
                        0,

                    'no_show_count' =>
                        0,

                    'completed_revenue' =>
                        0.0,
                ];
            }

            $this->accumulatePerformanceRow(
                target:
                    $services[$serviceId],

                row:
                    $row,
            );
        }

        return $this->rankPerformanceRows(
            $services,
            self::TOP_SERVICES_LIMIT,
            'service_name'
        );
    }

    /**
     * @param array<string, int|float|string|null> $target
     * @param mixed $row
     */
    private function accumulatePerformanceRow(
        array &$target,
        mixed $row
    ): void {
        $count =
            (int) $row
                ->aggregate_count;

        $status =
            $row->status
            instanceof AppointmentStatus
                ? $row->status->value
                : (string) $row->status;

        $target[
            'total_bookings'
        ] += $count;

        if (
            $status
            === AppointmentStatus::Completed
                ->value
        ) {
            $target[
                'completed_bookings'
            ] += $count;

            $target[
                'completed_revenue'
            ] += (float) $row
                ->aggregate_revenue;
        }

        if (
            $status
            === AppointmentStatus::NoShow
                ->value
        ) {
            $target[
                'no_show_count'
            ] += $count;
        }
    }

    /**
     * @param array<int, array<string, int|float|string|null>> $rows
     *
     * @return list<array<string, int|float|string|null>>
     */
    private function rankPerformanceRows(
        array $rows,
        int $limit,
        string $nameKey
    ): array {
        return (new Collection(
            array_values(
                $rows
            )
        ))
            ->map(
                function (
                    array $row
                ): array {
                    $attendanceOutcomes =
                        $row[
                            'completed_bookings'
                        ]
                        + $row[
                            'no_show_count'
                        ];

                    $row[
                        'attendance_outcomes'
                    ] = $attendanceOutcomes;

                    $row[
                        'no_show_rate'
                    ] = $this->percentage(
                        $row[
                            'no_show_count'
                        ],
                        $attendanceOutcomes
                    );

                    $row[
                        'completed_revenue'
                    ] = round(
                        (float) $row[
                            'completed_revenue'
                        ],
                        2
                    );

                    return $row;
                }
            )
            ->sort(
                function (
                    array $left,
                    array $right
                ) use (
                    $nameKey
                ): int {
                    $bookingComparison =
                        $right[
                            'total_bookings'
                        ]
                        <=>
                        $left[
                            'total_bookings'
                        ];

                    if (
                        $bookingComparison !== 0
                    ) {
                        return $bookingComparison;
                    }

                    $revenueComparison =
                        $right[
                            'completed_revenue'
                        ]
                        <=>
                        $left[
                            'completed_revenue'
                        ];

                    if (
                        $revenueComparison !== 0
                    ) {
                        return $revenueComparison;
                    }

                    return strcasecmp(
                        (string) $left[
                            $nameKey
                        ],
                        (string) $right[
                            $nameKey
                        ]
                    );
                }
            )
            ->take(
                $limit
            )
            ->values()
            ->all();
    }

    private function percentage(
        int $numerator,
        int $denominator
    ): ?float {
        if ($denominator <= 0) {
            return null;
        }

        return round(
            ($numerator / $denominator)
            * 100,
            2
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function feedbackStatistics(
        array $filters
    ): array {
        $query =
            $this->feedbackActivityQuery(
                $filters
            );

        $average =
            (clone $query)
                ->avg(
                    'rating'
                );

        return [
            'total' =>
                (clone $query)
                    ->count(),

            'average_rating' =>
                $average === null
                    ? null
                    : round(
                        (float) $average,
                        2
                    ),

            'rating_distribution' => [
                1 =>
                    (clone $query)
                        ->where(
                            'rating',
                            1
                        )
                        ->count(),

                2 =>
                    (clone $query)
                        ->where(
                            'rating',
                            2
                        )
                        ->count(),

                3 =>
                    (clone $query)
                        ->where(
                            'rating',
                            3
                        )
                        ->count(),

                4 =>
                    (clone $query)
                        ->where(
                            'rating',
                            4
                        )
                        ->count(),

                5 =>
                    (clone $query)
                        ->where(
                            'rating',
                            5
                        )
                        ->count(),
            ],
        ];
    }

    /**
     * Feedback report periods use feedback submission time.
     *
     * @param array<string, mixed> $filters
     *
     * @return Builder<Feedback>
     */
    private function feedbackActivityQuery(
        array $filters
    ): Builder {
        return Feedback::query()
            ->when(
                isset(
                    $filters['date_from']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'created_at',
                        '>=',
                        $filters['date_from']
                    )
            )
            ->when(
                isset(
                    $filters['date_to']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'created_at',
                        '<=',
                        $filters['date_to']
                    )
            );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function rescheduleRequestStatistics(
        array $filters
    ): array {
        $query =
            $this->rescheduleRequestActivityQuery(
                $filters
            );

        return [
            'total' =>
                (clone $query)
                    ->count(),

            'pending' =>
                (clone $query)
                    ->where(
                        'status',
                        RescheduleRequestStatus::Pending
                            ->value
                    )
                    ->count(),

            'approved' =>
                (clone $query)
                    ->where(
                        'status',
                        RescheduleRequestStatus::Approved
                            ->value
                    )
                    ->count(),

            'rejected' =>
                (clone $query)
                    ->where(
                        'status',
                        RescheduleRequestStatus::Rejected
                            ->value
                    )
                    ->count(),

            'withdrawn' =>
                (clone $query)
                    ->where(
                        'status',
                        RescheduleRequestStatus::Withdrawn
                            ->value
                    )
                    ->count(),
        ];
    }

    /**
     * Reschedule report periods use request submission time.
     *
     * @param array<string, mixed> $filters
     *
     * @return Builder<RescheduleRequest>
     */
    private function rescheduleRequestActivityQuery(
        array $filters
    ): Builder {
        return RescheduleRequest::query()
            ->when(
                isset(
                    $filters['date_from']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'created_at',
                        '>=',
                        $filters['date_from']
                    )
            )
            ->when(
                isset(
                    $filters['date_to']
                ),
                fn (
                    Builder $query
                ) =>
                    $query->whereDate(
                        'created_at',
                        '<=',
                        $filters['date_to']
                    )
            );
    }
}