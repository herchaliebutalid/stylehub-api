
<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const filters = reactive({
    date_from: '',
    date_to: '',
});

const report = ref(null);
const generatedAt = ref(null);
const isLoading = ref(false);
const isDownloading = ref(false);
const errorMessage = ref('');
const retryAction = ref('overview');

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-PH');

const percentage = (value) =>
    value == null ? 'N/A' : `${Number(value).toFixed(2)}%`;

const money = (value) =>
    `₱${Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;

const dateOnly = (value) =>
    value
        ? new Date(`${value}T00:00:00`).toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '—';

const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString('en-PH', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';

const periodLabel = computed(() => {
    const period = report.value?.period;

    if (!period || period.is_all_time) {
        return 'All available records';
    }

    if (period.date_from && period.date_to) {
        return `${dateOnly(period.date_from)} – ${dateOnly(period.date_to)}`;
    }

    if (period.date_from) {
        return `From ${dateOnly(period.date_from)}`;
    }

    return `Until ${dateOnly(period.date_to)}`;
});

const users = computed(
    () => report.value?.current_state?.users ?? {},
);

const shops = computed(
    () => report.value?.current_state?.shops ?? {},
);

const appointments = computed(
    () => report.value?.activity?.appointments ?? {},
);

const feedback = computed(
    () => report.value?.activity?.feedback ?? {},
);

const reschedules = computed(
    () => report.value?.activity?.reschedule_requests ?? {},
);

const noShows = computed(
    () => report.value?.analytics?.no_show ?? {},
);

const topShops = computed(
    () => report.value?.analytics?.top_shops ?? [],
);

const topServices = computed(
    () => report.value?.analytics?.top_services ?? [],
);

const summaryCards = computed(() => [
    {
        label: 'Managed accounts',
        value:
            (users.value.customers?.total ?? 0) +
            (users.value.shop_owners?.total ?? 0),
        detail: `${number(
            (users.value.customers?.active ?? 0) +
                (users.value.shop_owners?.active ?? 0),
        )} active accounts`,
        icon: 'accounts',
    },
    {
        label: 'Customers',
        value: users.value.customers?.total,
        detail: `${number(
            users.value.customers?.suspended,
        )} suspended`,
        icon: 'customers',
    },
    {
        label: 'Shop owners',
        value: users.value.shop_owners?.total,
        detail: `${number(
            users.value.shop_owners?.suspended,
        )} suspended`,
        icon: 'owners',
    },
    {
        label: 'Registered shops',
        value: shops.value.total,
        detail: `${number(
            shops.value.approval?.approved,
        )} approved`,
        icon: 'shops',
    },
]);

const approvalStats = computed(() => [
    ['Approved', shops.value.approval?.approved],
    ['Pending', shops.value.approval?.pending],
    ['Rejected', shops.value.approval?.rejected],
]);

const availabilityStats = computed(() => [
    ['Open', shops.value.availability?.open],
    ['Closed', shops.value.availability?.closed],
    ['Fully booked', shops.value.availability?.fully_booked],
    [
        'Accepting walk-ins',
        shops.value.availability?.accepting_walk_ins,
    ],
]);

const appointmentStats = computed(() => [
    ['Total bookings', appointments.value.total],
    ['Pending', appointments.value.pending],
    ['Confirmed', appointments.value.confirmed],
    ['Completed', appointments.value.completed],
    ['No-show', appointments.value.no_show],
    ['Cancelled', appointments.value.cancelled],
    ['Declined', appointments.value.declined],
    ['Unique customers', appointments.value.unique_customers],
    ['Unique shops', appointments.value.unique_shops],
]);

const performanceStats = computed(() => [
    ['Attendance outcomes', number(noShows.value.attendance_outcomes)],
    ['Completed', number(noShows.value.completed)],
    ['No-shows', number(noShows.value.no_show)],
    ['Completion rate', percentage(noShows.value.completion_rate)],
    ['No-show rate', percentage(noShows.value.no_show_rate)],
]);

const rescheduleStats = computed(() => [
    ['Total', reschedules.value.total],
    ['Pending', reschedules.value.pending],
    ['Approved', reschedules.value.approved],
    ['Rejected', reschedules.value.rejected],
    ['Withdrawn', reschedules.value.withdrawn],
]);

const ratingRows = computed(() => {
    const distribution =
        feedback.value.rating_distribution ?? {};

    const maximum = Math.max(
        1,
        ...Object.values(distribution).map(Number),
    );

    return [5, 4, 3, 2, 1].map((stars) => ({
        stars,
        count: Number(distribution[stars] ?? 0),
        width: `${
            (Number(distribution[stars] ?? 0) / maximum) * 100
        }%`,
    }));
});

function queryString() {
    const params = new URLSearchParams();

    if (filters.date_from) {
        params.set('date_from', filters.date_from);
    }

    if (filters.date_to) {
        params.set('date_to', filters.date_to);
    }

    return params.toString();
}

function validateDates() {
    errorMessage.value = '';

    if (!filters.date_from || !filters.date_to) {
        return true;
    }

    const from = new Date(`${filters.date_from}T00:00:00`);
    const to = new Date(`${filters.date_to}T00:00:00`);

    const days = Math.round(
        (to.getTime() - from.getTime()) / 86400000,
    );

    if (days < 0) {
        errorMessage.value =
            'Date to must be the same as or later than Date from.';
    } else if (days > 366) {
        errorMessage.value =
            'The reporting period may not exceed 366 days.';
    } else {
        return true;
    }

    retryAction.value = 'dismiss';
    return false;
}

async function authenticateAgain() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
|
| The displayed statistics are always unfiltered.
| Dates are used only when generating the selected PDF.
|
*/

async function loadReport() {
    if (!auth.token) {
        await authenticateAgain();
        return false;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            '/admin/reports/overview',
            {
                token: auth.token,
            },
        );

        if (
            !response?.data ||
            typeof response.data !== 'object'
        ) {
            throw new Error(
                'The report response was invalid.',
            );
        }

        report.value = response.data;
        generatedAt.value = response.generated_at ?? null;

        return true;
    } catch (error) {
        retryAction.value = 'overview';

        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            await authenticateAgain();
            return false;
        }

        errorMessage.value =
            error?.data?.message ??
            error?.message ??
            'Unable to load the report.';

        return false;
    } finally {
        isLoading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| PDF GENERATION
|--------------------------------------------------------------------------
*/

async function downloadPdf(useSelectedDates) {
    if (
        !auth.token ||
        isLoading.value ||
        isDownloading.value
    ) {
        return;
    }

    isDownloading.value = true;
    errorMessage.value = '';

    try {
        const params = new URLSearchParams(
            useSelectedDates ? queryString() : '',
        );

        params.set('format', 'pdf');

        const response = await fetch(
            `/api/v1/admin/reports/overview?${params.toString()}`,
            {
                method: 'GET',
                headers: {
                    Accept: 'application/pdf',
                    Authorization: `Bearer ${auth.token}`,
                },
                credentials: 'same-origin',
                cache: 'no-store',
            },
        );

        if (!response.ok) {
            if (
                response.status === 401 ||
                response.status === 403
            ) {
                await authenticateAgain();
                return;
            }

            const details = await response
                .json()
                .catch(() => null);

            throw new Error(
                details?.message ??
                    'Unable to download the PDF report.',
            );
        }

        const contentType =
            response.headers.get('content-type') ?? '';

        if (!contentType.includes('application/pdf')) {
            throw new Error(
                'The server did not return a PDF.',
            );
        }

        const blob = await response.blob();

        const disposition =
            response.headers.get('content-disposition') ?? '';

        const filename =
            disposition.match(
                /filename="?([^";]+)"?/i,
            )?.[1] ?? 'StyleHub_Report.pdf';

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;

        document.body.appendChild(link);
        link.click();
        link.remove();

        window.setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 1000);
    } catch (error) {
        retryAction.value = useSelectedDates
            ? 'selectedPdf'
            : 'latestPdf';

        errorMessage.value =
            error?.message ??
            'Unable to download the PDF report.';
    } finally {
        isDownloading.value = false;
    }
}

async function generateReport() {
    if (
        isDownloading.value ||
        isLoading.value ||
        !validateDates()
    ) {
        return;
    }

    await downloadPdf(true);
}

async function generateLatestReport() {
    await downloadPdf(false);
}

async function retryFailure() {
    if (retryAction.value === 'selectedPdf') {
        await generateReport();
    } else if (retryAction.value === 'latestPdf') {
        await generateLatestReport();
    } else if (retryAction.value === 'overview') {
        await loadReport();
    } else {
        errorMessage.value = '';
    }
}

function openShop(id) {
    if (!id) {
        return;
    }

    router.push({
        name: 'admin.shop.detail',
        params: {
            shopId: id,
        },
    });
}

// Load before displaying the page, using AdminLayout Suspense.
await loadReport();
</script>

<template>
    <div class="reports-page">

        <!-- INTRODUCTION -->

        <section class="sh-report-intro">
            <div class="sh-report-intro-copy">
                <h2>Platform performance reports</h2>

                <p>
                    Review platform activity, bookings,
                    shop performance and customer feedback.
                </p>
            </div>

            <div class="sh-report-intro-actions">
                <div class="sh-report-total-card">
                    <span class="sh-report-total-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M4 19V11M10 19V5M16 19v-8M22 19H2"
                            />
                        </svg>
                    </span>

                    <div>
                        <span>Managed accounts</span>

                        <strong>
                            {{ number(summaryCards[0].value) }}
                        </strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button sh-report-refresh"
                    :disabled="isLoading || isDownloading"
                    @click="loadReport"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path
                            d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"
                        />
                    </svg>

                    <span>Refresh</span>
                </button>
            </div>
        </section>

        <!-- SUMMARY CARDS -->

        <section
            v-if="report"
            class="sh-report-summary-grid"
        >
            <article
                v-for="card in summaryCards"
                :key="card.label"
                class="sh-report-summary-card"
            >
                <div
                    class="sh-report-summary-icon"
                    :class="`icon-${card.icon}`"
                >
                    <svg
                        v-if="card.icon === 'accounts'"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="9" cy="8" r="3" />
                        <path d="M3 20c0-4 2-7 6-7s6 3 6 7" />
                        <circle cx="17" cy="9" r="2.5" />
                        <path d="M15 14c4 0 6 2 6 6" />
                    </svg>

                    <svg
                        v-else-if="card.icon === 'customers'"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="8" r="3.5" />

                        <path
                            d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                        />
                    </svg>

                    <svg
                        v-else-if="card.icon === 'owners'"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M4 10h16l-2-6H6l-2 6ZM5 10v10h14V10M9 20v-6h6v6"
                        />
                    </svg>

                    <svg
                        v-else
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M3 10 12 3l9 7v11H3V10ZM9 21v-8h6v8"
                        />
                    </svg>
                </div>

                <div class="sh-report-summary-copy">
                    <span>{{ card.label }}</span>

                    <strong>
                        {{ number(card.value) }}
                    </strong>

                    <small>{{ card.detail }}</small>
                </div>
            </article>
        </section>

        <!-- GENERATE REPORT -->

        <section class="filter-card sh-report-filter-card">
            <div class="sh-report-filter-heading">
                <div class="sh-report-filter-heading-left">
                    <div class="sh-report-filter-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                    </div>

                    <div>
                        <h3>Generate report</h3>

                        <p>
                            Select a date range for the PDF report.
                        </p>
                    </div>
                </div>

                <span class="sh-report-heading-note">
                    {{ periodLabel }}
                </span>
            </div>

            <form
                class="sh-report-filters"
                @submit.prevent="generateReport"
            >
                <div class="filter-field">
                    <label for="report-date-from">
                        Date from
                    </label>

                    <input
                        id="report-date-from"
                        v-model="filters.date_from"
                        type="date"
                        :disabled="isLoading || isDownloading"
                    />
                </div>

                <div class="filter-field">
                    <label for="report-date-to">
                        Date to
                    </label>

                    <input
                        id="report-date-to"
                        v-model="filters.date_to"
                        type="date"
                        :disabled="isLoading || isDownloading"
                    />
                </div>

                <div class="filter-actions sh-report-filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="isLoading || isDownloading"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v4h16v-4"
                            />
                        </svg>

                        {{
                            isDownloading
                                ? 'Generating...'
                                : 'Generate report'
                        }}
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="isLoading || isDownloading"
                        @click="generateLatestReport"
                    >
                        Generate latest report
                    </button>
                </div>
            </form>

            <p class="sh-report-filter-note">
                Dates apply only to the downloaded PDF.
                The on-screen statistics stay unchanged.

                <span v-if="generatedAt">
                    Last updated {{ dateTime(generatedAt) }}.
                </span>
            </p>
        </section>

        <!-- ERROR MESSAGE -->

        <div
            v-if="errorMessage"
            class="error-message sh-report-error"
            role="alert"
        >
            <div>
                <strong>
                    {{
                        retryAction === 'overview'
                            ? 'Unable to load reports'
                            : 'Report action failed'
                    }}
                </strong>

                <p>{{ errorMessage }}</p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading || isDownloading"
                @click="retryFailure"
            >
                {{
                    retryAction === 'dismiss'
                        ? 'Dismiss'
                        : 'Try again'
                }}
            </button>
        </div>

        <template v-if="report">

            <!-- USERS AND SHOPS -->

            <section class="sh-report-group">
                <div class="sh-report-section-heading">
                    <div>
                        <h3>Users &amp; shops</h3>

                        <p>
                            Current account, shop approval
                            and availability totals.
                        </p>
                    </div>

                    <span class="sh-report-period-pill">
                        Current status
                    </span>
                </div>

                <div class="sh-report-two-columns">
                    <article class="sh-report-panel">
                        <div class="sh-report-panel-heading">
                            <h4>Shop approval</h4>
                        </div>

                        <div class="sh-report-metrics">
                            <div
                                v-for="[label, value] in approvalStats"
                                :key="label"
                                class="sh-report-metric"
                            >
                                <span>{{ label }}</span>

                                <strong>
                                    {{ number(value) }}
                                </strong>
                            </div>
                        </div>
                    </article>

                    <article class="sh-report-panel">
                        <div class="sh-report-panel-heading">
                            <h4>Shop availability</h4>
                        </div>

                        <div class="sh-report-metrics">
                            <div
                                v-for="[label, value] in availabilityStats"
                                :key="label"
                                class="sh-report-metric"
                            >
                                <span>{{ label }}</span>

                                <strong>
                                    {{ number(value) }}
                                </strong>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- APPOINTMENTS -->

            <section class="sh-report-group">
                <div class="sh-report-section-heading">
                    <div>
                        <h3>Appointments</h3>

                        <p>
                            Booking activity across all available records.
                        </p>
                    </div>

                    <span class="sh-report-period-pill">
                        {{ periodLabel }}
                    </span>
                </div>

                <div class="sh-report-stat-grid">
                    <article
                        v-for="[label, value] in appointmentStats"
                        :key="label"
                        class="sh-report-stat-card"
                    >
                        <span>{{ label }}</span>

                        <strong>
                            {{ number(value) }}
                        </strong>
                    </article>
                </div>
            </section>

            <!-- ATTENDANCE AND NO-SHOWS -->

            <section class="sh-report-group">
                <div class="sh-report-section-heading">
                    <div>
                        <h3>Attendance &amp; no-shows</h3>

                        <p>
                            Rates are based on completed and
                            no-show appointments.
                        </p>
                    </div>
                </div>

                <div
                    class="sh-report-stat-grid sh-report-performance-grid"
                >
                    <article
                        v-for="[label, value] in performanceStats"
                        :key="label"
                        class="sh-report-stat-card"
                    >
                        <span>{{ label }}</span>

                        <strong>{{ value }}</strong>
                    </article>
                </div>
            </section>

            <!-- TOP SHOPS AND SERVICES -->

            <section class="sh-report-group">
                <div class="sh-report-section-heading">
                    <div>
                        <h3>Top shops &amp; services</h3>

                        <p>
                            Ranked by booking volume across
                            all available records.
                        </p>
                    </div>
                </div>

                <div class="sh-report-ranking-stack">

                    <!-- TOP SHOPS -->

                    <article
                        class="sh-report-panel sh-report-table-panel"
                    >
                        <div class="sh-report-panel-heading">
                            <h4>Top shops</h4>

                            <span>
                                {{ topShops.length }} results
                            </span>
                        </div>

                        <p
                            v-if="!topShops.length"
                            class="sh-report-empty"
                        >
                            No shop bookings found.
                        </p>

                        <div
                            v-else
                            class="sh-report-table-scroll"
                        >
                            <table>
                                <thead>
                                    <tr>
                                        <th>Shop</th>
                                        <th>Bookings</th>
                                        <th>Completed</th>
                                        <th>No-show</th>
                                        <th>No-show rate</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(shop, index) in topShops"
                                        :key="shop.shop_id ?? index"
                                    >
                                        <td>
                                            <button
                                                type="button"
                                                class="sh-report-shop-link"
                                                :disabled="!shop.shop_id"
                                                @click="openShop(shop.shop_id)"
                                            >
                                                {{ index + 1 }}.
                                                {{ shop.shop_name }}
                                            </button>
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    shop.total_bookings
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    shop.completed_bookings
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    shop.no_show_count
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                percentage(
                                                    shop.no_show_rate
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                money(
                                                    shop.completed_revenue
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <!-- TOP SERVICES -->

                    <article
                        class="sh-report-panel sh-report-table-panel"
                    >
                        <div class="sh-report-panel-heading">
                            <h4>Top services</h4>

                            <span>
                                {{ topServices.length }} results
                            </span>
                        </div>

                        <p
                            v-if="!topServices.length"
                            class="sh-report-empty"
                        >
                            No service bookings found.
                        </p>

                        <div
                            v-else
                            class="sh-report-table-scroll"
                        >
                            <table>
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Bookings</th>
                                        <th>Completed</th>
                                        <th>No-show</th>
                                        <th>No-show rate</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(service, index) in topServices"
                                        :key="`${service.service_id}-${index}`"
                                    >
                                        <td>
                                            <strong class="sh-report-service-name">
                                                {{ index + 1 }}.
                                                {{ service.service_name }}
                                            </strong>

                                            <button
                                                type="button"
                                                class="sh-report-shop-link sh-report-shop-subtitle"
                                                :disabled="!service.shop_id"
                                                @click="openShop(service.shop_id)"
                                            >
                                                {{ service.shop_name }}
                                            </button>
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    service.total_bookings
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    service.completed_bookings
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                number(
                                                    service.no_show_count
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                percentage(
                                                    service.no_show_rate
                                                )
                                            }}
                                        </td>

                                        <td>
                                            {{
                                                money(
                                                    service.completed_revenue
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                </div>
            </section>

            <!-- FEEDBACK AND RESCHEDULING -->

            <section class="sh-report-group">
                <div class="sh-report-section-heading">
                    <div>
                        <h3>Feedback &amp; rescheduling</h3>

                        <p>
                            Customer feedback and reschedule
                            request activity.
                        </p>
                    </div>
                </div>

                <div class="sh-report-two-columns">

                    <!-- CUSTOMER FEEDBACK -->

                    <article class="sh-report-panel">
                        <div class="sh-report-panel-heading">
                            <h4>Customer feedback</h4>
                        </div>

                        <div class="sh-report-metrics">
                            <div class="sh-report-metric">
                                <span>Total reviews</span>

                                <strong>
                                    {{ number(feedback.total) }}
                                </strong>
                            </div>

                            <div class="sh-report-metric">
                                <span>Average rating</span>

                                <strong>
                                    {{
                                        feedback.average_rating == null
                                            ? 'N/A'
                                            : `${Number(
                                                  feedback.average_rating
                                              ).toFixed(2)} / 5`
                                    }}
                                </strong>
                            </div>
                        </div>

                        <div class="sh-report-rating-list">
                            <div
                                v-for="item in ratingRows"
                                :key="item.stars"
                                class="sh-report-rating-row"
                            >
                                <span>
                                    {{ item.stars }} ★
                                </span>

                                <div class="sh-report-rating-track">
                                    <div
                                        class="sh-report-rating-fill"
                                        :style="{
                                            width: item.width,
                                        }"
                                    ></div>
                                </div>

                                <strong>
                                    {{ number(item.count) }}
                                </strong>
                            </div>
                        </div>
                    </article>

                    <!-- RESCHEDULE REQUESTS -->

                    <article class="sh-report-panel">
                        <div class="sh-report-panel-heading">
                            <h4>Reschedule requests</h4>
                        </div>

                        <div class="sh-report-metrics">
                            <div
                                v-for="[label, value] in rescheduleStats"
                                :key="label"
                                class="sh-report-metric"
                            >
                                <span>{{ label }}</span>

                                <strong>
                                    {{ number(value) }}
                                </strong>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </template>

        <div
            v-else-if="!errorMessage"
            class="sh-report-empty sh-report-empty-page"
        >
            No report data available.
        </div>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

#app .reports-page {
    display: flex;
    flex-direction: column;
    gap: 24px;
    color: var(--sh-text);
    padding-bottom: 20px;
}

/*
|--------------------------------------------------------------------------
| PAGE INTRODUCTION
|--------------------------------------------------------------------------
*/

.sh-report-intro {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
}

.sh-report-intro-copy {
    min-width: 0;
    flex: 1;
}

.sh-report-intro h2 {
    margin: 0;
    color: var(--sh-text);
    font-size: clamp(27px, 2.5vw, 33px);
    font-weight: 750;
    line-height: 1.2;
    letter-spacing: -0.9px;
}

.sh-report-intro p {
    max-width: 690px;
    margin: 8px 0 0;
    color: var(--sh-text-3);
    font-size: 13px;
    line-height: 1.55;
}

.sh-report-intro-actions {
    display: flex;
    align-items: center;
    gap: 11px;
    flex: 0 0 auto;
}

/*
|--------------------------------------------------------------------------
| INTRODUCTION TOTAL CARD
|--------------------------------------------------------------------------
*/

.sh-report-total-card {
    min-width: 165px;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 9px 12px;

    border: 1px solid var(--sh-border);
    border-radius: 11px;
    background: var(--sh-surface);
}

.sh-report-total-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;

    display: grid;
    place-items: center;

    border-radius: 10px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sh-report-total-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sh-report-total-card > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sh-report-total-card > div span {
    color: var(--sh-text-3);
    font-size: 11px;
    font-weight: 650;
}

.sh-report-total-card strong {
    color: var(--sh-text);
    font-size: 19px;
    line-height: 1.15;
    font-weight: 800;
}

.sh-report-refresh {
    min-height: 43px;
    padding: 0 16px;
    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| CARDS
|--------------------------------------------------------------------------
*/

.sh-report-summary-card,
.sh-report-panel,
.sh-report-stat-card {
    background: var(--sh-surface);
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-lg, 16px);
    box-shadow: var(--sh-shadow-sm);
}

/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

.sh-report-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.sh-report-summary-card {
    min-width: 0;
    min-height: 144px;

    display: flex;
    flex-direction: row-reverse;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;

    padding: 20px 18px;
}

.sh-report-summary-icon {
    flex: 0 0 42px;
    width: 42px;
    height: 42px;

    display: grid;
    place-items: center;
    border-radius: 11px;

    color: var(--sh-info);
    background: var(--sh-info-bg);
}

.sh-report-summary-icon.icon-owners {
    color: var(--sh-purple);
    background: var(--sh-purple-bg);
}

.sh-report-summary-icon.icon-shops {
    color: var(--sh-success);
    background: var(--sh-success-bg);
}

.sh-report-summary-icon.icon-customers {
    color: var(--sh-accent);
    background: var(--sh-accent-soft);
}

.sh-report-summary-icon svg,
.sh-report-filter-icon svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sh-report-summary-copy {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
}

.sh-report-summary-copy span {
    color: var(--sh-text-2);
    font-size: 12px;
    font-weight: 650;
    line-height: 1.5;
}

.sh-report-summary-copy strong {
    margin-top: 13px;
    color: var(--sh-text);
    font-size: clamp(26px, 2.5vw, 31px);
    font-weight: 780;
    line-height: 1.1;
    letter-spacing: -0.8px;
}

.sh-report-summary-copy small {
    margin-top: 8px;
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 1.4;
}

/*
|--------------------------------------------------------------------------
| GENERATE REPORT FILTER
|--------------------------------------------------------------------------
*/

.sh-report-filter-card {
    margin: 0;
    padding: 0;
    overflow: hidden;

    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-lg);
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.sh-report-filter-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;

    min-height: 77px;
    padding: 18px 21px;
    border-bottom: 1px solid var(--sh-border);
}

.sh-report-filter-heading-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.sh-report-filter-icon {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;

    display: grid;
    place-items: center;

    color: var(--sh-text-2);
    background: var(--sh-surface-muted);
    border-radius: 11px;
}

.sh-report-filter-heading h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 16px;
    line-height: 1.3;
    font-weight: 750;
}

.sh-report-filter-heading p {
    margin: 4px 0 0;
    color: var(--sh-text-3);
    font-size: 12px;
    line-height: 1.45;
}

.sh-report-heading-note,
.sh-report-period-pill {
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;

    border: 1px solid var(--sh-border);
    border-radius: 999px;
    padding: 6px 10px;

    background: var(--sh-surface-muted);
    color: var(--sh-text-2);
    font-size: 10px;
    font-weight: 750;
}

.sh-report-filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px 15px;
    align-items: end;
    padding: 21px;
}

.sh-report-filters .filter-field {
    min-width: 0;
}

.sh-report-filters .filter-field label {
    display: block;
    margin-bottom: 8px;
    color: var(--sh-text-2);
    font-size: 12px;
    font-weight: 700;
}

.sh-report-filters input {
    width: 100%;
    min-width: 0;
    min-height: 43px;

    border: 1px solid var(--sh-border-strong);
    border-radius: 10px;

    background: var(--sh-surface);
    color: var(--sh-text);
    font-size: 13px;
}

.sh-report-filters input:focus {
    border-color: var(--sh-accent);
    outline: 2px solid var(--sh-accent-soft);
    outline-offset: 0;
}

.sh-report-filter-actions {
    display: flex;
    grid-column: 1 / -1;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
}

.sh-report-filter-actions button {
    min-height: 43px;
    padding: 0 17px;
    font-size: 12px;
    white-space: nowrap;
}

.sh-report-filter-note {
    margin: 0;
    padding: 0 21px 21px;
    color: var(--sh-text-3);
    font-size: 12px;
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| ERROR MESSAGE
|--------------------------------------------------------------------------
*/

.sh-report-error {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;

    margin: 0;
    padding: 14px 16px;

    border: 1px solid var(--sh-danger-bg);
    border-radius: 12px;
    background: #fff6f6;
}

.sh-report-error strong {
    color: #983b3b;
    font-size: 12px;
}

.sh-report-error p {
    margin: 4px 0 0;
    color: #9b4444;
    font-size: 11px;
}

/*
|--------------------------------------------------------------------------
| REPORT SECTIONS
|--------------------------------------------------------------------------
*/

.sh-report-group {
    display: flex;
    flex-direction: column;
    gap: 17px;
}

.sh-report-section-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 20px;
    padding: 5px 0 0;
}

.sh-report-section-heading h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 20px;
    font-weight: 750;
    letter-spacing: -0.45px;
}

.sh-report-section-heading p {
    margin: 6px 0 0;
    color: var(--sh-text-3);
    font-size: 12px;
    line-height: 1.55;
}

/*
|--------------------------------------------------------------------------
| DETAIL PANELS
|--------------------------------------------------------------------------
*/

.sh-report-two-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.sh-report-panel {
    min-width: 0;
    overflow: hidden;
}

.sh-report-panel-heading {
    min-height: 77px;
    padding: 18px 21px;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;

    border-bottom: 1px solid var(--sh-border);
}

.sh-report-panel-heading h4 {
    margin: 0;
    color: var(--sh-text);
    font-size: 16px;
    font-weight: 750;
}

.sh-report-panel-heading span {
    color: var(--sh-text-3);
    font-size: 11px;
}

.sh-report-metrics {
    padding: 8px 21px 16px;
}

.sh-report-metric {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    padding: 13px 0;
    border-bottom: 1px solid var(--sh-border);
    font-size: 12px;
}

.sh-report-metric:last-child {
    border-bottom: 0;
}

.sh-report-metric span {
    color: var(--sh-text-2);
}

.sh-report-metric strong {
    color: var(--sh-text);
    font-size: 15px;
    font-weight: 750;
}

/*
|--------------------------------------------------------------------------
| STATISTIC CARDS
|--------------------------------------------------------------------------
*/

.sh-report-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.sh-report-stat-card {
    min-width: 0;
    min-height: 130px;
    padding: 20px 18px;

    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 12px;
}

.sh-report-stat-card span {
    color: var(--sh-text-2);
    font-size: 12px;
    font-weight: 650;
}

.sh-report-stat-card strong {
    color: var(--sh-text);
    font-size: 29px;
    font-weight: 780;
    line-height: 1.15;
}

.sh-report-performance-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
}

/*
|--------------------------------------------------------------------------
| RANKINGS AND TABLES
|--------------------------------------------------------------------------
*/

.sh-report-ranking-stack {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sh-report-table-scroll {
    width: 100%;
    overflow-x: auto;
}

.sh-report-table-scroll table {
    width: 100%;
    min-width: 920px;
    border-collapse: collapse;
    font-size: 12px;
}

.sh-report-table-scroll th {
    padding: 15px 17px;
    text-align: left;

    font-size: 11px;
    font-weight: 750;

    color: var(--sh-text-3);
    background: var(--sh-surface-muted);
    white-space: nowrap;
}

.sh-report-table-scroll td {
    padding: 17px;
    border-top: 1px solid var(--sh-border);

    color: var(--sh-text-2);
    vertical-align: middle;
    white-space: nowrap;
}

.sh-report-table-scroll tbody tr:hover {
    background: var(--sh-surface-muted);
}

.sh-report-shop-link {
    border: 0;
    background: none;
    padding: 0;

    color: var(--sh-info);
    text-align: left;

    font: inherit;
    font-weight: 750;
    cursor: pointer;
}

.sh-report-shop-link:hover:not(:disabled) {
    text-decoration: underline;
}

.sh-report-shop-link:disabled {
    cursor: default;
    opacity: 0.7;
}

.sh-report-service-name {
    display: block;
    color: var(--sh-text);
    font-size: 12px;
}

.sh-report-shop-subtitle {
    display: block;
    margin-top: 4px;
    font-size: 11px;
}

.sh-report-empty {
    margin: 0;
    padding: 35px 20px;
    font-size: 12px;
    text-align: center;
    color: var(--sh-text-3);
}

.sh-report-empty-page {
    border-radius: 13px;
    background: var(--sh-surface);
    border: 1px solid var(--sh-border);
}

/*
|--------------------------------------------------------------------------
| FEEDBACK RATINGS
|--------------------------------------------------------------------------
*/

.sh-report-rating-list {
    display: flex;
    flex-direction: column;
    gap: 13px;
    padding: 0 21px 21px;
}

.sh-report-rating-row {
    display: grid;
    grid-template-columns: 36px minmax(0, 1fr) 34px;
    gap: 11px;
    align-items: center;
}

.sh-report-rating-row span,
.sh-report-rating-row strong {
    font-size: 12px;
    color: var(--sh-text-2);
}

.sh-report-rating-row strong {
    text-align: right;
}

.sh-report-rating-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--sh-surface-muted);
}

.sh-report-rating-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--sh-accent);
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE DESIGN
|--------------------------------------------------------------------------
*/

@media (max-width: 1350px) {
    .sh-report-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-report-filter-actions {
        grid-column: 1 / -1;
    }

    .sh-report-performance-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 1020px) {
    .sh-report-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-report-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .sh-report-intro-actions {
        width: 100%;
        flex-wrap: wrap;
    }
}

@media (max-width: 690px) {
    .sh-report-two-columns,
    .sh-report-stat-grid,
    .sh-report-performance-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-report-section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .sh-report-period-pill {
        max-width: 100%;
        white-space: normal;
    }
}

@media (max-width: 480px) {
    .sh-report-intro-actions,
    .sh-report-total-card,
    .sh-report-refresh {
        width: 100%;
    }

    .sh-report-summary-grid,
    .sh-report-two-columns,
    .sh-report-filters,
    .sh-report-stat-grid,
    .sh-report-performance-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .sh-report-filter-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .sh-report-filter-actions button {
        width: 100%;
    }

    .sh-report-heading-note {
        display: none;
    }

    .sh-report-error {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
