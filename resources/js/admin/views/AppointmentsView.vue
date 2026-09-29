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

const appointments = ref([]);

const isLoading = ref(false);
const errorMessage = ref('');

const pagination = ref({
    current_page: 1,
    per_page: 20,
    total: 0,
    last_page: 1,
    has_more_pages: false,
});

const filters = reactive({
    search: '',
    status: '',
    shop_id: '',
    customer_id: '',
    date_from: '',
    date_to: '',
    per_page: 20,
});

const statusOptions = [
    {
        value: 'pending',
        label: 'Pending',
    },
    {
        value: 'confirmed',
        label: 'Confirmed',
    },
    {
        value: 'completed',
        label: 'Completed',
    },
    {
        value: 'cancelled',
        label: 'Cancelled',
    },
    {
        value: 'declined',
        label: 'Declined',
    },
];

const numberFormatter =
    new Intl.NumberFormat(
        'en-PH',
    );


/*
|--------------------------------------------------------------------------
| FORMATTERS
|--------------------------------------------------------------------------
*/

function formatNumber(value) {
    const number =
        Number(
            value ?? 0,
        );

    if (
        !Number.isFinite(
            number,
        )
    ) {
        return '0';
    }

    return numberFormatter.format(
        number,
    );
}

function humanize(value) {
    if (!value) {
        return '—';
    }

    return String(value)
        .replaceAll(
            '_',
            ' ',
        )
        .replace(
            /\b\w/g,
            (character) =>
                character.toUpperCase(),
        );
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date =
        new Date(
            `${value}T00:00:00`,
        );

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-PH',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: 'Asia/Manila',
        },
    ).format(date);
}

function formatTime(value) {
    if (!value) {
        return '—';
    }

    const parts =
        String(value)
            .split(':');

    if (
        parts.length < 2
    ) {
        return value;
    }

    const hours =
        Number(
            parts[0],
        );

    const minutes =
        Number(
            parts[1],
        );

    if (
        !Number.isInteger(hours)
        ||
        !Number.isInteger(minutes)
    ) {
        return value;
    }

    const date =
        new Date(
            2000,
            0,
            1,
            hours,
            minutes,
        );

    return new Intl.DateTimeFormat(
        'en-PH',
        {
            hour: 'numeric',
            minute: '2-digit',
        },
    ).format(date);
}

function statusClass(value) {
    return String(
        value ?? '',
    )
        .toLowerCase()
        .replaceAll(
            '_',
            '-',
        );
}

function customerInitial(appointment) {
    const name =
        appointment
            ?.customer
            ?.full_name
        ||
        appointment
            ?.customer
            ?.email
        ||
        'C';

    return String(name)
        .charAt(0)
        .toUpperCase();
}


/*
|--------------------------------------------------------------------------
| FILTER COUNT
|--------------------------------------------------------------------------
*/

const activeFiltersCount =
    computed(() => {
        let count = 0;

        if (
            filters.search
                .trim()
        ) {
            count += 1;
        }

        if (
            filters.status
        ) {
            count += 1;
        }

        if (
            filters.shop_id
        ) {
            count += 1;
        }

        if (
            filters.customer_id
        ) {
            count += 1;
        }

        if (
            filters.date_from
        ) {
            count += 1;
        }

        if (
            filters.date_to
        ) {
            count += 1;
        }

        return count;
    });


/*
|--------------------------------------------------------------------------
| CURRENT PAGE STATUS SUMMARY
|--------------------------------------------------------------------------
|
| The appointments endpoint is paginated, so these cards describe only
| the records currently loaded on this page.
|
*/

const currentPageSummary =
    computed(() => {
        const summary = {
            pending: 0,
            confirmed: 0,
            completed: 0,
            cancelled: 0,
            declined: 0,
        };

        for (
            const appointment
            of appointments.value
        ) {
            const status =
                appointment.status;

            if (
                Object.prototype
                    .hasOwnProperty
                    .call(
                        summary,
                        status,
                    )
            ) {
                summary[status] += 1;
            }
        }

        return summary;
    });


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const firstResult =
    computed(() => {
        if (
            pagination.value.total
            === 0
        ) {
            return 0;
        }

        return (
            (
                pagination.value
                    .current_page
                - 1
            )
            *
            pagination.value
                .per_page
        )
        + 1;
    });

const lastResult =
    computed(() =>
        Math.min(
            pagination.value.total,

            pagination.value
                .current_page
            *
            pagination.value
                .per_page,
        )
    );

const visiblePages =
    computed(() => {
        const current =
            pagination.value
                .current_page;

        const last =
            pagination.value
                .last_page;

        const start =
            Math.max(
                1,
                current - 2,
            );

        const end =
            Math.min(
                last,
                current + 2,
            );

        const pages = [];

        for (
            let page = start;
            page <= end;
            page += 1
        ) {
            pages.push(
                page,
            );
        }

        return pages;
    });


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

function buildQuery(page) {
    const query =
        new URLSearchParams();

    const search =
        filters.search
            .trim();

    if (search) {
        query.set(
            'search',
            search,
        );
    }

    if (
        filters.status
    ) {
        query.set(
            'status',
            filters.status,
        );
    }

    if (
        filters.shop_id
    ) {
        query.set(
            'shop_id',
            String(
                filters.shop_id,
            ),
        );
    }

    if (
        filters.customer_id
    ) {
        query.set(
            'customer_id',
            String(
                filters.customer_id,
            ),
        );
    }

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

    query.set(
        'page',
        String(page),
    );

    query.set(
        'per_page',
        String(
            filters.per_page,
        ),
    );

    return query.toString();
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}


/*
|--------------------------------------------------------------------------
| LOAD APPOINTMENTS
|--------------------------------------------------------------------------
*/

async function loadAppointments(
    page = 1,
) {
    if (
        !auth.token
    ) {
        await authenticationFailure();

        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response =
            await apiRequest(
                `/admin/appointments?${buildQuery(page)}`,
                {
                    token:
                        auth.token,
                },
            );

        appointments.value =
            Array.isArray(
                response
                    ?.data
                    ?.appointments,
            )
                ? response
                    .data
                    .appointments
                : [];

        pagination.value = {
            current_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.current_page
                    ?? 1,
                ),

            per_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.per_page
                    ?? filters.per_page,
                ),

            total:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.total
                    ?? 0,
                ),

            last_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.last_page
                    ?? 1,
                ),

            has_more_pages:
                Boolean(
                    response
                        ?.data
                        ?.pagination
                        ?.has_more_pages,
                ),
        };
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

        errorMessage.value =
            error
                ?.data
                ?.message
            ??
            error
                ?.message
            ??
            'Unable to load appointments.';
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

async function applyFilters() {
    errorMessage.value =
        '';

    if (
        filters.date_from
        &&
        filters.date_to
        &&
        filters.date_to
            <
            filters.date_from
    ) {
        errorMessage.value =
            'The end date must be on or after the start date.';

        return;
    }

    await loadAppointments(
        1,
    );
}

async function resetFilters() {
    filters.search = '';
    filters.status = '';
    filters.shop_id = '';
    filters.customer_id = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.per_page = 20;

    await loadAppointments(
        1,
    );
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

async function changePage(page) {
    if (
        page < 1
        ||
        page
            >
            pagination.value
                .last_page
        ||
        page
            ===
            pagination.value
                .current_page
        ||
        isLoading.value
    ) {
        return;
    }

    await loadAppointments(
        page,
    );
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function openAppointment(
    appointment,
) {
    await router.push({
        name:
            'admin.appointment.detail',

        params: {
            appointmentId:
                appointment.id,
        },
    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadAppointments();
});
</script>


<template>
    <div class="appointments-page">
        <!-- ============================================================
             INTRODUCTION
             ============================================================ -->

        <section class="appointments-intro">
            <div class="appointments-intro-copy">
                <div class="appointments-intro-label">
                    <span class="appointments-intro-dot" />

                    Booking operations
                </div>

                <h2>
                    Appointment monitoring
                </h2>

                <p>
                    Review bookings across all StyleHub shops,
                    monitor their schedules and status, and
                    inspect complete appointment records.
                </p>
            </div>


            <div class="appointments-intro-actions">
                <div class="appointments-total-card">
                    <div class="appointments-total-icon">
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
                                d="M8 14h3M14 14h2M8 17h2"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Booking records
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <button
                    type="button"
                    class="secondary-button appointments-refresh-button"
                    :disabled="isLoading"
                    @click="
                        loadAppointments(
                            pagination.current_page
                        )
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
                            d="M20 11a8 8 0 10-2.3 5.7"
                        />

                        <path
                            d="M20 5v6h-6"
                        />
                    </svg>

                    <span>
                        {{
                            isLoading
                                ? 'Refreshing'
                                : 'Refresh'
                        }}
                    </span>
                </button>
            </div>
        </section>


        <!-- ============================================================
             READ-ONLY NOTICE
             ============================================================ -->

        <section class="monitoring-banner">
            <div class="monitoring-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"
                    />

                    <circle
                        cx="12"
                        cy="12"
                        r="2.5"
                    />
                </svg>
            </div>

            <div class="monitoring-copy">
                <strong>
                    Administrator monitoring mode
                </strong>

                <p>
                    Appointment management is read-only from the
                    administrator portal. Booking status changes
                    remain controlled by the Customer and Shop
                    Owner booking workflow.
                </p>
            </div>

            <span class="monitoring-badge">
                Read only
            </span>
        </section>


        <!-- ============================================================
             CURRENT PAGE SUMMARY
             ============================================================ -->

        <section
            v-if="
                appointments.length > 0
            "
            class="appointment-summary-grid"
        >
            <!-- PENDING -->

            <article class="appointment-summary-card">
                <div class="summary-status-icon pending">
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
                            d="M12 7v5l3 2"
                        />
                    </svg>
                </div>

                <div class="summary-status-copy">
                    <span>
                        Pending
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.pending
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- CONFIRMED -->

            <article class="appointment-summary-card">
                <div class="summary-status-icon confirmed">
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

                <div class="summary-status-copy">
                    <span>
                        Confirmed
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.confirmed
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- COMPLETED -->

            <article class="appointment-summary-card">
                <div class="summary-status-icon completed">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M5 12l4 4L19 6"
                        />
                    </svg>
                </div>

                <div class="summary-status-copy">
                    <span>
                        Completed
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.completed
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- CANCELLED -->

            <article class="appointment-summary-card">
                <div class="summary-status-icon cancelled">
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

                <div class="summary-status-copy">
                    <span>
                        Cancelled
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.cancelled
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- DECLINED -->

            <article class="appointment-summary-card">
                <div class="summary-status-icon declined">
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
                            d="M8 12h8"
                        />
                    </svg>
                </div>

                <div class="summary-status-copy">
                    <span>
                        Declined
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.declined
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>
        </section>


        <!-- ============================================================
             FILTER PANEL
             ============================================================ -->

        <section class="filter-card appointments-filter-card">
            <div class="filter-card-heading">
                <div class="filter-heading-left">
                    <div class="filter-heading-icon">
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
                        <h3>
                            Filter booking records
                        </h3>

                        <p>
                            Search by Customer, shop or service,
                            or narrow results by status and schedule.
                        </p>
                    </div>
                </div>


                <span
                    v-if="
                        activeFiltersCount > 0
                    "
                    class="active-filter-count"
                >
                    {{
                        activeFiltersCount
                    }}
                    active
                </span>
            </div>


            <form
                class="appointments-filters"
                @submit.prevent="
                    applyFilters
                "
            >
                <!-- SEARCH -->

                <div class="search-field appointment-search">
                    <label for="appointment-search">
                        Search
                    </label>

                    <div class="input-with-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="11"
                                cy="11"
                                r="7"
                            />

                            <path
                                d="M20 20l-4-4"
                            />
                        </svg>

                        <input
                            id="appointment-search"
                            v-model="
                                filters.search
                            "
                            type="search"
                            maxlength="100"
                            placeholder="Customer, shop or service"
                        >
                    </div>
                </div>


                <!-- STATUS -->

                <div class="filter-field">
                    <label for="appointment-status">
                        Status
                    </label>

                    <select
                        id="appointment-status"
                        v-model="
                            filters.status
                        "
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="
                                option
                                in statusOptions
                            "
                            :key="
                                option.value
                            "
                            :value="
                                option.value
                            "
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>


                <!-- SHOP ID -->

                <div class="filter-field">
                    <label for="shop-id">
                        Shop ID
                    </label>

                    <input
                        id="shop-id"
                        v-model="
                            filters.shop_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>


                <!-- CUSTOMER ID -->

                <div class="filter-field">
                    <label for="customer-id">
                        Customer ID
                    </label>

                    <input
                        id="customer-id"
                        v-model="
                            filters.customer_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>


                <!-- FROM -->

                <div class="filter-field">
                    <label for="date-from">
                        From
                    </label>

                    <input
                        id="date-from"
                        v-model="
                            filters.date_from
                        "
                        type="date"
                    >
                </div>


                <!-- TO -->

                <div class="filter-field">
                    <label for="date-to">
                        To
                    </label>

                    <input
                        id="date-to"
                        v-model="
                            filters.date_to
                        "
                        type="date"
                    >
                </div>


                <!-- ACTIONS -->

                <div class="filter-actions appointments-filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="
                            isLoading
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6h16M7 12h10M10 18h4"
                            />
                        </svg>

                        <span>
                            Apply filters
                        </span>
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="
                            isLoading
                            ||
                            activeFiltersCount === 0
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
                            Reset
                        </span>
                    </button>
                </div>
            </form>
        </section>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                errorMessage
            "
            class="error-message appointments-error"
            role="alert"
        >
            <div class="appointments-error-icon">
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

            <div class="appointments-error-copy">
                <strong>
                    Unable to load appointments
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
                    loadAppointments(
                        pagination.current_page
                    )
                "
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             TABLE CARD
             ============================================================ -->

        <section class="appointments-card">
            <!-- TABLE HEADER -->

            <div class="table-header appointments-table-header">
                <div class="table-heading">
                    <div class="table-heading-icon">
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
                                d="M8 14h3M14 14h2M8 17h2"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3>
                            Booking records
                        </h3>

                        <p>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}
                            appointment{{
                                pagination.total === 1
                                    ? ''
                                    : 's'
                            }}
                        </p>
                    </div>
                </div>


                <div class="table-header-actions">
                    <div
                        v-if="
                            activeFiltersCount > 0
                        "
                        class="table-filter-status"
                    >
                        <span class="table-filter-dot" />

                        Filtered
                    </div>


                    <div class="per-page">
                        <label for="appointments-per-page">
                            Rows
                        </label>

                        <select
                            id="appointments-per-page"
                            v-model.number="
                                filters.per_page
                            "
                            :disabled="
                                isLoading
                            "
                            @change="
                                loadAppointments(
                                    1
                                )
                            "
                        >
                            <option :value="10">
                                10
                            </option>

                            <option :value="20">
                                20
                            </option>

                            <option :value="50">
                                50
                            </option>
                        </select>
                    </div>
                </div>
            </div>


            <!-- ========================================================
                 LOADING
                 ======================================================== -->

            <div
                v-if="
                    isLoading
                    &&
                    appointments.length === 0
                "
                class="appointments-loading-state"
            >
                <div class="appointments-loading-icon">
                    <div class="spinner" />
                </div>

                <h3>
                    Loading appointments
                </h3>

                <p>
                    Retrieving booking records from
                    StyleHub shops.
                </p>
            </div>


            <!-- ========================================================
                 EMPTY
                 ======================================================== -->

            <div
                v-else-if="
                    appointments.length === 0
                "
                class="empty-state appointments-empty-state"
            >
                <div class="empty-icon">
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
                            d="M8 14h8"
                        />
                    </svg>
                </div>

                <h3>
                    No appointments found
                </h3>

                <p>
                    No booking records match the
                    selected search or filters.
                </p>

                <button
                    v-if="
                        activeFiltersCount > 0
                    "
                    type="button"
                    class="secondary-button empty-reset-button"
                    @click="
                        resetFilters
                    "
                >
                    Clear filters
                </button>
            </div>


            <!-- ========================================================
                 TABLE
                 ======================================================== -->

            <div
                v-else
                class="table-wrapper appointments-table-wrapper"
                :class="{
                    'table-refreshing':
                        isLoading,
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th>
                                Appointment
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Shop
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Schedule
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Reschedules
                            </th>

                            <th class="table-actions-heading">
                                Action
                            </th>
                        </tr>
                    </thead>


                    <tbody>
                        <tr
                            v-for="
                                appointment
                                in appointments
                            "
                            :key="
                                appointment.id
                            "
                            class="appointment-table-row"
                            @dblclick="
                                openAppointment(
                                    appointment
                                )
                            "
                        >
                            <!-- APPOINTMENT -->

                            <td>
                                <div class="appointment-reference">
                                    <div class="appointment-reference-icon">
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
                                        <strong>
                                            #{{ appointment.id }}
                                        </strong>

                                        <span>
                                            Booking record
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- CUSTOMER -->

                            <td>
                                <div class="customer-cell appointment-customer">
                                    <div class="customer-avatar">
                                        {{
                                            customerInitial(
                                                appointment
                                            )
                                        }}
                                    </div>

                                    <div>
                                        <strong>
                                            {{
                                                appointment
                                                    .customer
                                                    ?.full_name
                                                ||
                                                'Unavailable'
                                            }}
                                        </strong>

                                        <span class="table-email">
                                            {{
                                                appointment
                                                    .customer
                                                    ?.email
                                                ||
                                                'No email'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- SHOP -->

                            <td>
                                <div class="shop-cell appointment-shop">
                                    <div class="shop-table-icon">
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
                                        <strong>
                                            {{
                                                appointment
                                                    .shop
                                                    ?.name
                                                ||
                                                'Unavailable'
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                appointment
                                                    .shop
                                                    ?.city
                                                ||
                                                'Location unavailable'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- SERVICE -->

                            <td>
                                <div class="service-cell appointment-service">
                                    <strong>
                                        {{
                                            appointment
                                                .booking_snapshot
                                                ?.service_name
                                            ||
                                            appointment
                                                .service
                                                ?.name
                                            ||
                                            'Unavailable'
                                        }}
                                    </strong>

                                    <span class="snapshot-label">
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M6 3h9l3 3v15H6V3z"
                                            />

                                            <path
                                                d="M14 3v4h4"
                                            />
                                        </svg>

                                        Booking snapshot
                                    </span>
                                </div>
                            </td>


                            <!-- SCHEDULE -->

                            <td>
                                <div class="appointment-schedule">
                                    <div class="schedule-date">
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

                                        <strong>
                                            {{
                                                formatDate(
                                                    appointment
                                                        .appointment_date
                                                )
                                            }}
                                        </strong>
                                    </div>

                                    <span class="schedule-time">
                                        {{
                                            formatTime(
                                                appointment
                                                    .start_time
                                            )
                                        }}

                                        <span>
                                            –
                                        </span>

                                        {{
                                            formatTime(
                                                appointment
                                                    .end_time
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- STATUS -->

                            <td>
                                <span
                                    class="status-badge appointment-status"
                                    :class="
                                        statusClass(
                                            appointment.status
                                        )
                                    "
                                >
                                    <span class="status-dot-small" />

                                    {{
                                        humanize(
                                            appointment.status
                                        )
                                    }}
                                </span>
                            </td>


                            <!-- RESCHEDULES -->

                            <td>
                                <div
                                    class="reschedule-count"
                                    :class="{
                                        'has-reschedules':
                                            Number(
                                                appointment
                                                    .reschedule_requests_count
                                                ?? 0
                                            ) > 0,
                                    }"
                                >
                                    <div class="reschedule-icon">
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

                                    <strong>
                                        {{
                                            formatNumber(
                                                appointment
                                                    .reschedule_requests_count
                                                ?? 0
                                            )
                                        }}
                                    </strong>
                                </div>
                            </td>


                            <!-- ACTION -->

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button appointment-view-button"
                                    @click="
                                        openAppointment(
                                            appointment
                                        )
                                    "
                                >
                                    <span>
                                        View
                                    </span>

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M9 18l6-6-6-6"
                                        />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <!-- ========================================================
                 PAGINATION
                 ======================================================== -->

            <div
                v-if="
                    pagination.total > 0
                "
                class="pagination appointments-pagination"
            >
                <div class="pagination-summary">
                    <span>
                        Showing
                    </span>

                    <strong>
                        {{ firstResult }}
                    </strong>

                    <span>
                        –
                    </span>

                    <strong>
                        {{ lastResult }}
                    </strong>

                    <span>
                        of
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                pagination.total
                            )
                        }}
                    </strong>

                    <span>
                        appointments
                    </span>
                </div>


                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        :disabled="
                            pagination.current_page <= 1
                            ||
                            isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page - 1
                            )
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M15 18l-6-6 6-6"
                            />
                        </svg>

                        <span>
                            Previous
                        </span>
                    </button>


                    <button
                        v-for="
                            page
                            in visiblePages
                        "
                        :key="
                            page
                        "
                        type="button"
                        class="page-button"
                        :class="{
                            active:
                                page
                                ===
                                pagination.current_page,
                        }"
                        :disabled="
                            isLoading
                        "
                        @click="
                            changePage(
                                page
                            )
                        "
                    >
                        {{ page }}
                    </button>


                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        :disabled="
                            pagination.current_page
                            >=
                            pagination.last_page
                            ||
                            isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page + 1
                            )
                        "
                    >
                        <span>
                            Next
                        </span>

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M9 18l6-6-6-6"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>


<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.appointments-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRODUCTION
|--------------------------------------------------------------------------
*/

.appointments-intro {
    min-height: 126px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    padding:
        20px
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
                0.07
            ),
            transparent 26%
        ),
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.appointments-intro-copy {
    min-width: 0;

    flex: 1;
}

.appointments-intro-label {
    width: fit-content;

    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8.5px;
    font-weight: 800;

    letter-spacing: 1.1px;

    text-transform:
        uppercase;
}

.appointments-intro-dot {
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

.appointments-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 22px;
    font-weight: 800;

    line-height: 29px;

    letter-spacing:
        -0.55px;
}

.appointments-intro p {
    max-width: 680px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}

.appointments-intro-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| TOTAL CARD
|--------------------------------------------------------------------------
*/

.appointments-total-card {
    min-width: 170px;

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

.appointments-total-icon {
    width: 38px;
    height: 38px;

    flex:
        0
        0
        38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.appointments-total-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.appointments-total-card
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.appointments-total-card span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 600;
}

.appointments-total-card strong {
    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;

    line-height: 21px;
}

.appointments-refresh-button {
    min-height: 48px;
}

.appointments-refresh-button svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| MONITORING BANNER
|--------------------------------------------------------------------------
*/

.monitoring-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #d7e2f7;

    border-radius:
        13px;

    background:
        linear-gradient(
            135deg,
            var(--sh-info-bg),
            #f8fbff
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.monitoring-icon {
    width: 37px;
    height: 37px;

    flex:
        0
        0
        37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;

    color:
        var(--sh-info);
}

.monitoring-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.monitoring-copy {
    min-width: 0;

    flex: 1;
}

.monitoring-copy strong {
    display: block;

    color:
        #31558e;

    font-size: 9.5px;
    font-weight: 800;
}

.monitoring-copy p {
    max-width: 820px;

    margin:
        3px
        0
        0;

    color:
        #607ca9;

    font-size: 8.5px;

    line-height: 14px;
}

.monitoring-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #ccdaf1;

    border-radius:
        999px;

    background:
        #ffffff;

    color:
        var(--sh-info);

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.5px;

    text-transform:
        uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| SUMMARY GRID
|--------------------------------------------------------------------------
*/

.appointment-summary-grid {
    display: grid;

    grid-template-columns:
        repeat(
            5,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.appointment-summary-card {
    min-width: 0;

    min-height: 82px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding:
        12px
        13px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);

    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.appointment-summary-card:hover {
    transform:
        translateY(
            -1px
        );

    box-shadow:
        var(--sh-shadow-card);
}

.summary-status-icon {
    width: 38px;
    height: 38px;

    flex:
        0
        0
        38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.summary-status-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.summary-status-icon.pending {
    background:
        var(--sh-warning-bg) !important;

    color:
        var(--sh-warning) !important;
}

.summary-status-icon.confirmed {
    background:
        var(--sh-info-bg) !important;

    color:
        var(--sh-info) !important;
}

.summary-status-icon.completed {
    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.summary-status-icon.cancelled,
.summary-status-icon.declined {
    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}

.summary-status-copy {
    min-width: 0;

    flex: 1;

    display: grid;

    grid-template-columns:
        minmax(
            0,
            1fr
        )
        auto;

    align-items: center;

    column-gap: 7px;
}

.summary-status-copy span {
    grid-column: 1;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.summary-status-copy strong {
    grid-column: 2;
    grid-row:
        1
        / 3;

    color:
        var(--sh-text);

    font-size: 18px;
    font-weight: 800;
}

.summary-status-copy small {
    grid-column: 1;

    color:
        #a1a8b2;

    font-size: 7.5px;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.appointments-filter-card {
    padding: 0;
}

.filter-card-heading {
    min-height: 66px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    padding:
        13px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.filter-heading-left {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.filter-heading-icon {
    width: 36px;
    height: 36px;

    flex:
        0
        0
        36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-2);
}

.filter-heading-icon svg {
    width: 17px;
    height: 17px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.filter-heading-left h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.filter-heading-left p {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
}

.active-filter-count {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border:
        1px solid
        #eadfbf;

    border-radius:
        999px;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;

    font-size: 8px;
    font-weight: 800;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| FILTER FORM
|--------------------------------------------------------------------------
*/

.appointments-filters {
    display: grid;

    grid-template-columns:
        minmax(
            230px,
            1.7fr
        )
        repeat(
            3,
            minmax(
                120px,
                0.8fr
            )
        )
        repeat(
            2,
            minmax(
                145px,
                0.9fr
            )
        );

    align-items: end;

    gap: 11px;

    padding:
        15px
        16px
        16px;
}

.appointment-search {
    flex: none;
}

.appointments-filter-actions {
    grid-column:
        1
        / -1;

    align-items: center;
}

.appointments-filter-actions
.primary-button,
.appointments-filter-actions
.secondary-button {
    min-height: 42px;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.appointments-error {
    align-items: center;

    margin: 0;
}

.appointments-error-icon {
    width: 38px;
    height: 38px;

    flex:
        0
        0
        38px;

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

.appointments-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.appointments-error-copy {
    min-width: 0;

    flex: 1;
}

.appointments-error-copy strong {
    display: block;

    font-size: 10.5px;
}

.appointments-error-copy p {
    margin:
        2px
        0
        0;
}


/*
|--------------------------------------------------------------------------
| TABLE HEADER
|--------------------------------------------------------------------------
*/

.appointments-table-header {
    padding:
        13px
        16px;
}

.table-heading {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.table-heading-icon {
    width: 36px;
    height: 36px;

    flex:
        0
        0
        36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.table-heading-icon svg {
    width: 17px;
    height: 17px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.table-header-actions {
    display: flex;
    align-items: center;

    gap: 10px;
}

.table-filter-status {
    display: inline-flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.table-filter-dot {
    width: 6px;
    height: 6px;

    border-radius:
        999px;

    background:
        var(--sh-accent);
}

.per-page {
    display: flex;
    align-items: center;

    gap: 6px;
}

.per-page label {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.per-page select {
    width: 69px;

    min-height: 34px;

    padding:
        5px
        8px;

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.appointments-table-wrapper {
    transition:
        opacity
        0.16s ease;
}

.table-refreshing {
    opacity: 0.55;

    pointer-events: none;
}

.appointments-table-wrapper table {
    min-width: 1280px;
}

.appointment-table-row {
    cursor: default;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT REFERENCE
|--------------------------------------------------------------------------
*/

.appointment-reference {
    display: flex;
    align-items: center;

    gap: 9px;
}

.appointment-reference-icon {
    width: 34px;
    height: 34px;

    flex:
        0
        0
        34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.appointment-reference-icon svg {
    width: 16px;
    height: 16px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.appointment-reference
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.appointment-reference strong {
    color:
        var(--sh-text);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 10px;
    font-weight: 800;
}

.appointment-reference span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| CUSTOMER
|--------------------------------------------------------------------------
*/

.appointment-customer {
    display: flex;
    align-items: center;

    gap: 9px;
}

.customer-avatar {
    width: 34px;
    height: 34px;

    flex:
        0
        0
        34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border:
        1px solid
        #dce6f8;

    border-radius: 10px;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);

    font-size: 10px;
    font-weight: 800;
}

.appointment-customer
> div:last-child {
    min-width: 0;
}

.table-email {
    max-width: 190px;

    overflow: hidden;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| SHOP
|--------------------------------------------------------------------------
*/

.appointment-shop {
    display: flex;
    align-items: center;

    gap: 8px;
}

.shop-table-icon {
    width: 31px;
    height: 31px;

    flex:
        0
        0
        31px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.shop-table-icon svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.appointment-shop
> div:last-child {
    min-width: 0;
}


/*
|--------------------------------------------------------------------------
| SERVICE
|--------------------------------------------------------------------------
*/

.appointment-service {
    min-width: 150px;
}

.snapshot-label {
    width: fit-content;

    display: inline-flex !important;
    align-items: center;

    gap: 4px;

    margin-top:
        4px !important;

    color:
        var(--sh-text-3) !important;

    font-size:
        7.5px !important;
}

.snapshot-label svg {
    width: 10px;
    height: 10px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| SCHEDULE
|--------------------------------------------------------------------------
*/

.appointment-schedule {
    min-width: 145px;

    display: flex;
    flex-direction: column;

    gap: 4px;
}

.schedule-date {
    display: flex;
    align-items: center;

    gap: 5px;
}

.schedule-date svg {
    width: 12px;
    height: 12px;

    flex:
        0
        0
        12px;

    fill: none;

    stroke:
        var(--sh-accent);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.schedule-date strong {
    color:
        var(--sh-text);

    font-size: 9.5px;
    font-weight: 750;
}

.schedule-time {
    padding-left: 17px;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
}

.schedule-time > span {
    margin:
        0
        2px;

    color:
        #bcc1c8;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.appointment-status {
    gap: 5px;
}

.status-dot-small {
    width: 5px;
    height: 5px;

    flex:
        0
        0
        5px;

    border-radius:
        999px;

    background:
        currentColor;
}

.appointment-status.confirmed {
    border-color:
        #d9e4f8 !important;

    background:
        var(--sh-info-bg) !important;

    color:
        var(--sh-info) !important;
}

.appointment-status.completed {
    border-color:
        #d4ebdf !important;

    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.appointment-status.pending {
    border-color:
        #f0dfb9 !important;

    background:
        var(--sh-warning-bg) !important;

    color:
        var(--sh-warning) !important;
}

.appointment-status.cancelled,
.appointment-status.declined {
    border-color:
        #eed3d3 !important;

    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}


/*
|--------------------------------------------------------------------------
| RESCHEDULES
|--------------------------------------------------------------------------
*/

.reschedule-count {
    width: fit-content;

    display: inline-flex;
    align-items: center;

    gap: 6px;

    padding:
        4px
        7px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 9px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.reschedule-count.has-reschedules {
    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #8c641f;
}

.reschedule-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.reschedule-icon svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.reschedule-count strong {
    font-size: 9px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| ACTION
|--------------------------------------------------------------------------
*/

.table-actions-heading {
    text-align:
        right !important;
}

.appointment-view-button {
    min-height: 34px;

    padding:
        0
        10px;
}

.appointment-view-button svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.9;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.appointments-loading-state {
    min-height: 280px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;
}

.appointments-loading-icon {
    width: 52px;
    height: 52px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 11px;

    border-radius: 15px;

    background:
        var(--sh-surface-muted);
}

.appointments-loading-icon
.spinner {
    margin: 0;
}

.appointments-loading-state h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.appointments-loading-state p {
    max-width: 340px;

    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.appointments-empty-state {
    min-height: 280px;
}

.empty-reset-button {
    margin-top: 13px;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.appointments-pagination {
    background:
        #fbfcfd;
}

.pagination-summary {
    display: flex;
    align-items: center;

    gap: 4px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.pagination-summary strong {
    color:
        var(--sh-text-2);

    font-weight: 800;
}

.pagination-arrow {
    min-width: auto;

    gap: 5px;
}

.pagination-arrow svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.9;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| REFRESH ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        appointment-spin
        0.8s
        linear
        infinite;
}

@keyframes appointment-spin {
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

@media (max-width: 1300px) {
    .appointment-summary-grid {
        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .appointments-filters {
        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .appointment-search {
        grid-column:
            span 2;
    }
}


@media (max-width: 1000px) {
    .appointments-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .appointments-intro-actions {
        width: 100%;
    }

    .appointments-total-card {
        flex: 1;
    }

    .appointment-summary-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .monitoring-banner {
        align-items:
            flex-start;
    }
}


@media (max-width: 760px) {
    .appointments-filters {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .appointment-search {
        grid-column:
            1
            / -1;
    }

    .appointments-filter-actions {
        grid-column:
            1
            / -1;
    }

    .appointments-table-header {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .table-header-actions {
        width: 100%;

        justify-content:
            space-between;
    }
}


@media (max-width: 680px) {
    .appointments-intro {
        padding: 17px;
    }

    .appointments-intro h2 {
        font-size: 19px;
    }

    .appointments-intro-actions {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .appointments-total-card,
    .appointments-refresh-button {
        width: 100%;
    }

    .monitoring-banner {
        flex-wrap: wrap;
    }

    .monitoring-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .monitoring-badge {
        margin-left: 48px;
    }

    .appointment-summary-grid {
        grid-template-columns:
            1fr;
    }

    .filter-card-heading {
        align-items:
            flex-start;
    }

    .filter-heading-left {
        align-items:
            flex-start;
    }

    .appointments-filters {
        display: flex;

        align-items:
            stretch;

        flex-direction:
            column;

        padding: 14px;
    }

    .appointments-filter-actions {
        width: 100%;

        align-items:
            stretch;

        flex-direction:
            column;
    }

    .appointments-filter-actions
    .primary-button,
    .appointments-filter-actions
    .secondary-button {
        width: 100%;
    }

    .appointments-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .appointments-error
    .retry-button {
        width: 100%;
    }

    .appointments-pagination {
        align-items:
            stretch;
    }

    .pagination-summary {
        flex-wrap: wrap;
    }

    .pagination-arrow span {
        display: none;
    }
}
</style>