<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>StyleHub Administrative Performance Report</title>

    <style>
        @if(is_file(public_path('fonts/arial.ttf')))
        @font-face {
            font-family: 'StyleHub Arial';
            src: url('{{ public_path('fonts/arial.ttf') }}') format('truetype');
            font-weight: normal;
        }
        @endif

        @if(is_file(public_path('fonts/arialbd.ttf')))
        @font-face {
            font-family: 'StyleHub Arial';
            src: url('{{ public_path('fonts/arialbd.ttf') }}') format('truetype');
            font-weight: bold;
        }
        @endif

        @page {
            margin: 91px 42px 66px 42px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'StyleHub Arial', Arial, Helvetica, sans-serif;
            font-size: 10px;
            line-height: 1.48;
            color: #000;
            margin: 0;
        }

        .page-header {
            position: fixed;
            top: -66px;
            left: 0;
            right: 0;
            height: 51px;
            border-bottom: 1px solid #000;
        }

        .page-header table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand {
            font-size: 17px;
            font-weight: bold;
            color: #000;
            line-height: 1.15;
        }

        .brand-sub {
            display: block;
            margin-top: 3px;
            font-size: 7.5px;
            letter-spacing: 1px;
            color: #000;
        }

        .header-right {
            text-align: right;
            vertical-align: top;
            padding-top: 3px;
            color: #000;
            font-size: 8px;
        }

        .header-right strong {
            display: block;
            color: #000;
            font-size: 9px;
        }

        .page-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -43px;
            height: 24px;
            border-top: 1px solid #000;
            padding-top: 7px;
            font-size: 7.5px;
            color: #000;
        }

        .eyebrow {
            color: #000;
            letter-spacing: 1.3px;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        h1 {
            color: #000;
            font-size: 18px;
            line-height: 1.25;
            margin: 0 0 4px;
        }

        .subtitle {
            font-size: 9px;
            color: #000;
            margin: 0 0 13px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            background: #fff;
            border: 1px solid #000;
        }

        .meta td {
            width: 50%;
            vertical-align: top;
            padding: 8px 11px;
            border-bottom: 1px solid #000;
        }

        .meta .label {
            display: block;
            color: #000;
            font-size: 7.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .meta .value {
            font-size: 9px;
            font-weight: bold;
            color: #000;
        }

        h2 {
            font-size: 11px;
            margin: 17px 0 7px;
            color: #000;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            page-break-after: avoid;
        }

        h2 .num {
            color: #000;
            margin-right: 6px;
        }

        .prose {
            margin: 0 0 9px;
            line-height: 1.6;
            color: #000;
        }

        .hint {
            margin: 3px 0 8px;
            color: #000;
            font-size: 7.7px;
        }

        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 11px;
        }

        .kpi-table td {
            width: 25%;
            background: #fff;
            border: 1px solid #000;
            padding: 10px 9px;
            vertical-align: top;
        }

        .kpi-label {
            display: block;
            color: #000;
            font-size: 7.7px;
        }

        .kpi-value {
            display: block;
            color: #000;
            font-size: 16px;
            font-weight: bold;
            margin-top: 3px;
        }

        .summary-note {
            background: #fff;
            border: 1px solid #000;
            padding: 8px 10px;
            margin: 8px 0 12px;
            color: #000;
        }

        table.data {
            border-collapse: collapse;
            width: 100%;
            margin: 5px 0 12px;
        }

        table.data thead {
            display: table-header-group;
        }

        table.data th {
            background: #fff;
            color: #000;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            padding: 7px 8px;
            border: 1px solid #000;
        }

        table.data td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 8.2px;
        }

        table.data tbody tr:nth-child(even) td {
            background: #fff;
        }

        table.data tr {
            page-break-inside: avoid;
        }

        table.data td.num,
        table.data th.num {
            text-align: right;
            white-space: nowrap;
        }

        table.data .total td {
            background: #fff;
            font-weight: bold;
            border-top: 1px solid #000;
        }

        .keep {
            page-break-inside: avoid;
        }

        .empty {
            border: 1px solid #000;
            color: #000;
            padding: 10px;
            margin-bottom: 12px;
        }

        .small-label {
            color: #000;
            font-size: 7.3px;
            margin-top: 3px;
        }

        .closing {
            padding-top: 8px;
            margin-top: 12px;
            border-top: 1px solid #000;
            font-size: 8px;
            color: #000;
        }
    </style>
</head>

<body>
@php
    $period = $report['period'] ?? [];
    $users = $report['current_state']['users'] ?? [];
    $customers = $users['customers'] ?? [];
    $owners = $users['shop_owners'] ?? [];
    $shops = $report['current_state']['shops'] ?? [];

    $appointments = $report['activity']['appointments'] ?? [];
    $feedback = $report['activity']['feedback'] ?? [];
    $reschedules = $report['activity']['reschedule_requests'] ?? [];

    $attendance = $report['analytics']['no_show'] ?? [];
    $topShops = $report['analytics']['top_shops'] ?? [];
    $topServices = $report['analytics']['top_services'] ?? [];

    $rangeLabel = ($period['is_all_time'] ?? true)
        ? 'All available records'
        : (($period['date_from'] ?? 'No start limit')
            . ' to '
            . ($period['date_to'] ?? 'No end limit'));

    $n = static fn ($value) =>
        number_format((int) ($value ?? 0));

    $pct = static fn ($value) =>
        $value === null
            ? 'N/A'
            : number_format((float) $value, 2) . '%';

    $totalAccounts =
        (int) ($customers['total'] ?? 0) +
        (int) ($owners['total'] ?? 0);

    $totalAppointments =
        (int) ($appointments['total'] ?? 0);

    $completed =
        (int) ($appointments['completed'] ?? 0);

    $noShowCount =
        (int) ($appointments['no_show'] ?? 0);

    $feedbackCount =
        (int) ($feedback['total'] ?? 0);

    $topShopName = $topShops[0]['shop_name'] ?? null;
@endphp

<!-- PAGE HEADER -->

<div class="page-header">
    <table>
        <tr>
            <td>
                <span class="brand">StyleHub</span>
                <span class="brand-sub">
                    ADMINISTRATION AND ANALYTICS
                </span>
            </td>

            <td class="header-right">
                <strong>PERFORMANCE REPORT</strong>
                Internal management document
            </td>
        </tr>
    </table>
</div>

<!-- PAGE FOOTER -->

<div class="page-footer">
    StyleHub |
    System-generated management report |
    Confidential - authorized administrators only
</div>

<!-- REPORT TITLE -->

<div class="eyebrow">
    REPORTING AND PERFORMANCE
</div>

<h1>Administrative Performance Report</h1>

<p class="subtitle">
    Consolidated operational statistics and platform activity
</p>

<!-- REPORT INFORMATION -->

<table class="meta">
    <tr>
        <td>
            <span class="label">Reporting period</span>
            <span class="value">{{ $rangeLabel }}</span>
        </td>

        <td>
            <span class="label">Report generated</span>
            <span class="value">
                {{ $generatedAt->format('F j, Y - g:i A') }} (PHT)
            </span>
        </td>
    </tr>

    <tr>
        <td>
            <span class="label">Prepared by</span>
            <span class="value">
                StyleHub Automated Reporting System
            </span>
        </td>

        <td>
            <span class="label">Document classification</span>
            <span class="value">
                Internal / Administrator Access
            </span>
        </td>
    </tr>
</table>

<!-- 01: EXECUTIVE SUMMARY -->

<section class="keep">
    <h2>
        <span class="num">01</span>
        Executive Summary
    </h2>

    @if($totalAppointments > 0)
        <p class="prose">
            During the reporting period, StyleHub recorded
            <strong>{{ $n($totalAppointments) }}</strong>
            appointments, including
            <strong>{{ $n($completed) }}</strong>
            completed bookings and
            <strong>{{ $n($noShowCount) }}</strong>
            no-shows.

            The recorded no-show rate was
            <strong>
                {{ $pct($attendance['no_show_rate'] ?? null) }}
            </strong>
            among bookings that reached an attendance outcome.

            Customer feedback totaled
            <strong>{{ $n($feedbackCount) }}</strong>
            {{ $feedbackCount === 1 ? 'entry' : 'entries' }}.
        </p>
    @else
        <p class="prose">
            No appointments were recorded for the selected
            reporting period.

            The remaining sections provide available feedback,
            rescheduling, and current platform information.
        </p>
    @endif

    <table class="kpi-table">
        <tr>
            <td>
                <span class="kpi-label">
                    Total appointments
                </span>

                <span class="kpi-value">
                    {{ $n($totalAppointments) }}
                </span>
            </td>

            <td>
                <span class="kpi-label">
                    Completed bookings
                </span>

                <span class="kpi-value">
                    {{ $n($completed) }}
                </span>
            </td>

            <td>
                <span class="kpi-label">
                    No-show rate
                </span>

                <span class="kpi-value">
                    {{ $pct($attendance['no_show_rate'] ?? null) }}
                </span>
            </td>

            <td>
                <span class="kpi-label">
                    Customer reviews
                </span>

                <span class="kpi-value">
                    {{ $n($feedbackCount) }}
                </span>
            </td>
        </tr>
    </table>

    @if($topShopName)
        <div class="summary-note">
            <strong>Performance highlight:</strong>
            {{ $topShopName }} ranked first by appointment
            volume during the selected reporting period.
        </div>
    @endif
</section>

<!-- 02: APPOINTMENT ACTIVITY -->

<section>
    <h2>
        <span class="num">02</span>
        Appointment Activity
    </h2>

    <p class="hint">
        Counts in this section use the scheduled appointment date.
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Appointment status</th>
                <th class="num">Number of bookings</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Pending</td>
                <td class="num">
                    {{ $n($appointments['pending'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Confirmed</td>
                <td class="num">
                    {{ $n($appointments['confirmed'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Completed</td>
                <td class="num">
                    {{ $n($completed) }}
                </td>
            </tr>

            <tr>
                <td>No-show</td>
                <td class="num">
                    {{ $n($noShowCount) }}
                </td>
            </tr>

            <tr>
                <td>Cancelled</td>
                <td class="num">
                    {{ $n($appointments['cancelled'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Declined</td>
                <td class="num">
                    {{ $n($appointments['declined'] ?? 0) }}
                </td>
            </tr>

            <tr class="total">
                <td>Total appointments</td>
                <td class="num">
                    {{ $n($totalAppointments) }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Unique participation</th>
                <th class="num">Count</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Customers with appointments</td>
                <td class="num">
                    {{ $n($appointments['unique_customers'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Shops receiving appointments</td>
                <td class="num">
                    {{ $n($appointments['unique_shops'] ?? 0) }}
                </td>
            </tr>
        </tbody>
    </table>
</section>

<!-- 03: ATTENDANCE -->

<section class="keep">
    <h2>
        <span class="num">03</span>
        Attendance and Booking Outcomes
    </h2>

    <table class="data">
        <thead>
            <tr>
                <th>Performance indicator</th>
                <th class="num">Result</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>
                    Attendance outcomes (completed + no-show)
                </td>
                <td class="num">
                    {{ $n($attendance['attendance_outcomes'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Completed appointments</td>
                <td class="num">
                    {{ $n($attendance['completed'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>No-show appointments</td>
                <td class="num">
                    {{ $n($attendance['no_show'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Completion rate</td>
                <td class="num">
                    {{ $pct($attendance['completion_rate'] ?? null) }}
                </td>
            </tr>

            <tr>
                <td>No-show rate</td>
                <td class="num">
                    {{ $pct($attendance['no_show_rate'] ?? null) }}
                </td>
            </tr>
        </tbody>
    </table>

    <p class="hint">
        Rates exclude pending, confirmed, cancelled, and
        declined appointments.

        N/A means that no attendance outcomes exist.
    </p>
</section>

<!-- 04: CUSTOMER FEEDBACK -->

<section class="keep">
    <h2>
        <span class="num">04</span>
        Customer Feedback
    </h2>

    <p class="hint">
        Feedback records are filtered by their creation date.
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Feedback indicator</th>
                <th class="num">Value</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Total feedback entries</td>
                <td class="num">
                    {{ $n($feedbackCount) }}
                </td>
            </tr>

            <tr>
                <td>Average customer rating</td>
                <td class="num">
                    {{
                        ($feedback['average_rating'] ?? null) === null
                            ? 'N/A'
                            : number_format(
                                (float) $feedback['average_rating'],
                                2
                            ) . ' / 5'
                    }}
                </td>
            </tr>

            @for($stars = 5; $stars >= 1; $stars--)
                <tr>
                    <td>{{ $stars }}-star ratings</td>
                    <td class="num">
                        {{
                            $n(
                                $feedback['rating_distribution'][$stars]
                                ?? 0
                            )
                        }}
                    </td>
                </tr>
            @endfor
        </tbody>
    </table>
</section>

<!-- 05: RESCHEDULE REQUESTS -->

<section class="keep">
    <h2>
        <span class="num">05</span>
        Reschedule Requests
    </h2>

    <p class="hint">
        Reschedule requests are filtered by their creation date.
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Request status</th>
                <th class="num">Number of requests</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Pending</td>
                <td class="num">
                    {{ $n($reschedules['pending'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Approved</td>
                <td class="num">
                    {{ $n($reschedules['approved'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Rejected</td>
                <td class="num">
                    {{ $n($reschedules['rejected'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Withdrawn</td>
                <td class="num">
                    {{ $n($reschedules['withdrawn'] ?? 0) }}
                </td>
            </tr>

            <tr class="total">
                <td>Total requests</td>
                <td class="num">
                    {{ $n($reschedules['total'] ?? 0) }}
                </td>
            </tr>
        </tbody>
    </table>
</section>

<!-- 06: TOP SHOPS -->

<section>
    <h2>
        <span class="num">06</span>
        Top Performing Shops
    </h2>

    <p class="hint">
        Ranked by appointment volume for the selected period.
        Completed booking value is calculated from price
        snapshots, not verified payment collections.
    </p>

    @if(count($topShops) === 0)
        <p class="empty">
            No shop bookings were found during the selected period.
        </p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Shop</th>
                    <th class="num">Bookings</th>
                    <th class="num">Completed</th>
                    <th class="num">No-shows</th>
                    <th class="num">Value (PHP)</th>
                </tr>
            </thead>

            <tbody>
                @foreach($topShops as $shop)
                    <tr>
                        <td>{{ $loop->iteration }}</td>

                        <td>
                            {{ $shop['shop_name'] ?? 'Unavailable Shop' }}
                        </td>

                        <td class="num">
                            {{ $n($shop['total_bookings'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{ $n($shop['completed_bookings'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{ $n($shop['no_show_count'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{
                                number_format(
                                    (float) (
                                        $shop['completed_revenue'] ?? 0
                                    ),
                                    2
                                )
                            }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<!-- 07: TOP SERVICES -->

<section>
    <h2>
        <span class="num">07</span>
        Top Performing Services
    </h2>

    <p class="hint">
        Based on booking-time service snapshots for the
        selected period.
    </p>

    @if(count($topServices) === 0)
        <p class="empty">
            No service bookings were found during the selected period.
        </p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service / Shop</th>
                    <th class="num">Bookings</th>
                    <th class="num">Completed</th>
                    <th class="num">No-shows</th>
                    <th class="num">Value (PHP)</th>
                </tr>
            </thead>

            <tbody>
                @foreach($topServices as $service)
                    <tr>
                        <td>{{ $loop->iteration }}</td>

                        <td>
                            <strong>
                                {{
                                    $service['service_name']
                                    ?? 'Unavailable Service'
                                }}
                            </strong>

                            <div class="small-label">
                                {{
                                    $service['shop_name']
                                    ?? 'Unavailable Shop'
                                }}
                            </div>
                        </td>

                        <td class="num">
                            {{ $n($service['total_bookings'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{ $n($service['completed_bookings'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{ $n($service['no_show_count'] ?? 0) }}
                        </td>

                        <td class="num">
                            {{
                                number_format(
                                    (float) (
                                        $service['completed_revenue'] ?? 0
                                    ),
                                    2
                                )
                            }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>

<!-- 08: PLATFORM OVERVIEW -->

<section>
    <h2>
        <span class="num">08</span>
        Current Platform Overview
    </h2>

    <p class="hint">
        The figures below are live snapshots at the moment
        of generation.

        They do not represent historical account and shop
        totals for the selected date range.
    </p>

    <table class="data">
        <thead>
            <tr>
                <th>Account category</th>
                <th class="num">Total</th>
                <th class="num">Active</th>
                <th class="num">Suspended</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>Customers</td>
                <td class="num">
                    {{ $n($customers['total'] ?? 0) }}
                </td>
                <td class="num">
                    {{ $n($customers['active'] ?? 0) }}
                </td>
                <td class="num">
                    {{ $n($customers['suspended'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Shop owners</td>
                <td class="num">
                    {{ $n($owners['total'] ?? 0) }}
                </td>
                <td class="num">
                    {{ $n($owners['active'] ?? 0) }}
                </td>
                <td class="num">
                    {{ $n($owners['suspended'] ?? 0) }}
                </td>
            </tr>

            <tr class="total">
                <td>Managed accounts</td>
                <td class="num">
                    {{ $n($totalAccounts) }}
                </td>
                <td class="num">
                    {{
                        $n(
                            ($customers['active'] ?? 0)
                            + ($owners['active'] ?? 0)
                        )
                    }}
                </td>
                <td class="num">
                    {{
                        $n(
                            ($customers['suspended'] ?? 0)
                            + ($owners['suspended'] ?? 0)
                        )
                    }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Shop indicator</th>
                <th class="num">Count</th>
                <th>Shop indicator</th>
                <th class="num">Count</th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>All shops</td>
                <td class="num">
                    {{ $n($shops['total'] ?? 0) }}
                </td>
                <td>Approved</td>
                <td class="num">
                    {{ $n($shops['approval']['approved'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Pending approval</td>
                <td class="num">
                    {{ $n($shops['approval']['pending'] ?? 0) }}
                </td>
                <td>Rejected</td>
                <td class="num">
                    {{ $n($shops['approval']['rejected'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Open</td>
                <td class="num">
                    {{ $n($shops['availability']['open'] ?? 0) }}
                </td>
                <td>Closed</td>
                <td class="num">
                    {{ $n($shops['availability']['closed'] ?? 0) }}
                </td>
            </tr>

            <tr>
                <td>Fully booked</td>
                <td class="num">
                    {{ $n($shops['availability']['fully_booked'] ?? 0) }}
                </td>
                <td>Accepting walk-ins</td>
                <td class="num">
                    {{
                        $n(
                            $shops['availability']['accepting_walk_ins']
                            ?? 0
                        )
                    }}
                </td>
            </tr>
        </tbody>
    </table>
</section>

<!-- 09: REPORT NOTES -->

<section class="keep">
    <h2>
        <span class="num">09</span>
        Report Notes and Interpretation
    </h2>

    <p class="prose">
        This report is generated from StyleHub's available
        system records using the selected reporting dates.

        It is intended for administrative review, operational
        monitoring, and documentation.

        All summary figures represent recorded activity;
        they should not be interpreted as independently
        audited financial or attendance records.
    </p>

    <div class="closing">
        <strong>End of report.</strong>

        Generated automatically by StyleHub on
        {{ $generatedAt->format('F j, Y') }}.

        No manual adjustments were made to the
        statistics in this document.
    </div>
</section>

</body>
</html>