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

const notifications = ref([]);

const summary = ref({
    total: 0,
    pending: 0,
    processing: 0,
    accepted: 0,
    failed: 0,
    skipped: 0,
    safe_to_retry: 0,
    uncertain_failures: 0,
});

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
    type: '',
    failure_kind: '',
    provider: '',
    user_id: '',
    appointment_id: '',
    date_from: '',
    date_to: '',
    per_page: 20,
});


/*
|--------------------------------------------------------------------------
| FILTER OPTIONS
|--------------------------------------------------------------------------
*/

const statusOptions = [
    {
        value: 'pending',
        label: 'Pending',
    },
    {
        value: 'processing',
        label: 'Processing',
    },
    {
        value: 'accepted',
        label: 'Accepted by Provider',
    },
    {
        value: 'failed',
        label: 'Failed',
    },
    {
        value: 'skipped',
        label: 'Skipped',
    },
];

const typeOptions = [
    {
        value: 'booking_received',
        label: 'Booking Received',
    },
    {
        value: 'appointment_confirmed',
        label: 'Appointment Confirmed',
    },
    {
        value: 'appointment_declined',
        label: 'Appointment Declined',
    },
    {
        value: 'appointment_cancelled',
        label: 'Appointment Cancelled',
    },
    {
        value: 'reschedule_approved',
        label: 'Reschedule Approved',
    },
    {
        value: 'reschedule_rejected',
        label: 'Reschedule Rejected',
    },
    {
        value: 'appointment_reminder',
        label: 'Appointment Reminder',
    },
    {
        value: 'system_test',
        label: 'System Test',
    },
];

const failureOptions = [
    {
        value: 'definite',
        label: 'Definite Failure',
    },
    {
        value: 'uncertain',
        label: 'Uncertain Delivery Outcome',
    },
];

const numberFormatter =
    new Intl.NumberFormat(
        'en-PH',
    );


/*
|--------------------------------------------------------------------------
| HELPERS
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
| FILTER COUNT
|--------------------------------------------------------------------------
*/

const activeFiltersCount =
    computed(() => {
        let count = 0;

        if (
            filters.search.trim()
        ) {
            count += 1;
        }

        if (
            filters.status
        ) {
            count += 1;
        }

        if (
            filters.type
        ) {
            count += 1;
        }

        if (
            filters.failure_kind
        ) {
            count += 1;
        }

        if (
            filters.provider.trim()
        ) {
            count += 1;
        }

        if (
            filters.user_id
        ) {
            count += 1;
        }

        if (
            filters.appointment_id
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
| FILTER VALIDATION
|--------------------------------------------------------------------------
*/

function validateFilters() {
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

        return false;
    }

    return true;
}


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

    const provider =
        filters.provider
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
        filters.type
    ) {
        query.set(
            'type',
            filters.type,
        );
    }

    if (
        filters.failure_kind
    ) {
        query.set(
            'failure_kind',
            filters.failure_kind,
        );
    }

    if (provider) {
        query.set(
            'provider',
            provider,
        );
    }

    if (
        filters.user_id
    ) {
        query.set(
            'user_id',
            String(
                filters.user_id,
            ),
        );
    }

    if (
        filters.appointment_id
    ) {
        query.set(
            'appointment_id',
            String(
                filters.appointment_id,
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
        name:
            'admin.login',
    });
}


/*
|--------------------------------------------------------------------------
| LOAD SUMMARY
|--------------------------------------------------------------------------
*/

async function loadSummary() {
    const response =
        await apiRequest(
            '/admin/sms-notifications/summary',
            {
                token:
                    auth.token,
            },
        );

    summary.value = {
        total:
            Number(
                response?.data
                    ?.summary
                    ?.total
                ?? 0,
            ),

        pending:
            Number(
                response?.data
                    ?.summary
                    ?.pending
                ?? 0,
            ),

        processing:
            Number(
                response?.data
                    ?.summary
                    ?.processing
                ?? 0,
            ),

        accepted:
            Number(
                response?.data
                    ?.summary
                    ?.accepted
                ?? 0,
            ),

        failed:
            Number(
                response?.data
                    ?.summary
                    ?.failed
                ?? 0,
            ),

        skipped:
            Number(
                response?.data
                    ?.summary
                    ?.skipped
                ?? 0,
            ),

        safe_to_retry:
            Number(
                response?.data
                    ?.summary
                    ?.safe_to_retry
                ?? 0,
            ),

        uncertain_failures:
            Number(
                response?.data
                    ?.summary
                    ?.uncertain_failures
                ?? 0,
            ),
    };
}


/*
|--------------------------------------------------------------------------
| LOAD NOTIFICATIONS
|--------------------------------------------------------------------------
*/

async function loadNotifications(
    page = 1,
) {
    const response =
        await apiRequest(
            `/admin/sms-notifications?${buildQuery(page)}`,
            {
                token:
                    auth.token,
            },
        );

    notifications.value =
        Array.isArray(
            response?.data
                ?.sms_notifications,
        )
            ? response.data.sms_notifications
            : [];

    pagination.value = {
        current_page:
            Number(
                response?.data
                    ?.pagination
                    ?.current_page
                ?? 1,
            ),

        per_page:
            Number(
                response?.data
                    ?.pagination
                    ?.per_page
                ?? filters.per_page,
            ),

        total:
            Number(
                response?.data
                    ?.pagination
                    ?.total
                ?? 0,
            ),

        last_page:
            Number(
                response?.data
                    ?.pagination
                    ?.last_page
                ?? 1,
            ),

        has_more_pages:
            Boolean(
                response?.data
                    ?.pagination
                    ?.has_more_pages,
            ),
    };
}


/*
|--------------------------------------------------------------------------
| LOAD PAGE
|--------------------------------------------------------------------------
*/

async function loadPage(
    page = 1,
) {
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
        await Promise.all([
            loadSummary(),

            loadNotifications(
                page,
            ),
        ]);
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
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to load SMS monitoring data.';
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
    await loadPage(
        1,
    );
}

async function resetFilters() {
    filters.search = '';
    filters.status = '';
    filters.type = '';
    filters.failure_kind = '';
    filters.provider = '';
    filters.user_id = '';
    filters.appointment_id = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.per_page = 20;

    await loadPage(
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

    await loadPage(
        page,
    );
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function openNotification(
    notification,
) {
    await router.push({
        name:
            'admin.sms-notification.detail',

        params: {
            smsNotificationId:
                notification.id,
        },
    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadPage();
});
</script>


<template>
    <div class="sms-page">
        <!-- ============================================================
             INTRODUCTION
             ============================================================ -->

        <section class="sms-intro">
            <div class="sms-intro-copy">
                <div class="sms-intro-label">
                    <span class="sms-intro-dot" />

                    Messaging operations
                </div>

                <h2>
                    SMS delivery monitoring
                </h2>

                <p>
                    Monitor StyleHub SMS notifications,
                    provider acceptance, failures, retry
                    eligibility and notification processing
                    across the platform.
                </p>
            </div>


            <div class="sms-intro-actions">
                <div class="sms-total-card">
                    <div class="sms-total-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="4"
                                y="3"
                                width="16"
                                height="18"
                                rx="3"
                            />

                            <path
                                d="M8 7h8M8 11h8M8 15h5"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            SMS records
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    summary.total
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <button
                    type="button"
                    class="secondary-button sms-refresh-button"
                    :disabled="
                        isLoading
                    "
                    @click="
                        loadPage(
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
             OPERATIONS NOTICE
             ============================================================ -->

        <section class="sms-operations-note">
            <div class="sms-operations-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                    />

                    <path
                        d="M12 8v5M12 16h.01"
                    />
                </svg>
            </div>

            <div class="sms-operations-copy">
                <strong>
                    Retry safety matters
                </strong>

                <p>
                    Definite failures may be marked safe to
                    retry. An uncertain failure means the
                    delivery outcome is not known, so it should
                    not be treated the same as a definite
                    failure.
                </p>
            </div>

            <span class="sms-monitoring-badge">
                Operations
            </span>
        </section>


        <!-- ============================================================
             SUMMARY
             ============================================================ -->

        <section class="sms-summary-grid">
            <!-- TOTAL -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon total">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <rect
                            x="4"
                            y="3"
                            width="16"
                            height="18"
                            rx="3"
                        />

                        <path
                            d="M8 7h8M8 11h8M8 15h5"
                        />
                    </svg>
                </div>

                <div class="sms-summary-copy">
                    <span>
                        Total
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.total
                            )
                        }}
                    </strong>

                    <small>
                        SMS records
                    </small>
                </div>
            </article>


            <!-- ACCEPTED -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon accepted">
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

                <div class="sms-summary-copy">
                    <span>
                        Provider accepted
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.accepted
                            )
                        }}
                    </strong>

                    <small>
                        Accepted by provider
                    </small>
                </div>
            </article>


            <!-- PENDING -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon pending">
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

                <div class="sms-summary-copy">
                    <span>
                        Pending
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.pending
                            )
                        }}
                    </strong>

                    <small>
                        Waiting for processing
                    </small>
                </div>
            </article>


            <!-- PROCESSING -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon processing">
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

                <div class="sms-summary-copy">
                    <span>
                        Processing
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.processing
                            )
                        }}
                    </strong>

                    <small>
                        Currently being processed
                    </small>
                </div>
            </article>


            <!-- FAILED -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon failed">
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

                <div class="sms-summary-copy">
                    <span>
                        Failed
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.failed
                            )
                        }}
                    </strong>

                    <small>
                        Failed processing records
                    </small>
                </div>
            </article>


            <!-- SKIPPED -->

            <article class="sms-summary-card">
                <div class="sms-summary-icon skipped">
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

                <div class="sms-summary-copy">
                    <span>
                        Skipped
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.skipped
                            )
                        }}
                    </strong>

                    <small>
                        Intentionally not processed
                    </small>
                </div>
            </article>


            <!-- SAFE TO RETRY -->

            <article class="sms-summary-card retry-card">
                <div class="sms-summary-icon retry">
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

                        <path
                            d="M15 12l2 2 4-4"
                        />
                    </svg>
                </div>

                <div class="sms-summary-copy">
                    <span>
                        Safe to retry
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.safe_to_retry
                            )
                        }}
                    </strong>

                    <small>
                        Definite failures only
                    </small>
                </div>
            </article>


            <!-- UNCERTAIN -->

            <article class="sms-summary-card uncertain-card">
                <div class="sms-summary-icon uncertain">
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

                <div class="sms-summary-copy">
                    <span>
                        Uncertain
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary.uncertain_failures
                            )
                        }}
                    </strong>

                    <small>
                        Unknown delivery outcome
                    </small>
                </div>
            </article>
        </section>


        <!-- ============================================================
             FILTERS
             ============================================================ -->

        <section class="filter-card sms-filter-card">
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
                            Filter SMS records
                        </h3>

                        <p>
                            Search delivery records and narrow
                            results by status, notification type,
                            failure classification or related record.
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
                class="sms-filters"
                @submit.prevent="
                    applyFilters
                "
            >
                <!-- SEARCH -->

                <div class="search-field sms-search-field">
                    <label for="sms-search">
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
                            id="sms-search"
                            v-model="
                                filters.search
                            "
                            type="search"
                            maxlength="100"
                            placeholder="Recipient, user, provider ID or error"
                        >
                    </div>
                </div>


                <!-- STATUS -->

                <div class="filter-field">
                    <label for="sms-status">
                        Status
                    </label>

                    <select
                        id="sms-status"
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


                <!-- TYPE -->

                <div class="filter-field">
                    <label for="sms-type">
                        Notification type
                    </label>

                    <select
                        id="sms-type"
                        v-model="
                            filters.type
                        "
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="
                                option
                                in typeOptions
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


                <!-- FAILURE KIND -->

                <div class="filter-field">
                    <label for="failure-kind">
                        Failure kind
                    </label>

                    <select
                        id="failure-kind"
                        v-model="
                            filters.failure_kind
                        "
                    >
                        <option value="">
                            All failure kinds
                        </option>

                        <option
                            v-for="
                                option
                                in failureOptions
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


                <!-- PROVIDER -->

                <div class="filter-field">
                    <label for="sms-provider">
                        Provider
                    </label>

                    <input
                        id="sms-provider"
                        v-model="
                            filters.provider
                        "
                        type="text"
                        maxlength="32"
                        placeholder="e.g. semaphore"
                    >
                </div>


                <!-- USER -->

                <div class="filter-field">
                    <label for="sms-user-id">
                        User ID
                    </label>

                    <input
                        id="sms-user-id"
                        v-model="
                            filters.user_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>


                <!-- APPOINTMENT -->

                <div class="filter-field">
                    <label for="sms-appointment-id">
                        Appointment ID
                    </label>

                    <input
                        id="sms-appointment-id"
                        v-model="
                            filters.appointment_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>


                <!-- DATE FROM -->

                <div class="filter-field">
                    <label for="sms-date-from">
                        From
                    </label>

                    <input
                        id="sms-date-from"
                        v-model="
                            filters.date_from
                        "
                        type="date"
                    >
                </div>


                <!-- DATE TO -->

                <div class="filter-field">
                    <label for="sms-date-to">
                        To
                    </label>

                    <input
                        id="sms-date-to"
                        v-model="
                            filters.date_to
                        "
                        type="date"
                    >
                </div>


                <!-- ACTIONS -->

                <div class="filter-actions sms-filter-actions">
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
            class="error-message sms-error"
            role="alert"
        >
            <div class="sms-error-icon">
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

            <div class="sms-error-copy">
                <strong>
                    Unable to load SMS monitoring
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
                    loadPage(
                        pagination.current_page
                    )
                "
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             SMS TABLE CARD
             ============================================================ -->

        <section class="sms-card">
            <div class="table-header sms-table-header">
                <div class="table-heading">
                    <div class="table-heading-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="4"
                                y="3"
                                width="16"
                                height="18"
                                rx="3"
                            />

                            <path
                                d="M8 7h8M8 11h8M8 15h5"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3>
                            SMS delivery records
                        </h3>

                        <p>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}
                            record{{
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
                        <label for="sms-per-page">
                            Rows
                        </label>

                        <select
                            id="sms-per-page"
                            v-model.number="
                                filters.per_page
                            "
                            :disabled="
                                isLoading
                            "
                            @change="
                                loadPage(
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
                    notifications.length === 0
                "
                class="sms-loading-state"
            >
                <div class="sms-loading-icon">
                    <div class="spinner" />
                </div>

                <h3>
                    Loading SMS records
                </h3>

                <p>
                    Retrieving notification and
                    delivery monitoring information.
                </p>
            </div>


            <!-- ========================================================
                 EMPTY
                 ======================================================== -->

            <div
                v-else-if="
                    notifications.length === 0
                "
                class="empty-state sms-empty-state"
            >
                <div class="empty-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <rect
                            x="4"
                            y="3"
                            width="16"
                            height="18"
                            rx="3"
                        />

                        <path
                            d="M8 7h8M8 11h8M8 15h5"
                        />
                    </svg>
                </div>

                <h3>
                    No SMS records found
                </h3>

                <p>
                    No delivery records match
                    your current filters.
                </p>

                <button
                    v-if="
                        activeFiltersCount > 0
                    "
                    type="button"
                    class="secondary-button sms-empty-reset"
                    :disabled="
                        isLoading
                    "
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
                class="table-wrapper sms-table-wrapper"
                :class="{
                    'table-refreshing':
                        isLoading,
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th>
                                Notification
                            </th>

                            <th>
                                Recipient
                            </th>

                            <th>
                                User
                            </th>

                            <th>
                                Appointment
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Failure
                            </th>

                            <th>
                                Attempts
                            </th>

                            <th>
                                Created
                            </th>

                            <th class="table-actions-heading">
                                Action
                            </th>
                        </tr>
                    </thead>


                    <tbody>
                        <tr
                            v-for="
                                notification
                                in notifications
                            "
                            :key="
                                notification.id
                            "
                            class="sms-table-row"
                            @dblclick="
                                openNotification(
                                    notification
                                )
                            "
                        >
                            <!-- NOTIFICATION -->

                            <td>
                                <div class="notification-cell">
                                    <div class="notification-icon">
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

                                    <div class="notification-copy">
                                        <strong>
                                            #{{ notification.id }}
                                        </strong>

                                        <span>
                                            {{
                                                notification.type_label
                                                ||
                                                humanize(
                                                    notification.type
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- RECIPIENT -->

                            <td>
                                <div class="recipient-cell">
                                    <div class="recipient-number">
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M7 3h4l2 5-3 2c1.4 3 3.7 5.3 6.7 6.7l2-3 5 2v4c0 1.1-.9 2-2 2C11.4 21.7 2.3 12.6 2.3 2.3 2.3 1.2 3.2.3 4.3.3"
                                            />
                                        </svg>

                                        <strong>
                                            {{
                                                notification.recipient
                                                || 'Unavailable'
                                            }}
                                        </strong>
                                    </div>

                                    <span>
                                        {{
                                            notification.provider
                                            || 'No provider'
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- USER -->

                            <td>
                                <div class="sms-user-cell">
                                    <div
                                        v-if="
                                            notification.user
                                        "
                                        class="sms-user-avatar"
                                    >
                                        {{
                                            (
                                                notification.user
                                                    ?.full_name
                                                ||
                                                notification.user
                                                    ?.email
                                                ||
                                                'U'
                                            )
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div
                                        v-else
                                        class="sms-user-avatar system"
                                    >
                                        S
                                    </div>

                                    <div class="sms-user-copy">
                                        <strong>
                                            {{
                                                notification.user
                                                    ?.full_name
                                                ||
                                                'System / unavailable'
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                notification.user
                                                    ?.email
                                                || '—'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- APPOINTMENT -->

                            <td>
                                <div
                                    v-if="
                                        notification.appointment
                                            ?.id
                                    "
                                    class="sms-appointment-cell"
                                >
                                    <div class="sms-appointment-icon">
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
                                            #{{
                                                notification.appointment
                                                    .id
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                notification.appointment
                                                    ?.shop
                                                    ?.name
                                                ||
                                                'Shop unavailable'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <span
                                    v-else
                                    class="sms-no-appointment"
                                >
                                    —
                                </span>
                            </td>


                            <!-- STATUS -->

                            <td>
                                <span
                                    class="status-badge sms-status-badge"
                                    :class="
                                        statusClass(
                                            notification.status
                                        )
                                    "
                                >
                                    <span class="status-dot" />

                                    {{
                                        notification.status_label
                                        ||
                                        humanize(
                                            notification.status
                                        )
                                    }}
                                </span>
                            </td>


                            <!-- FAILURE -->

                            <td>
                                <div class="failure-cell">
                                    <span
                                        v-if="
                                            notification.failure_kind
                                        "
                                        class="failure-badge"
                                        :class="
                                            statusClass(
                                                notification.failure_kind
                                            )
                                        "
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                v-if="
                                                    notification.failure_kind
                                                    === 'definite'
                                                "
                                                d="M9 9l6 6M15 9l-6 6"
                                            />

                                            <path
                                                v-else
                                                d="M12 8v5M12 17h.01"
                                            />

                                            <circle
                                                v-if="
                                                    notification.failure_kind
                                                    !== 'definite'
                                                "
                                                cx="12"
                                                cy="12"
                                                r="9"
                                            />
                                        </svg>

                                        {{
                                            notification.failure_kind_label
                                            ||
                                            humanize(
                                                notification.failure_kind
                                            )
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="no-failure"
                                    >
                                        —
                                    </span>


                                    <span
                                        v-if="
                                            notification.can_retry
                                        "
                                        class="retry-eligible"
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

                                        Safe to retry
                                    </span>
                                </div>
                            </td>


                            <!-- ATTEMPTS -->

                            <td>
                                <div class="attempts-cell">
                                    <span class="attempts-icon">
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
                                    </span>

                                    <strong>
                                        {{
                                            formatNumber(
                                                notification.attempts
                                                ?? 0
                                            )
                                        }}
                                    </strong>
                                </div>
                            </td>


                            <!-- CREATED -->

                            <td>
                                <div class="created-cell">
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

                                    <span>
                                        {{
                                            formatDateTime(
                                                notification.created_at
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- ACTION -->

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button sms-view-button"
                                    @click="
                                        openNotification(
                                            notification
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
                class="pagination sms-pagination"
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
                        SMS records
                    </span>
                </div>


                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        :disabled="
                            pagination.current_page
                                <= 1
                            ||
                            isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page
                                - 1
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
                                pagination.current_page
                                + 1
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

.sms-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRODUCTION
|--------------------------------------------------------------------------
*/

.sms-intro {
    min-height: 132px;

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
            transparent 27%
        ),
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.sms-intro-copy {
    min-width: 0;

    flex: 1;
}

.sms-intro-label {
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

.sms-intro-dot {
    width: 6px;
    height: 6px;

    border-radius: 999px;

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

.sms-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 22px;
    font-weight: 800;

    line-height: 29px;

    letter-spacing:
        -0.55px;
}

.sms-intro p {
    max-width: 690px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}

.sms-intro-actions {
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

.sms-total-card {
    min-width: 165px;

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

.sms-total-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.sms-total-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-total-card
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.sms-total-card span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 600;
}

.sms-total-card strong {
    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;
}

.sms-refresh-button {
    min-height: 48px;
}

.sms-refresh-button svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| OPERATIONS NOTE
|--------------------------------------------------------------------------
*/

.sms-operations-note {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #f0dfb9;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            var(--sh-warning-bg),
            #fffdf8
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.sms-operations-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;

    color:
        var(--sh-warning);
}

.sms-operations-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-operations-copy {
    min-width: 0;

    flex: 1;
}

.sms-operations-copy strong {
    display: block;

    color:
        #80591f;

    font-size: 9.5px;
}

.sms-operations-copy p {
    max-width: 850px;

    margin:
        3px
        0
        0;

    color:
        #99733b;

    font-size: 8.5px;

    line-height: 14px;
}

.sms-monitoring-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #ead8ad;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-warning);

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.5px;

    text-transform: uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| SUMMARY GRID
|--------------------------------------------------------------------------
*/

.sms-summary-grid {
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

.sms-summary-card {
    min-width: 0;

    min-height: 92px;

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

    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.sms-summary-card:hover {
    transform:
        translateY(
            -1px
        );

    box-shadow:
        var(--sh-shadow-card);
}

.sms-summary-card.retry-card {
    border-color:
        #d4ebdf;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-success-bg)
        );
}

.sms-summary-card.uncertain-card {
    border-color:
        #f0dfb9;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-warning-bg)
        );
}

.sms-summary-icon {
    width: 40px;
    height: 40px;

    flex: 0 0 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.sms-summary-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-summary-icon.total {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.sms-summary-icon.accepted,
.sms-summary-icon.retry {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.sms-summary-icon.pending,
.sms-summary-icon.uncertain {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.sms-summary-icon.processing {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.sms-summary-icon.failed {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.sms-summary-icon.skipped {
    background:
        #f1f3f5;

    color:
        var(--sh-text-3);
}

.sms-summary-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.sms-summary-copy span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.sms-summary-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;

    line-height: 24px;
}

.sms-summary-copy small {
    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 12px;
}


/*
|--------------------------------------------------------------------------
| FILTER PANEL
|--------------------------------------------------------------------------
*/

.sms-filter-card {
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

    flex: 0 0 36px;

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

    stroke-linecap: round;
    stroke-linejoin: round;
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

    border-radius: 999px;

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
| FILTERS
|--------------------------------------------------------------------------
*/

.sms-filters {
    display: grid;

    grid-template-columns:
        minmax(
            250px,
            1.7fr
        )
        repeat(
            3,
            minmax(
                145px,
                1fr
            )
        );

    align-items: end;

    gap: 11px;

    padding:
        15px
        16px
        16px;
}

.sms-search-field {
    flex: none;
}

.sms-filter-actions {
    grid-column:
        span 2;

    align-items: center;
}

.sms-filter-actions
.primary-button,
.sms-filter-actions
.secondary-button {
    min-height: 42px;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.sms-error {
    align-items: center;

    margin: 0;
}

.sms-error-icon {
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

.sms-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-error-copy {
    min-width: 0;

    flex: 1;
}

.sms-error-copy strong {
    display: block;

    font-size: 10px;
}

.sms-error-copy p {
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

.sms-table-header {
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

    flex: 0 0 36px;

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

    stroke-linecap: round;
    stroke-linejoin: round;
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

    border-radius: 999px;

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

.sms-table-wrapper {
    transition:
        opacity
        0.16s ease;
}

.table-refreshing {
    opacity: 0.55;

    pointer-events: none;
}

.sms-table-wrapper table {
    min-width: 1320px;
}

.sms-table-row {
    cursor: default;
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION
|--------------------------------------------------------------------------
*/

.notification-cell {
    min-width: 175px;

    display: flex;
    align-items: center;

    gap: 9px;
}

.notification-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.notification-icon svg {
    width: 16px;
    height: 16px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.notification-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.notification-copy strong {
    color:
        var(--sh-text);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 9.5px;
    font-weight: 800;
}

.notification-copy span {
    max-width: 160px;

    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| RECIPIENT
|--------------------------------------------------------------------------
*/

.recipient-cell {
    min-width: 145px;

    display: flex;
    flex-direction: column;

    gap: 3px;
}

.recipient-number {
    display: flex;
    align-items: center;

    gap: 5px;
}

.recipient-number svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.recipient-number strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
    font-weight: 750;

    white-space: nowrap;
}

.recipient-cell > span {
    padding-left: 17px;

    color:
        var(--sh-text-3);

    font-size: 8px;

    text-transform: capitalize;
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

.sms-user-cell {
    min-width: 185px;

    display: flex;
    align-items: center;

    gap: 8px;
}

.sms-user-avatar {
    width: 32px;
    height: 32px;

    flex: 0 0 32px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);

    font-size: 9px;
    font-weight: 800;
}

.sms-user-avatar.system {
    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.sms-user-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.sms-user-copy strong,
.sms-user-copy span {
    max-width: 165px;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.sms-user-copy strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
}

.sms-user-copy span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT
|--------------------------------------------------------------------------
*/

.sms-appointment-cell {
    min-width: 150px;

    display: flex;
    align-items: center;

    gap: 8px;
}

.sms-appointment-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.sms-appointment-icon svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-appointment-cell
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.sms-appointment-cell strong {
    color:
        var(--sh-text);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 9px;
}

.sms-appointment-cell span {
    max-width: 130px;

    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.sms-no-appointment {
    color:
        #b0b6be;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.sms-status-badge {
    gap: 5px;
}

.status-dot {
    width: 5px;
    height: 5px;

    flex: 0 0 5px;

    border-radius: 999px;

    background:
        currentColor;
}

.sms-status-badge.pending {
    border-color:
        #f0dfb9 !important;

    background:
        var(--sh-warning-bg) !important;

    color:
        var(--sh-warning) !important;
}

.sms-status-badge.processing {
    border-color:
        #d9e4f8 !important;

    background:
        var(--sh-info-bg) !important;

    color:
        var(--sh-info) !important;
}

.sms-status-badge.accepted {
    border-color:
        #d4ebdf !important;

    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.sms-status-badge.failed {
    border-color:
        #eed3d3 !important;

    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}

.sms-status-badge.skipped {
    border-color:
        #e1e5ea !important;

    background:
        #f3f5f7 !important;

    color:
        #6f7884 !important;
}


/*
|--------------------------------------------------------------------------
| FAILURE
|--------------------------------------------------------------------------
*/

.failure-cell {
    min-width: 150px;

    display: flex;
    align-items: flex-start;
    flex-direction: column;

    gap: 5px;
}

.failure-badge {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    gap: 5px;

    padding:
        0
        7px;

    border:
        1px solid
        transparent;

    border-radius: 999px;

    font-size: 7.5px;
    font-weight: 750;

    white-space: nowrap;
}

.failure-badge svg {
    width: 11px;
    height: 11px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.failure-badge.definite {
    border-color:
        #eed3d3;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.failure-badge.uncertain {
    border-color:
        #f0dfb9;

    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.no-failure {
    color:
        #b0b6be;
}

.retry-eligible {
    display: inline-flex;
    align-items: center;

    gap: 4px;

    color:
        var(--sh-success);

    font-size: 7.5px;
    font-weight: 750;
}

.retry-eligible svg {
    width: 11px;
    height: 11px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| ATTEMPTS
|--------------------------------------------------------------------------
*/

.attempts-cell {
    display: inline-flex;
    align-items: center;

    gap: 6px;
}

.attempts-icon {
    width: 27px;
    height: 27px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.attempts-icon svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.attempts-cell strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| CREATED
|--------------------------------------------------------------------------
*/

.created-cell {
    min-width: 145px;

    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-2);
}

.created-cell svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.created-cell span {
    font-size: 8.5px;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| VIEW
|--------------------------------------------------------------------------
*/

.table-actions-heading {
    text-align:
        right !important;
}

.sms-view-button {
    min-height: 34px;

    padding:
        0
        10px;
}

.sms-view-button svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.9;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.sms-loading-state {
    min-height: 280px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;
}

.sms-loading-icon {
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

.sms-loading-icon
.spinner {
    margin: 0;
}

.sms-loading-state h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.sms-loading-state p {
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

.sms-empty-state {
    min-height: 280px;
}

.sms-empty-reset {
    margin-top: 13px;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.sms-pagination {
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

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| REFRESH ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        sms-spin
        0.8s
        linear
        infinite;
}

@keyframes sms-spin {
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

@media (max-width: 1350px) {
    .sms-summary-grid {
        grid-template-columns:
            repeat(
                4,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .sms-filters {
        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .sms-search-field {
        grid-column:
            span 2;
    }

    .sms-filter-actions {
        grid-column:
            span 1;
    }
}


@media (max-width: 1050px) {
    .sms-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .sms-intro-actions {
        width: 100%;
    }

    .sms-total-card {
        flex: 1;
    }

    .sms-summary-grid {
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


@media (max-width: 800px) {
    .sms-filters {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .sms-search-field {
        grid-column:
            1
            / -1;
    }

    .sms-filter-actions {
        grid-column:
            1
            / -1;
    }

    .sms-table-header {
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
    .sms-intro {
        padding: 17px;
    }

    .sms-intro h2 {
        font-size: 19px;
    }

    .sms-intro-actions {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .sms-total-card,
    .sms-refresh-button {
        width: 100%;
    }

    .sms-operations-note {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .sms-operations-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .sms-monitoring-badge {
        margin-left: 48px;
    }

    .sms-summary-grid {
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

    .sms-filters {
        display: flex;

        align-items: stretch;

        flex-direction: column;

        padding: 14px;
    }

    .sms-filter-actions {
        width: 100%;

        align-items: stretch;

        flex-direction: column;
    }

    .sms-filter-actions
    .primary-button,
    .sms-filter-actions
    .secondary-button {
        width: 100%;
    }

    .sms-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .sms-error
    .retry-button {
        width: 100%;
    }

    .sms-pagination {
        align-items: stretch;
    }

    .pagination-summary {
        flex-wrap: wrap;
    }

    .pagination-arrow span {
        display: none;
    }
}
</style>