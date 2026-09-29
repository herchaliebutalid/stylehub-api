<?php

namespace App\Services\Analytics;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ShopOwnerAnalyticsService
{
    private const DEFAULT_PERIOD_DAYS = 30;

    private const TOP_SERVICES_LIMIT = 5;

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function overview(
        Shop $shop,
        array $filters
    ): array {
        [$dateFrom, $dateTo] =
            $this->resolvePeriod(
                $filters
            );

        $appointments =
            $this->appointmentQuery(
                shop:
                    $shop,

                dateFrom:
                    $dateFrom,

                dateTo:
                    $dateTo,
            );

        $statusCounts =
            $this->statusCounts(
                $appointments
            );

        $totalBookings =
            array_sum(
                $statusCounts
            );

        $completedRevenue =
            (float) (
                clone $appointments
            )
                ->where(
                    'status',
                    AppointmentStatus::Completed
                        ->value
                )
                ->sum(
                    'price_snapshot'
                );

        $uniqueCustomers =
            (clone $appointments)
                ->distinct()
                ->count(
                    'customer_id'
                );

        $rating =
            $this->ratingStatistics(
                shop:
                    $shop,

                dateFrom:
                    $dateFrom,

                dateTo:
                    $dateTo,
            );

        return [
            'period' => [
                'date_from' =>
                    $dateFrom
                        ->toDateString(),

                'date_to' =>
                    $dateTo
                        ->toDateString(),

                'days' =>
                    (int) $dateFrom
                        ->diffInDays(
                            $dateTo
                        ) + 1,

                'is_default' =>
                    ! isset(
                        $filters['date_from'],
                        $filters['date_to']
                    ),
            ],

            'summary' => [
                'total_bookings' =>
                    $totalBookings,

                'unique_customers' =>
                    $uniqueCustomers,

                'completed_revenue' =>
                    round(
                        $completedRevenue,
                        2
                    ),

                'average_rating' =>
                    $rating[
                        'average_rating'
                    ],

                'review_count' =>
                    $rating[
                        'review_count'
                    ],
            ],

            'status_counts' =>
                $statusCounts,

            'performance' =>
                $this->performance(
                    statusCounts:
                        $statusCounts,

                    totalBookings:
                        $totalBookings,
                ),

            'daily_trend' =>
                $this->dailyTrend(
                    appointments:
                        $appointments,

                    dateFrom:
                        $dateFrom,

                    dateTo:
                        $dateTo,
                ),

            'top_services' =>
                $this->topServices(
                    appointments:
                        $appointments
                ),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(
        array $filters
    ): array {
        $timezone =
            (string) config(
                'app.timezone',
                'Asia/Manila'
            );

        if (
            isset(
                $filters['date_from'],
                $filters['date_to']
            )
        ) {
            return [
                CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $filters['date_from'],
                    $timezone
                )
                    ->startOfDay(),

                CarbonImmutable::createFromFormat(
                    'Y-m-d',
                    (string) $filters['date_to'],
                    $timezone
                )
                    ->startOfDay(),
            ];
        }

        $dateTo =
            CarbonImmutable::now(
                $timezone
            )->startOfDay();

        return [
            $dateTo->subDays(
                self::DEFAULT_PERIOD_DAYS - 1
            ),

            $dateTo,
        ];
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointmentQuery(
        Shop $shop,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo
    ): Builder {
        return Appointment::query()
            ->where(
                'shop_id',
                $shop->id
            )
            ->whereDate(
                'appointment_date',
                '>=',
                $dateFrom->toDateString()
            )
            ->whereDate(
                'appointment_date',
                '<=',
                $dateTo->toDateString()
            );
    }

    /**
     * @param Builder<Appointment> $appointments
     *
     * @return array{
     *     pending: int,
     *     confirmed: int,
     *     completed: int,
     *     no_show: int,
     *     cancelled: int,
     *     declined: int
     * }
     */
    private function statusCounts(
        Builder $appointments
    ): array {
        $raw =
            (clone $appointments)
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

        return [
            'pending' =>
                (int) $raw->get(
                    AppointmentStatus::Pending
                        ->value,
                    0
                ),

            'confirmed' =>
                (int) $raw->get(
                    AppointmentStatus::Confirmed
                        ->value,
                    0
                ),

            'completed' =>
                (int) $raw->get(
                    AppointmentStatus::Completed
                        ->value,
                    0
                ),

            'no_show' =>
                (int) $raw->get(
                    AppointmentStatus::NoShow
                        ->value,
                    0
                ),

            'cancelled' =>
                (int) $raw->get(
                    AppointmentStatus::Cancelled
                        ->value,
                    0
                ),

            'declined' =>
                (int) $raw->get(
                    AppointmentStatus::Declined
                        ->value,
                    0
                ),
        ];
    }

    /**
     * @param array{
     *     pending: int,
     *     confirmed: int,
     *     completed: int,
     *     no_show: int,
     *     cancelled: int,
     *     declined: int
     * } $statusCounts
     *
     * @return array<string, int|float|null>
     */
    private function performance(
        array $statusCounts,
        int $totalBookings
    ): array {
        $attendanceOutcomes =
            $statusCounts['completed']
            +
            $statusCounts['no_show'];

        return [
            'attendance_outcomes' =>
                $attendanceOutcomes,

            /*
             * Completed vs No-show represents appointments
             * that reached their attendance outcome.
             */
            'completion_rate' =>
                $this->percentage(
                    $statusCounts[
                        'completed'
                    ],
                    $attendanceOutcomes
                ),

            'no_show_rate' =>
                $this->percentage(
                    $statusCounts[
                        'no_show'
                    ],
                    $attendanceOutcomes
                ),

            /*
             * Cancellation and decline rates use all bookings
             * during the selected reporting period.
             */
            'cancellation_rate' =>
                $this->percentage(
                    $statusCounts[
                        'cancelled'
                    ],
                    $totalBookings
                ),

            'decline_rate' =>
                $this->percentage(
                    $statusCounts[
                        'declined'
                    ],
                    $totalBookings
                ),
        ];
    }

    /**
     * @return array{
     *     average_rating: float|null,
     *     review_count: int
     * }
     */
    private function ratingStatistics(
        Shop $shop,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo
    ): array {
        $query =
            Feedback::query()
                ->verified()
                ->where(
                    'shop_id',
                    $shop->id
                )
                ->whereHas(
                    'appointment',
                    function (
                        Builder $appointmentQuery
                    ) use (
                        $dateFrom,
                        $dateTo
                    ): void {
                        $appointmentQuery
                            ->whereDate(
                                'appointment_date',
                                '>=',
                                $dateFrom
                                    ->toDateString()
                            )
                            ->whereDate(
                                'appointment_date',
                                '<=',
                                $dateTo
                                    ->toDateString()
                            );
                    }
                );

        $average =
            (clone $query)
                ->avg(
                    'rating'
                );

        return [
            'average_rating' =>
                $average === null
                    ? null
                    : round(
                        (float) $average,
                        2
                    ),

            'review_count' =>
                (clone $query)
                    ->count(),
        ];
    }

    /**
     * @param Builder<Appointment> $appointments
     *
     * @return list<array<string, int|float|string>>
     */
    private function dailyTrend(
        Builder $appointments,
        CarbonImmutable $dateFrom,
        CarbonImmutable $dateTo
    ): array {
        $rows =
            (clone $appointments)
                ->select([
                    'appointment_date',
                    'status',
                ])
                ->selectRaw(
                    'COUNT(*) as aggregate_count'
                )
                ->selectRaw(
                    'SUM(price_snapshot) as aggregate_revenue'
                )
                ->groupBy([
                    'appointment_date',
                    'status',
                ])
                ->get();

        $trend = [];

        for (
            $date = $dateFrom;
            $date->lessThanOrEqualTo(
                $dateTo
            );
            $date = $date->addDay()
        ) {
            $key =
                $date->toDateString();

            $trend[$key] = [
                'date' =>
                    $key,

                'total_bookings' =>
                    0,

                'completed' =>
                    0,

                'no_show' =>
                    0,

                'cancelled' =>
                    0,

                'completed_revenue' =>
                    0.0,
            ];
        }

        foreach (
            $rows as $row
        ) {
            $appointmentDate =
                $row->appointment_date;

            $date =
                $appointmentDate
                instanceof CarbonImmutable
                    ? $appointmentDate
                        ->toDateString()
                    : substr(
                        (string) $appointmentDate,
                        0,
                        10
                    );

            if (
                ! isset(
                    $trend[$date]
                )
            ) {
                continue;
            }

            $count =
                (int) $row
                    ->aggregate_count;

            $status =
                $row->status
                instanceof AppointmentStatus
                    ? $row->status->value
                    : (string) $row->status;

            $trend[$date][
                'total_bookings'
            ] += $count;

            if (
                $status
                === AppointmentStatus::Completed
                    ->value
            ) {
                $trend[$date][
                    'completed'
                ] += $count;

                $trend[$date][
                    'completed_revenue'
                ] += (float) $row
                    ->aggregate_revenue;
            }

            if (
                $status
                === AppointmentStatus::NoShow
                    ->value
            ) {
                $trend[$date][
                    'no_show'
                ] += $count;
            }

            if (
                $status
                === AppointmentStatus::Cancelled
                    ->value
            ) {
                $trend[$date][
                    'cancelled'
                ] += $count;
            }
        }

        foreach (
            $trend as &$day
        ) {
            $day[
                'completed_revenue'
            ] = round(
                $day[
                    'completed_revenue'
                ],
                2
            );
        }

        unset(
            $day
        );

        return array_values(
            $trend
        );
    }

    /**
     * @param Builder<Appointment> $appointments
     *
     * @return list<array<string, int|float|string>>
     */
    private function topServices(
        Builder $appointments
    ): array {
        $rows =
            (clone $appointments)
                ->select([
                    'service_id',
                    'service_name',
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
                    'status',
                ])
                ->get();

        $services = [];

        foreach (
            $rows as $row
        ) {
            $serviceId =
                (int) $row
                    ->service_id;

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

            $count =
                (int) $row
                    ->aggregate_count;

            $status =
                $row->status
                instanceof AppointmentStatus
                    ? $row->status->value
                    : (string) $row->status;

            $services[$serviceId][
                'total_bookings'
            ] += $count;

            if (
                $status
                === AppointmentStatus::Completed
                    ->value
            ) {
                $services[$serviceId][
                    'completed_bookings'
                ] += $count;

                $services[$serviceId][
                    'completed_revenue'
                ] += (float) $row
                    ->aggregate_revenue;
            }

            if (
                $status
                === AppointmentStatus::NoShow
                    ->value
            ) {
                $services[$serviceId][
                    'no_show_count'
                ] += $count;
            }
        }

        $collection =
            new Collection(
                array_values(
                    $services
                )
            );

        return $collection
            ->sort(
                function (
                    array $left,
                    array $right
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
                        $left[
                            'service_name'
                        ],
                        $right[
                            'service_name'
                        ]
                    );
                }
            )
            ->take(
                self::TOP_SERVICES_LIMIT
            )
            ->map(
                function (
                    array $service
                ): array {
                    $service[
                        'completed_revenue'
                    ] = round(
                        $service[
                            'completed_revenue'
                        ],
                        2
                    );

                    return $service;
                }
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
}