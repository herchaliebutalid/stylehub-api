<script setup>
import {
    computed,
    onMounted,
    reactive,
    ref,
} from 'vue';

import { useRouter } from 'vue-router';

import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const report = ref(null);
const generatedAt = ref(null);

const isLoading = ref(false);
const errorMessage = ref('');

const filters = reactive({
    date_from: '',
    date_to: '',
});


/*
|--------------------------------------------------------------------------
| REPORT DATA
|--------------------------------------------------------------------------
*/

const period = computed(
    () =>
        report.value?.period
        ?? {
            date_from: null,
            date_to: null,
            is_all_time: true,
        },
);

const customers = computed(
    () =>
        report.value
            ?.current_state
            ?.users
            ?.customers
        ?? {
            total: 0,
            active: 0,
            suspended: 0,
        },
);

const shopOwners = computed(
    () =>
        report.value
            ?.current_state
            ?.users
            ?.shop_owners
        ?? {
            total: 0,
            active: 0,
            suspended: 0,
        },
);

const shops = computed(
    () =>
        report.value
            ?.current_state
            ?.shops
        ?? {
            total: 0,

            approval: {
                pending: 0,
                approved: 0,
                rejected: 0,
            },

            availability: {
                open: 0,
                closed: 0,
                fully_booked: 0,
                accepting_walk_ins: 0,
            },
        },
);

const appointments = computed(
    () =>
        report.value
            ?.activity
            ?.appointments
        ?? {
            total: 0,
            pending: 0,
            confirmed: 0,
            completed: 0,
            no_show: 0,
            cancelled: 0,
            declined: 0,
            unique_customers: 0,
            unique_shops: 0,
        },
);

const feedback = computed(
    () =>
        report.value
            ?.activity
            ?.feedback
        ?? {
            total: 0,
            average_rating: null,

            rating_distribution: {
                1: 0,
                2: 0,
                3: 0,
                4: 0,
                5: 0,
            },
        },
);

const rescheduleRequests = computed(
    () =>
        report.value
            ?.activity
            ?.reschedule_requests
        ?? {
            total: 0,
            pending: 0,
            approved: 0,
            rejected: 0,
            withdrawn: 0,
        },
);

const noShowAnalytics = computed(
    () =>
        report.value
            ?.analytics
            ?.no_show
        ?? {
            total_bookings: 0,
            attendance_outcomes: 0,
            completed: 0,
            no_show: 0,
            completion_rate: null,
            no_show_rate: null,
        },
);

const topShops = computed(
    () =>
        Array.isArray(
            report.value
                ?.analytics
                ?.top_shops,
        )
            ? report.value.analytics.top_shops
            : [],
);

const topServices = computed(
    () =>
        Array.isArray(
            report.value
                ?.analytics
                ?.top_services,
        )
            ? report.value.analytics.top_services
            : [],
);


/*
|--------------------------------------------------------------------------
| FRONTEND SUMMARIES
|--------------------------------------------------------------------------
*/

const totalManagedAccounts = computed(
    () =>
        Number(
            customers.value.total
            ?? 0,
        )
        +
        Number(
            shopOwners.value.total
            ?? 0,
        ),
);

const totalActiveAccounts = computed(
    () =>
        Number(
            customers.value.active
            ?? 0,
        )
        +
        Number(
            shopOwners.value.active
            ?? 0,
        ),
);

const totalSuspendedAccounts = computed(
    () =>
        Number(
            customers.value.suspended
            ?? 0,
        )
        +
        Number(
            shopOwners.value.suspended
            ?? 0,
        ),
);


/*
|--------------------------------------------------------------------------
| RATINGS
|--------------------------------------------------------------------------
*/

const ratingRows = computed(() => {
    const distribution =
        feedback.value
            ?.rating_distribution
        ?? {};

    return [
        5,
        4,
        3,
        2,
        1,
    ].map(
        (rating) => ({
            rating,

            count:
                Number(
                    distribution[rating]
                    ??
                    distribution[
                        String(rating)
                    ]
                    ??
                    0,
                ),
        }),
    );
});

const maximumRatingCount = computed(
    () =>
        Math.max(
            0,

            ...ratingRows.value.map(
                (row) =>
                    row.count,
            ),
        ),
);


/*
|--------------------------------------------------------------------------
| FORMATTERS
|--------------------------------------------------------------------------
*/

function formatNumber(value) {
    const number =
        Number(value);

    return Number.isFinite(number)
        ? new Intl.NumberFormat(
            'en-PH',
        ).format(number)
        : '0';
}

function formatDecimal(value) {
    if (
        value === null
        ||
        value === undefined
    ) {
        return '—';
    }

    const number =
        Number(value);

    if (
        !Number.isFinite(number)
    ) {
        return '—';
    }

    return number
        .toFixed(2)
        .replace(
            /\.00$/,
            '',
        );
}

function formatPercent(value) {
    const formatted =
        formatDecimal(value);

    return formatted === '—'
        ? '—'
        : `${formatted}%`;
}

function formatCurrency(value) {
    const number =
        Number(value);

    if (
        !Number.isFinite(number)
    ) {
        return '₱0.00';
    }

    return new Intl.NumberFormat(
        'en-PH',
        {
            style: 'currency',
            currency: 'PHP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    ).format(number);
}


/*
|--------------------------------------------------------------------------
| DATE HELPERS
|--------------------------------------------------------------------------
*/

function parseDateOnly(value) {
    if (!value) {
        return null;
    }

    const parts =
        String(value)
            .split('-')
            .map(Number);

    if (
        parts.length !== 3
        ||
        parts.some(
            (part) =>
                !Number.isInteger(
                    part,
                ),
        )
    ) {
        return null;
    }

    return new Date(
        Date.UTC(
            parts[0],
            parts[1] - 1,
            parts[2],
        ),
    );
}

function formatDate(value) {
    const date =
        parseDateOnly(value);

    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-PH',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: 'UTC',
        },
    ).format(date);
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date =
        new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-PH',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZone: 'Asia/Manila',
        },
    ).format(date);
}


/*
|--------------------------------------------------------------------------
| RATING WIDTH
|--------------------------------------------------------------------------
*/

function ratingWidth(count) {
    if (
        maximumRatingCount.value
        <= 0
    ) {
        return '0%';
    }

    return `${
        Math.max(
            0,

            Math.min(
                100,

                (
                    Number(count)
                    /
                    maximumRatingCount.value
                )
                * 100,
            ),
        )
    }%`;
}


/*
|--------------------------------------------------------------------------
| PERIOD
|--------------------------------------------------------------------------
*/

const periodLabel = computed(() => {
    if (
        period.value
            ?.is_all_time
    ) {
        return 'All Time';
    }

    const from =
        period.value
            ?.date_from;

    const to =
        period.value
            ?.date_to;

    if (
        from
        &&
        to
    ) {
        return `${
            formatDate(from)
        } – ${
            formatDate(to)
        }`;
    }

    if (from) {
        return `From ${formatDate(from)}`;
    }

    if (to) {
        return `Through ${formatDate(to)}`;
    }

    return 'All Time';
});


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

function differenceInDays(
    dateFrom,
    dateTo,
) {
    const from =
        parseDateOnly(
            dateFrom,
        );

    const to =
        parseDateOnly(
            dateTo,
        );

    if (
        !from
        ||
        !to
    ) {
        return null;
    }

    return Math.floor(
        (
            to.getTime()
            -
            from.getTime()
        )
        /
        86400000,
    );
}

function validateFilters() {
    errorMessage.value =
        '';

    if (
        !filters.date_from
        ||
        !filters.date_to
    ) {
        return true;
    }

    const difference =
        differenceInDays(
            filters.date_from,
            filters.date_to,
        );

    if (
        difference !== null
        &&
        difference < 0
    ) {
        errorMessage.value =
            'The end date must be on or after the start date.';

        return false;
    }

    if (
        difference !== null
        &&
        difference > 366
    ) {
        errorMessage.value =
            'The reporting period may not exceed 366 days.';

        return false;
    }

    return true;
}


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

function buildQuery() {
    const query =
        new URLSearchParams();

    if (
        filters.date_from
    ) {
        query.set(
            'date_from',
            filters.date_from,
        );
    }

    if (
        filters.date_to
    ) {
        query.set(
            'date_to',
            filters.date_to,
        );
    }

    const value =
        query.toString();

    return value
        ? `?${value}`
        : '';
}


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name:
            'admin.login',
    });
}


/*
|--------------------------------------------------------------------------
| LOAD REPORT
|--------------------------------------------------------------------------
*/

async function loadReport() {
    if (
        !auth.token
    ) {
        await authenticationFailure();

        return;
    }

    if (
        !validateFilters()
    ) {
        return;
    }

    isLoading.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/reports/overview${buildQuery()}`,
                {
                    token:
                        auth.token,
                },
            );

        if (
            !response
            ||
            typeof response.data
                !== 'object'
            ||
            response.data === null
        ) {
            throw new Error(
                'The report response was invalid.',
            );
        }

        report.value =
            response.data;

        generatedAt.value =
            response.generated_at
            ?? null;
    } catch (
        error
    ) {
        if (
            error?.status === 401
            ||
            error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status === 422
        ) {
            errorMessage.value =
                error
                    ?.data
                    ?.errors
                    ?.date_to
                    ?.[0]
                ??
                error
                    ?.data
                    ?.errors
                    ?.date_from
                    ?.[0]
                ??
                error
                    ?.data
                    ?.message
                ??
                'The report date range is invalid.';

            return;
        }

        errorMessage.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to load the report.';
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER ACTIONS
|--------------------------------------------------------------------------
*/

async function resetFilters() {
    filters.date_from =
        '';

    filters.date_to =
        '';

    await loadReport();
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function openShop(shopId) {
    if (!shopId) {
        return;
    }

    await router.push({
        name:
            'admin.shop.detail',

        params: {
            shopId,
        },
    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadReport();
});
</script>


<template>
    <div class="reports-page">
        <!-- ============================================================
             REPORT INTRODUCTION
             ============================================================ -->

        <section class="reports-intro">
            <div class="reports-intro-copy">
                <div class="reports-label">
                    <span class="reports-label-dot" />

                    Analytics & reporting
                </div>

                <h2>
                    Platform performance overview
                </h2>

                <p>
                    Review account activity, shop operations,
                    booking outcomes, customer feedback,
                    attendance performance and top-performing
                    shops and services.
                </p>
            </div>


            <div class="reports-intro-right">
                <div class="report-period-display">
                    <div class="report-period-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="3"
                                y="5"
                                width="18"
                                height="16"
                                rx="2"
                            />

                            <path
                                d="M8 3v4M16 3v4M3 10h18"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Reporting period
                        </span>

                        <strong>
                            {{ periodLabel }}
                        </strong>
                    </div>
                </div>

                <div
                    v-if="generatedAt"
                    class="generated-information"
                >
                    <span>
                        Last generated
                    </span>

                    <strong>
                        {{
                            formatDateTime(
                                generatedAt
                            )
                        }}
                    </strong>
                </div>
            </div>
        </section>


        <!-- ============================================================
             PERIOD FILTER
             ============================================================ -->

        <section class="period-card reports-period-card">
            <div class="period-card-heading">
                <div class="period-heading-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M4 6h16M7 12h10M10 18h4"
                        />
                    </svg>
                </div>

                <div>
                    <p class="eyebrow">
                        ACTIVITY PERIOD
                    </p>

                    <h3>
                        Generate report
                    </h3>

                    <p>
                        Filter activity-based analytics by
                        appointment date.
                    </p>
                </div>
            </div>


            <form
                class="period-filters reports-period-filters"
                @submit.prevent="
                    loadReport
                "
            >
                <label class="period-field">
                    <span>
                        Date from
                    </span>

                    <input
                        v-model="
                            filters.date_from
                        "
                        type="date"
                        :disabled="
                            isLoading
                        "
                    >
                </label>


                <label class="period-field">
                    <span>
                        Date to
                    </span>

                    <input
                        v-model="
                            filters.date_to
                        "
                        type="date"
                        :disabled="
                            isLoading
                        "
                    >
                </label>


                <button
                    type="submit"
                    class="primary-button generate-button"
                    :disabled="
                        isLoading
                    "
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        :class="{
                            spinning:
                                isLoading,
                        }"
                    >
                        <path
                            d="M4 19V9M10 19V5M16 19v-7M22 19V3"
                        />
                    </svg>

                    <span>
                        {{
                            isLoading
                                ? 'Generating...'
                                : 'Generate report'
                        }}
                    </span>
                </button>


                <button
                    type="button"
                    class="secondary-button"
                    :disabled="
                        isLoading
                    "
                    @click="
                        resetFilters
                    "
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M4 4v6h6"
                        />

                        <path
                            d="M5.5 15a8 8 0 102-8"
                        />
                    </svg>

                    <span>
                        All time
                    </span>
                </button>
            </form>
        </section>


        <!-- ============================================================
             REPORTING NOTE
             ============================================================ -->

        <section class="report-scope-note">
            <div class="scope-note-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path
                        d="M12 11v5M12 8h.01"
                    />
                </svg>
            </div>

            <div>
                <strong>
                    Report scope
                </strong>

                <p>
                    Activity filters use appointment dates for
                    booking and performance analytics. Current
                    user and shop state always represents the
                    present StyleHub system state.
                </p>
            </div>
        </section>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                errorMessage
            "
            class="error-message reports-error"
            role="alert"
        >
            <div class="reports-error-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path
                        d="M12 8v5"
                    />

                    <path
                        d="M12 17h.01"
                    />
                </svg>
            </div>

            <div class="reports-error-copy">
                <strong>
                    Unable to generate report
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="
                    isLoading
                "
                @click="
                    loadReport
                "
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             LOADING
             ============================================================ -->

        <section
            v-if="
                isLoading
                && !report
            "
            class="loading-card reports-loading-card"
        >
            <div class="reports-loading-icon">
                <div class="spinner" />
            </div>

            <h3>
                Generating report
            </h3>

            <p>
                Retrieving and aggregating StyleHub
                platform statistics.
            </p>
        </section>


        <!-- ============================================================
             REPORT
             ============================================================ -->

        <template v-else-if="report">
            <!-- ========================================================
                 CURRENT PLATFORM STATE
                 ======================================================== -->

            <section class="report-section-heading">
                <div>
                    <p class="eyebrow">
                        CURRENT PLATFORM STATE
                    </p>

                    <h3>
                        Users & shops
                    </h3>

                    <p>
                        Live platform state. These values are not
                        restricted by the selected reporting period.
                    </p>
                </div>

                <span class="section-period-badge">
                    Current
                </span>
            </section>


            <section class="platform-state-grid">
                <!-- ALL ACCOUNTS -->

                <article class="platform-state-card">
                    <div class="state-card-icon accounts">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                            />

                            <path
                                d="M3 20c0-4 2-7 6-7s6 3 6 7"
                            />

                            <circle
                                cx="17"
                                cy="9"
                                r="2.5"
                            />

                            <path
                                d="M15 14c4 0 6 2 6 6"
                            />
                        </svg>
                    </div>

                    <div class="state-card-copy">
                        <span>
                            Managed accounts
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    totalManagedAccounts
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                formatNumber(
                                    totalActiveAccounts
                                )
                            }}
                            active ·
                            {{
                                formatNumber(
                                    totalSuspendedAccounts
                                )
                            }}
                            suspended
                        </small>
                    </div>
                </article>


                <!-- CUSTOMERS -->

                <article class="platform-state-card">
                    <div class="state-card-icon customers">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3.5"
                            />

                            <path
                                d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                            />
                        </svg>
                    </div>

                    <div class="state-card-copy">
                        <span>
                            Customers
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    customers.total
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                formatNumber(
                                    customers.active
                                )
                            }}
                            active ·
                            {{
                                formatNumber(
                                    customers.suspended
                                )
                            }}
                            suspended
                        </small>
                    </div>
                </article>


                <!-- OWNERS -->

                <article class="platform-state-card">
                    <div class="state-card-icon owners">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3.5"
                            />

                            <path
                                d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                            />

                            <path
                                d="M18 4v4M16 6h4"
                            />
                        </svg>
                    </div>

                    <div class="state-card-copy">
                        <span>
                            Shop Owners
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shopOwners.total
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                formatNumber(
                                    shopOwners.active
                                )
                            }}
                            active ·
                            {{
                                formatNumber(
                                    shopOwners.suspended
                                )
                            }}
                            suspended
                        </small>
                    </div>
                </article>


                <!-- SHOPS -->

                <article class="platform-state-card">
                    <div class="state-card-icon shops">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 10v10h16V10"
                            />

                            <path
                                d="M3 10l2-6h14l2 6"
                            />

                            <path
                                d="M8 20v-6h8v6"
                            />
                        </svg>
                    </div>

                    <div class="state-card-copy">
                        <span>
                            Registered shops
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shops.total
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.approved
                                )
                            }}
                            approved ·
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.pending
                                )
                            }}
                            pending
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 SHOP OPERATIONS
                 ======================================================== -->

            <section class="detail-card report-card">
                <div class="report-card-heading">
                    <div class="report-heading-main">
                        <div class="report-heading-icon shop-operation">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 10v10h16V10"
                                />

                                <path
                                    d="M3 10l2-6h14l2 6"
                                />

                                <path
                                    d="M8 20v-6h8v6"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="eyebrow">
                                SHOP OPERATIONS
                            </p>

                            <h3>
                                Current availability
                            </h3>

                            <p>
                                Present operational state of
                                registered shops.
                            </p>
                        </div>
                    </div>

                    <div class="shop-approval-overview">
                        <span>
                            Approved shops
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.approved
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <div class="availability-grid">
                    <div class="availability-card open">
                        <div class="availability-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />

                                <path
                                    d="M8 12l3 3 5-6"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Open
                            </span>

                            <strong>
                                {{
                                    formatNumber(
                                        shops.availability
                                            ?.open
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <div class="availability-card closed">
                        <div class="availability-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />

                                <path
                                    d="M9 9l6 6M15 9l-6 6"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Closed
                            </span>

                            <strong>
                                {{
                                    formatNumber(
                                        shops.availability
                                            ?.closed
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <div class="availability-card booked">
                        <div class="availability-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="16"
                                    rx="2"
                                />

                                <path
                                    d="M8 3v4M16 3v4M3 10h18"
                                />

                                <path
                                    d="M8 15h8"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Fully booked
                            </span>

                            <strong>
                                {{
                                    formatNumber(
                                        shops.availability
                                            ?.fully_booked
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <div class="availability-card walkins">
                        <div class="availability-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 3v18M5 10l7-7 7 7"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Accepting walk-ins
                            </span>

                            <strong>
                                {{
                                    formatNumber(
                                        shops.availability
                                            ?.accepting_walk_ins
                                    )
                                }}
                            </strong>
                        </div>
                    </div>
                </div>


                <div class="shop-approval-strip">
                    <div>
                        <span class="approval-dot approved" />

                        <span>
                            Approved
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.approved
                                )
                            }}
                        </strong>
                    </div>

                    <div>
                        <span class="approval-dot pending" />

                        <span>
                            Pending
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.pending
                                )
                            }}
                        </strong>
                    </div>

                    <div>
                        <span class="approval-dot rejected" />

                        <span>
                            Rejected
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    shops.approval
                                        ?.rejected
                                )
                            }}
                        </strong>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 ACTIVITY SECTION
                 ======================================================== -->

            <section class="report-section-heading">
                <div>
                    <p class="eyebrow">
                        ACTIVITY
                    </p>

                    <h3>
                        Booking activity
                    </h3>

                    <p>
                        Appointment records included in
                        <strong>
                            {{ periodLabel }}
                        </strong>.
                    </p>
                </div>

                <span class="section-period-badge">
                    {{ periodLabel }}
                </span>
            </section>


            <!-- ========================================================
                 APPOINTMENT KPI CARDS
                 ======================================================== -->

            <section class="booking-summary-grid">
                <article class="booking-total-card">
                    <div class="booking-total-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="3"
                                y="5"
                                width="18"
                                height="16"
                                rx="2"
                            />

                            <path
                                d="M8 3v4M16 3v4M3 10h18"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Total appointments
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.total
                                )
                            }}
                        </strong>

                        <small>
                            During selected period
                        </small>
                    </div>
                </article>


                <article class="booking-total-card">
                    <div class="booking-total-icon customers">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3.5"
                            />

                            <path
                                d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Unique customers
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments
                                        .unique_customers
                                )
                            }}
                        </strong>

                        <small>
                            Customers with bookings
                        </small>
                    </div>
                </article>


                <article class="booking-total-card">
                    <div class="booking-total-icon shops">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 10v10h16V10"
                            />

                            <path
                                d="M3 10l2-6h14l2 6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Unique shops
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments
                                        .unique_shops
                                )
                            }}
                        </strong>

                        <small>
                            Shops receiving bookings
                        </small>
                    </div>
                </article>
            </section>


            <section class="detail-card report-card">
                <div class="report-card-heading">
                    <div class="report-heading-main">
                        <div class="report-heading-icon appointments">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="16"
                                    rx="2"
                                />

                                <path
                                    d="M8 3v4M16 3v4M3 10h18"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="eyebrow">
                                APPOINTMENTS
                            </p>

                            <h3>
                                Appointment outcomes
                            </h3>

                            <p>
                                Booking status distribution for
                                the selected period.
                            </p>
                        </div>
                    </div>

                    <div class="large-summary">
                        <span>
                            Total
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.total
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <div class="appointment-outcome-grid">
                    <div class="outcome-card pending">
                        <span>
                            Pending
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.pending
                                )
                            }}
                        </strong>
                    </div>

                    <div class="outcome-card confirmed">
                        <span>
                            Confirmed
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.confirmed
                                )
                            }}
                        </strong>
                    </div>

                    <div class="outcome-card completed">
                        <span>
                            Completed
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.completed
                                )
                            }}
                        </strong>
                    </div>

                    <div class="outcome-card no-show">
                        <span>
                            No-show
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.no_show
                                )
                            }}
                        </strong>
                    </div>

                    <div class="outcome-card cancelled">
                        <span>
                            Cancelled
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.cancelled
                                )
                            }}
                        </strong>
                    </div>

                    <div class="outcome-card declined">
                        <span>
                            Declined
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    appointments.declined
                                )
                            }}
                        </strong>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 PERFORMANCE
                 ======================================================== -->

            <section class="report-section-heading">
                <div>
                    <p class="eyebrow">
                        PERFORMANCE ANALYTICS
                    </p>

                    <h3>
                        Attendance & performance
                    </h3>

                    <p>
                        No-show analytics and rankings are calculated
                        from bookings in
                        <strong>
                            {{ periodLabel }}
                        </strong>.
                        Completed Revenue includes completed
                        appointments only.
                    </p>
                </div>
            </section>


            <!-- ========================================================
                 NO SHOW
                 ======================================================== -->

            <section class="performance-grid">
                <article class="performance-card highlight">
                    <div class="performance-icon no-show">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M9 9l6 6M15 9l-6 6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            No-show rate
                        </span>

                        <strong>
                            {{
                                formatPercent(
                                    noShowAnalytics
                                        .no_show_rate
                                )
                            }}
                        </strong>

                        <small>
                            No-shows ÷ attendance outcomes
                        </small>
                    </div>
                </article>


                <article class="performance-card">
                    <div class="performance-icon outcomes">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M5 12l4 4L19 6"
                            />

                            <path
                                d="M4 20h16"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Attendance outcomes
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    noShowAnalytics
                                        .attendance_outcomes
                                )
                            }}
                        </strong>

                        <small>
                            Completed + no-show
                        </small>
                    </div>
                </article>


                <article class="performance-card">
                    <div class="performance-icon complete">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M8 12l3 3 5-6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Completed
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    noShowAnalytics
                                        .completed
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                formatPercent(
                                    noShowAnalytics
                                        .completion_rate
                                )
                            }}
                            completion rate
                        </small>
                    </div>
                </article>


                <article class="performance-card">
                    <div class="performance-icon failure">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l9 17H3L12 3z"
                            />

                            <path
                                d="M12 9v4M12 16h.01"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            No-shows
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    noShowAnalytics
                                        .no_show
                                )
                            }}
                        </strong>

                        <small>
                            Attendance failures
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 TOP SHOPS
                 ======================================================== -->

            <section class="ranking-grid reports-ranking-grid">
                <article class="detail-card ranking-card">
                    <div class="ranking-heading">
                        <div class="report-heading-main">
                            <div class="report-heading-icon ranking">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M8 21h8M12 17v4"
                                    />

                                    <path
                                        d="M6 3h12v4c0 4-2.7 7-6 7s-6-3-6-7V3z"
                                    />

                                    <path
                                        d="M6 5H3v2c0 2 1.3 4 4 4"
                                    />

                                    <path
                                        d="M18 5h3v2c0 2-1.3 4-4 4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    TOP 5 SHOPS
                                </p>

                                <h3>
                                    Shops by booking volume
                                </h3>

                                <p>
                                    Highest booking activity during
                                    the selected period.
                                </p>
                            </div>
                        </div>
                    </div>


                    <div
                        v-if="
                            topShops.length === 0
                        "
                        class="empty-state report-empty-state"
                    >
                        <div class="empty-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 10v10h16V10"
                                />

                                <path
                                    d="M3 10l2-6h14l2 6"
                                />
                            </svg>
                        </div>

                        <h3>
                            No shop rankings
                        </h3>

                        <p>
                            No shop bookings exist for this
                            reporting period.
                        </p>
                    </div>


                    <div
                        v-else
                        class="table-wrap ranking-table-wrap"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        Rank
                                    </th>

                                    <th>
                                        Shop
                                    </th>

                                    <th>
                                        Bookings
                                    </th>

                                    <th>
                                        Completed
                                    </th>

                                    <th>
                                        No-show
                                    </th>

                                    <th>
                                        No-show Rate
                                    </th>

                                    <th>
                                        Completed Revenue
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="
                                        (shop, index)
                                        in topShops
                                    "
                                    :key="
                                        shop.shop_id
                                    "
                                >
                                    <td>
                                        <span
                                            class="rank"
                                            :class="{
                                                top:
                                                    index === 0,
                                            }"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="entity-link"
                                            @click="
                                                openShop(
                                                    shop.shop_id
                                                )
                                            "
                                        >
                                            <span class="shop-rank-icon">
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M4 10v10h16V10"
                                                    />

                                                    <path
                                                        d="M3 10l2-6h14l2 6"
                                                    />
                                                </svg>
                                            </span>

                                            <span>
                                                {{
                                                    shop.shop_name
                                                }}
                                            </span>
                                        </button>
                                    </td>

                                    <td>
                                        {{
                                            formatNumber(
                                                shop.total_bookings
                                            )
                                        }}
                                    </td>

                                    <td>
                                        <span class="positive-value">
                                            {{
                                                formatNumber(
                                                    shop.completed_bookings
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        {{
                                            formatNumber(
                                                shop.no_show_count
                                            )
                                        }}
                                    </td>

                                    <td>
                                        <span
                                            class="rate-value"
                                            :class="{
                                                warning:
                                                    Number(
                                                        shop.no_show_rate
                                                        ?? 0
                                                    ) > 0,
                                            }"
                                        >
                                            {{
                                                formatPercent(
                                                    shop.no_show_rate
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <td class="revenue">
                                        {{
                                            formatCurrency(
                                                shop.completed_revenue
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>


                <!-- ====================================================
                     TOP SERVICES
                     ==================================================== -->

                <article class="detail-card ranking-card">
                    <div class="ranking-heading">
                        <div class="report-heading-main">
                            <div class="report-heading-icon services">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M5 7h14M5 12h14M5 17h14"
                                    />

                                    <circle
                                        cx="8"
                                        cy="7"
                                        r="1"
                                    />

                                    <circle
                                        cx="8"
                                        cy="12"
                                        r="1"
                                    />

                                    <circle
                                        cx="8"
                                        cy="17"
                                        r="1"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    TOP 5 SERVICES
                                </p>

                                <h3>
                                    Services by booking volume
                                </h3>

                                <p>
                                    Services with the highest booking
                                    activity during this period.
                                </p>
                            </div>
                        </div>
                    </div>


                    <div
                        v-if="
                            topServices.length === 0
                        "
                        class="empty-state report-empty-state"
                    >
                        <div class="empty-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M5 7h14M5 12h14M5 17h14"
                                />
                            </svg>
                        </div>

                        <h3>
                            No service rankings
                        </h3>

                        <p>
                            No service bookings exist for this
                            reporting period.
                        </p>
                    </div>


                    <div
                        v-else
                        class="table-wrap ranking-table-wrap"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        Rank
                                    </th>

                                    <th>
                                        Service
                                    </th>

                                    <th>
                                        Shop
                                    </th>

                                    <th>
                                        Bookings
                                    </th>

                                    <th>
                                        Completed
                                    </th>

                                    <th>
                                        No-show
                                    </th>

                                    <th>
                                        No-show Rate
                                    </th>

                                    <th>
                                        Completed Revenue
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="
                                        (service, index)
                                        in topServices
                                    "
                                    :key="
                                        service.service_id
                                    "
                                >
                                    <td>
                                        <span
                                            class="rank"
                                            :class="{
                                                top:
                                                    index === 0,
                                            }"
                                        >
                                            {{ index + 1 }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="service-cell">
                                            <strong>
                                                {{
                                                    service.service_name
                                                }}
                                            </strong>

                                            <span>
                                                Service
                                                #{{
                                                    service.service_id
                                                }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="entity-link compact"
                                            @click="
                                                openShop(
                                                    service.shop_id
                                                )
                                            "
                                        >
                                            {{
                                                service.shop_name
                                            }}
                                        </button>
                                    </td>

                                    <td>
                                        {{
                                            formatNumber(
                                                service.total_bookings
                                            )
                                        }}
                                    </td>

                                    <td>
                                        <span class="positive-value">
                                            {{
                                                formatNumber(
                                                    service.completed_bookings
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        {{
                                            formatNumber(
                                                service.no_show_count
                                            )
                                        }}
                                    </td>

                                    <td>
                                        <span
                                            class="rate-value"
                                            :class="{
                                                warning:
                                                    Number(
                                                        service.no_show_rate
                                                        ?? 0
                                                    ) > 0,
                                            }"
                                        >
                                            {{
                                                formatPercent(
                                                    service.no_show_rate
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <td class="revenue">
                                        {{
                                            formatCurrency(
                                                service.completed_revenue
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 FEEDBACK + RESCHEDULING
                 ======================================================== -->

            <section class="two-column-grid reports-lower-grid">
                <!-- FEEDBACK -->

                <article class="detail-card report-card">
                    <div class="report-card-heading">
                        <div class="report-heading-main">
                            <div class="report-heading-icon feedback">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 5h16v12H9l-5 4V5z"
                                    />

                                    <path
                                        d="M8 9h8M8 13h5"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    FEEDBACK
                                </p>

                                <h3>
                                    Customer ratings
                                </h3>

                                <p>
                                    Rating distribution from feedback
                                    submitted during the period.
                                </p>
                            </div>
                        </div>
                    </div>


                    <div class="feedback-summary">
                        <div class="feedback-summary-card rating">
                            <div class="feedback-summary-label">
                                Average rating
                            </div>

                            <div class="feedback-rating-line">
                                <strong>
                                    {{
                                        formatDecimal(
                                            feedback
                                                .average_rating
                                        )
                                    }}
                                </strong>

                                <span>
                                    ★
                                </span>
                            </div>

                            <small>
                                Out of 5
                            </small>
                        </div>


                        <div class="feedback-summary-card reviews">
                            <div class="feedback-summary-label">
                                Total reviews
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        feedback.total
                                    )
                                }}
                            </strong>

                            <small>
                                Submitted reviews
                            </small>
                        </div>
                    </div>


                    <div class="rating-list">
                        <div
                            v-for="
                                row
                                in ratingRows
                            "
                            :key="
                                row.rating
                            "
                            class="rating-row"
                        >
                            <span class="rating-label">
                                {{ row.rating }}

                                <span>
                                    ★
                                </span>
                            </span>

                            <div class="rating-track">
                                <div
                                    class="rating-fill"
                                    :style="{
                                        width:
                                            ratingWidth(
                                                row.count
                                            ),
                                    }"
                                />
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        row.count
                                    )
                                }}
                            </strong>
                        </div>
                    </div>
                </article>


                <!-- RESCHEDULE -->

                <article class="detail-card report-card">
                    <div class="report-card-heading">
                        <div class="report-heading-main">
                            <div class="report-heading-icon reschedule">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 4v6h6"
                                    />

                                    <path
                                        d="M5.5 15a8 8 0 102-8"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    RESCHEDULING
                                </p>

                                <h3>
                                    Reschedule requests
                                </h3>

                                <p>
                                    Request outcomes recorded during
                                    the selected period.
                                </p>
                            </div>
                        </div>

                        <div class="large-summary">
                            <span>
                                Total
                            </span>

                            <strong>
                                {{
                                    formatNumber(
                                        rescheduleRequests
                                            .total
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <div class="reschedule-list">
                        <div class="reschedule-row pending">
                            <div>
                                <span class="reschedule-dot" />

                                <span>
                                    Pending
                                </span>
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        rescheduleRequests
                                            .pending
                                    )
                                }}
                            </strong>
                        </div>

                        <div class="reschedule-row approved">
                            <div>
                                <span class="reschedule-dot" />

                                <span>
                                    Approved
                                </span>
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        rescheduleRequests
                                            .approved
                                    )
                                }}
                            </strong>
                        </div>

                        <div class="reschedule-row rejected">
                            <div>
                                <span class="reschedule-dot" />

                                <span>
                                    Rejected
                                </span>
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        rescheduleRequests
                                            .rejected
                                    )
                                }}
                            </strong>
                        </div>

                        <div class="reschedule-row withdrawn">
                            <div>
                                <span class="reschedule-dot" />

                                <span>
                                    Withdrawn
                                </span>
                            </div>

                            <strong>
                                {{
                                    formatNumber(
                                        rescheduleRequests
                                            .withdrawn
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <div class="reschedule-note">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M12 11v5M12 8h.01"
                            />
                        </svg>

                        <span>
                            These counts reflect reschedule request
                            records associated with bookings in the
                            selected reporting period.
                        </span>
                    </div>
                </article>
            </section>
        </template>
    </div>
</template>


<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.reports-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRO
|--------------------------------------------------------------------------
*/

.reports-intro {
    min-height: 145px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 25px;

    padding:
        21px
        22px;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        var(--sh-radius-xl);

    background:
        radial-gradient(
            circle at 94% 10%,
            rgba(
                214,
                154,
                45,
                0.08
            ),
            transparent 28%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.reports-intro-copy {
    min-width: 0;

    flex: 1;
}

.reports-label {
    width: fit-content;

    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.1px;

    text-transform:
        uppercase;
}

.reports-label-dot {
    width: 6px;
    height: 6px;

    border-radius:
        999px;

    background:
        var(--sh-accent);

    box-shadow:
        0 0 0 4px
        rgba(
            214,
            154,
            45,
            0.08
        );
}

.reports-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 23px;
    font-weight: 800;

    line-height: 30px;

    letter-spacing:
        -0.65px;
}

.reports-intro p {
    max-width: 700px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}


/*
|--------------------------------------------------------------------------
| INTRO RIGHT
|--------------------------------------------------------------------------
*/

.reports-intro-right {
    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 8px;
}

.report-period-display {
    min-width: 230px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding:
        10px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 12px;

    background:
        var(--sh-surface-muted);
}

.report-period-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.report-period-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.report-period-display
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.report-period-display span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.report-period-display strong {
    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;
}

.generated-information {
    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.generated-information strong {
    color:
        var(--sh-text-2);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| PERIOD FILTER
|--------------------------------------------------------------------------
*/

.reports-period-card {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;

    padding:
        15px
        16px;
}

.period-card-heading {
    min-width: 240px;

    display: flex;
    align-items: center;

    gap: 10px;
}

.period-heading-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-2);
}

.period-heading-icon svg {
    width: 17px;
    height: 17px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.period-card-heading
.eyebrow {
    margin-bottom: 1px;
}

.period-card-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 11px;
    font-weight: 800;
}

.period-card-heading p:last-child {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8px;
}

.reports-period-filters {
    flex: 0 1 auto;

    justify-content: flex-end;

    margin: 0;
}

.period-field {
    display: flex;
    flex-direction: column;

    gap: 6px;
}

.period-field > span {
    color:
        var(--sh-text-2);

    font-size: 9px;
    font-weight: 700;
}

.period-field input {
    width: 155px;
}

.generate-button svg,
.reports-period-filters
.secondary-button svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| REPORT SCOPE
|--------------------------------------------------------------------------
*/

.report-scope-note {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    padding:
        10px
        12px;

    border:
        1px solid
        #d7e2f7;

    border-radius: 11px;

    background:
        var(--sh-info-bg);
}

.scope-note-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-info);
}

.scope-note-icon svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.report-scope-note
> div:last-child {
    min-width: 0;
}

.report-scope-note strong {
    color:
        #365a9c;

    font-size: 8.5px;
}

.report-scope-note p {
    margin:
        2px
        0
        0;

    color:
        #607ca9;

    font-size: 8px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.reports-error {
    align-items: center;

    margin: 0;
}

.reports-error-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        rgba(
            169,
            79,
            79,
            0.08
        );
}

.reports-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.reports-error-copy {
    min-width: 0;

    flex: 1;
}

.reports-error-copy strong {
    display: block;

    font-size: 10px;
}

.reports-error-copy p {
    margin:
        2px
        0
        0;
}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.reports-loading-card {
    min-height: 360px;
}

.reports-loading-icon {
    width: 54px;
    height: 54px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 12px;

    border-radius: 16px;

    background:
        var(--sh-surface-muted);
}

.reports-loading-icon
.spinner {
    margin: 0;
}


/*
|--------------------------------------------------------------------------
| SECTION HEADING
|--------------------------------------------------------------------------
*/

.report-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 16px;

    margin:
        13px
        2px
        0;
}

.report-section-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;

    letter-spacing:
        -0.4px;
}

.report-section-heading p:last-child {
    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 14px;
}

.section-period-badge {
    min-height: 26px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 999px;

    background:
        var(--sh-surface);

    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 700;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| PLATFORM STATE CARDS
|--------------------------------------------------------------------------
*/

.platform-state-grid {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.platform-state-card {
    min-width: 0;

    min-height: 110px;

    display: flex;
    align-items: center;

    gap: 12px;

    padding: 14px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.state-card-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.state-card-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.state-card-icon.accounts {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.state-card-icon.customers {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.state-card-icon.owners {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.state-card-icon.shops {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.state-card-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.state-card-copy span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.state-card-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 21px;
    font-weight: 800;

    line-height: 25px;
}

.state-card-copy small {
    margin-top: 3px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| REPORT CARD
|--------------------------------------------------------------------------
*/

.report-card {
    padding: 0;

    overflow: hidden;
}

.report-card-heading,
.ranking-heading {
    min-height: 74px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    padding:
        14px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.report-heading-main {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.report-heading-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.report-heading-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.report-heading-icon.shop-operation {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.report-heading-icon.appointments {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.report-heading-icon.ranking {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.report-heading-icon.services {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.report-heading-icon.feedback {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.report-heading-icon.reschedule {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.report-heading-main
.eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.report-heading-main h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.report-heading-main
p:last-child {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| SHOP APPROVAL OVERVIEW
|--------------------------------------------------------------------------
*/

.shop-approval-overview {
    display: flex;
    flex-direction: column;

    align-items: flex-end;

    gap: 1px;
}

.shop-approval-overview span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.shop-approval-overview strong {
    color:
        var(--sh-success);

    font-size: 17px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| AVAILABILITY
|--------------------------------------------------------------------------
*/

.availability-grid {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(
                0,
                1fr
            )
        );

    gap: 10px;

    padding:
        15px
        16px;
}

.availability-card {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 9px;

    padding:
        11px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        #fbfcfd;
}

.availability-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        #f0f2f5;

    color:
        var(--sh-text-3);
}

.availability-icon svg {
    width: 16px;
    height: 16px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.availability-card.open
.availability-icon {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.availability-card.closed
.availability-icon {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.availability-card.booked
.availability-icon {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.availability-card.walkins
.availability-icon {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.availability-card
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.availability-card span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.availability-card strong {
    color:
        var(--sh-text);

    font-size: 16px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| SHOP APPROVAL STRIP
|--------------------------------------------------------------------------
*/

.shop-approval-strip {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 1px;

    border-top:
        1px solid
        var(--sh-border);

    background:
        var(--sh-border);
}

.shop-approval-strip > div {
    min-height: 49px;

    display: flex;
    align-items: center;

    gap: 7px;

    padding:
        10px
        14px;

    background:
        #fbfcfd;
}

.shop-approval-strip span:not(.approval-dot) {
    flex: 1;

    color:
        var(--sh-text-3);

    font-size: 8px;
}

.shop-approval-strip strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.approval-dot {
    width: 7px;
    height: 7px;

    flex: 0 0 7px;

    border-radius: 999px;
}

.approval-dot.approved {
    background:
        var(--sh-success);
}

.approval-dot.pending {
    background:
        var(--sh-warning);
}

.approval-dot.rejected {
    background:
        var(--sh-danger);
}


/*
|--------------------------------------------------------------------------
| BOOKING SUMMARY
|--------------------------------------------------------------------------
*/

.booking-summary-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.booking-total-card {
    min-width: 0;

    min-height: 100px;

    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        13px
        14px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.booking-total-icon {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.booking-total-icon.customers {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.booking-total-icon.shops {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.booking-total-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.booking-total-card
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.booking-total-card span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.booking-total-card strong {
    margin-top: 1px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;
}

.booking-total-card small {
    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT OUTCOMES
|--------------------------------------------------------------------------
*/

.appointment-outcome-grid {
    display: grid;

    grid-template-columns:
        repeat(
            6,
            minmax(
                0,
                1fr
            )
        );

    gap: 9px;

    padding:
        15px
        16px
        17px;
}

.outcome-card {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 4px;

    padding:
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 10px;

    background:
        #fbfcfd;
}

.outcome-card span {
    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 700;
}

.outcome-card strong {
    color:
        var(--sh-text);

    font-size: 18px;
    font-weight: 800;
}

.outcome-card.pending {
    border-color:
        #f0dfb9;

    background:
        var(--sh-warning-bg);
}

.outcome-card.confirmed {
    border-color:
        #d9e4f8;

    background:
        var(--sh-info-bg);
}

.outcome-card.completed {
    border-color:
        #d4ebdf;

    background:
        var(--sh-success-bg);
}

.outcome-card.no-show,
.outcome-card.cancelled,
.outcome-card.declined {
    border-color:
        #eed3d3;

    background:
        var(--sh-danger-bg);
}


/*
|--------------------------------------------------------------------------
| PERFORMANCE CARDS
|--------------------------------------------------------------------------
*/

.performance-grid {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.performance-card {
    min-width: 0;

    min-height: 112px;

    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        14px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.performance-card.highlight {
    border-color:
        #eadfbf;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-accent-soft)
        );
}

.performance-icon {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.performance-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.performance-icon.no-show {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.performance-icon.outcomes {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.performance-icon.complete {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.performance-icon.failure {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.performance-card
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.performance-card span {
    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 700;
}

.performance-card strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;
}

.performance-card small {
    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 12px;
}


/*
|--------------------------------------------------------------------------
| RANKINGS
|--------------------------------------------------------------------------
*/

.reports-ranking-grid {
    grid-template-columns:
        1fr;

    margin-top: 0;
}

.ranking-card {
    padding: 0;

    overflow: hidden;
}

.ranking-table-wrap table {
    min-width: 850px;
}

.rank {
    width: 28px;
    height: 28px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 800;
}

.rank.top {
    background:
        var(--sh-accent-soft);

    color:
        #855e1d;
}

.entity-link {
    min-height: 30px;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    padding: 0;

    border: 0;

    background:
        transparent;

    color:
        var(--sh-text);

    font: inherit;

    font-size: 9px;
    font-weight: 750;

    cursor: pointer;

    text-align: left;
}

.entity-link:hover {
    color:
        #8a621f;
}

.entity-link.compact {
    color:
        var(--sh-info);

    font-size: 8.5px;
}

.shop-rank-icon {
    width: 28px;
    height: 28px;

    flex: 0 0 28px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.shop-rank-icon svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.service-cell {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.service-cell strong {
    color:
        var(--sh-text);

    font-size: 9px;
}

.service-cell span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.positive-value {
    color:
        var(--sh-success);

    font-weight: 750;
}

.rate-value {
    color:
        var(--sh-text-2);

    font-weight: 700;
}

.rate-value.warning {
    color:
        var(--sh-warning);
}

.revenue {
    color:
        var(--sh-success) !important;

    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| LOWER GRID
|--------------------------------------------------------------------------
*/

.reports-lower-grid {
    margin-top: 0;
}


/*
|--------------------------------------------------------------------------
| FEEDBACK SUMMARY
|--------------------------------------------------------------------------
*/

.feedback-summary {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 10px;

    margin:
        15px
        16px;
}

.feedback-summary-card {
    min-width: 0;

    padding:
        12px
        13px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.feedback-summary-card.rating {
    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);
}

.feedback-summary-label {
    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 700;
}

.feedback-summary-card
> strong {
    display: block;

    margin-top: 3px;

    color:
        var(--sh-text);

    font-size: 21px;
    font-weight: 800;
}

.feedback-summary-card small {
    display: block;

    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.feedback-rating-line {
    display: flex;
    align-items: center;

    gap: 5px;
}

.feedback-rating-line strong {
    color:
        var(--sh-text);

    font-size: 21px;
    font-weight: 800;
}

.feedback-rating-line span {
    color:
        var(--sh-accent);

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| RATINGS
|--------------------------------------------------------------------------
*/

.rating-list {
    gap: 10px;

    padding:
        0
        16px
        17px;
}

.rating-row {
    grid-template-columns:
        34px
        minmax(
            0,
            1fr
        )
        36px;
}

.rating-label {
    display: inline-flex;
    align-items: center;

    gap: 3px;
}

.rating-label span {
    color:
        var(--sh-accent);
}


/*
|--------------------------------------------------------------------------
| RESCHEDULE
|--------------------------------------------------------------------------
*/

.reschedule-list {
    display: flex;
    flex-direction: column;

    padding:
        7px
        16px;
}

.reschedule-row {
    min-height: 53px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        9px
        0;

    border-bottom:
        1px solid
        var(--sh-border);
}

.reschedule-row:last-child {
    border-bottom: 0;
}

.reschedule-row > div {
    display: flex;
    align-items: center;

    gap: 7px;
}

.reschedule-row span:not(.reschedule-dot) {
    color:
        var(--sh-text-2);

    font-size: 9px;
    font-weight: 700;
}

.reschedule-row strong {
    color:
        var(--sh-text);

    font-size: 11px;
    font-weight: 800;
}

.reschedule-dot {
    width: 7px;
    height: 7px;

    border-radius: 999px;

    background:
        var(--sh-text-3);
}

.reschedule-row.pending
.reschedule-dot {
    background:
        var(--sh-warning);
}

.reschedule-row.approved
.reschedule-dot {
    background:
        var(--sh-success);
}

.reschedule-row.rejected
.reschedule-dot {
    background:
        var(--sh-danger);
}

.reschedule-row.withdrawn
.reschedule-dot {
    background:
        var(--sh-text-3);
}

.reschedule-note {
    display: flex;
    align-items: flex-start;

    gap: 7px;

    margin:
        5px
        16px
        16px;

    padding:
        10px
        11px;

    border-radius: 10px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 14px;
}

.reschedule-note svg {
    width: 13px;
    height: 13px;

    flex: 0 0 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.report-empty-state {
    min-height: 190px;

    border: 0;

    border-radius: 0;

    background:
        transparent;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        reports-spin
        0.8s
        linear
        infinite;
}

@keyframes reports-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1250px) {
    .platform-state-grid,
    .performance-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .appointment-outcome-grid {
        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .reports-period-card {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .reports-period-filters {
        width: 100%;

        justify-content:
            flex-start;
    }
}


@media (max-width: 1000px) {
    .reports-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .reports-intro-right {
        width: 100%;

        align-items:
            stretch;
    }

    .report-period-display {
        width: 100%;
    }

    .generated-information {
        justify-content:
            flex-start;
    }

    .availability-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .booking-summary-grid {
        grid-template-columns:
            1fr;
    }

    .reports-lower-grid {
        grid-template-columns:
            1fr;
    }
}


@media (max-width: 760px) {
    .reports-period-filters {
        display: grid;

        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .period-field input {
        width: 100%;
    }

    .platform-state-grid,
    .performance-grid {
        grid-template-columns:
            1fr;
    }

    .report-section-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .appointment-outcome-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }
}


@media (max-width: 680px) {
    .reports-intro {
        padding: 17px;
    }

    .reports-intro h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .reports-period-card {
        padding: 14px;
    }

    .period-card-heading {
        min-width: 0;

        align-items:
            flex-start;
    }

    .reports-period-filters {
        display: flex;

        align-items:
            stretch;

        flex-direction:
            column;
    }

    .reports-period-filters
    .primary-button,
    .reports-period-filters
    .secondary-button {
        width: 100%;
    }

    .reports-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .reports-error
    .retry-button {
        width: 100%;
    }

    .availability-grid {
        grid-template-columns:
            1fr;
    }

    .shop-approval-strip {
        grid-template-columns:
            1fr;
    }

    .appointment-outcome-grid {
        grid-template-columns:
            1fr;

        padding: 14px;
    }

    .report-card-heading,
    .ranking-heading {
        align-items:
            flex-start;
    }

    .shop-approval-overview,
    .large-summary {
        align-items:
            flex-end;

        flex: 0 0 auto;
    }

    .feedback-summary {
        grid-template-columns:
            1fr;
    }
}
</style>