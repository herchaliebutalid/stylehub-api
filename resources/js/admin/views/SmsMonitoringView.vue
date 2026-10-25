
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const notifications = ref([]);
const isLoading = ref(false);
const errorMessage = ref('');

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

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'accepted', label: 'Accepted by Provider' },
    { value: 'failed', label: 'Failed' },
    { value: 'skipped', label: 'Skipped' },
];

const typeOptions = [
    { value: 'booking_received', label: 'Booking Received' },
    { value: 'appointment_confirmed', label: 'Appointment Confirmed' },
    { value: 'appointment_declined', label: 'Appointment Declined' },
    { value: 'appointment_cancelled', label: 'Appointment Cancelled' },
    { value: 'reschedule_approved', label: 'Reschedule Approved' },
    { value: 'reschedule_rejected', label: 'Reschedule Rejected' },
    { value: 'appointment_reminder', label: 'Appointment Reminder' },
    { value: 'system_test', label: 'System Test' },
];

const failureOptions = [
    { value: 'definite', label: 'Definite Failure' },
    { value: 'uncertain', label: 'Uncertain Delivery Outcome' },
];

const overviewCards = [
    {
        key: 'total',
        title: 'Total SMS',
        caption: 'All notifications',
        icon: 'M5 4h14v16H5z M9 9h6 M9 13h6 M9 17h3',
    },
    {
        key: 'accepted',
        title: 'Provider accepted',
        caption: 'Delivery not guaranteed',
        icon: 'M20 6L9 17l-5-5',
    },
    {
        key: 'pending',
        title: 'Pending',
        caption: 'Awaiting processing',
        icon: 'M12 7v5l3 2 M21 12a9 9 0 1 1-18 0a9 9 0 0 1 18 0',
    },
    {
        key: 'processing',
        title: 'Processing',
        caption: 'Being processed',
        icon: 'M20 7v5h-5 M4 17v-5h5 M5.5 9a8 8 0 0 1 13-2 M18.5 15a8 8 0 0 1-13 2',
    },
    {
        key: 'failed',
        title: 'Failed',
        caption: 'Send attempts failed',
        icon: 'M5 5l14 14 M19 5L5 19',
    },
    {
        key: 'skipped',
        title: 'Skipped',
        caption: 'Not submitted',
        icon: 'M5 12h14',
    },
    {
        key: 'safe_to_retry',
        title: 'Safe to retry',
        caption: 'Definite failures',
        icon: 'M20 11a8 8 0 1 0-2 5 M20 5v6h-6',
    },
    {
        key: 'uncertain_failures',
        title: 'Uncertain',
        caption: 'Outcome unknown',
        icon: 'M12 3l10 18H2L12 3z M12 9v5 M12 17h.01',
    },
];

const numberFormatter = new Intl.NumberFormat('en-PH');

/* ==========================================================
   FORMATTING
   ========================================================== */

function formatNumber(value) {
    const number = Number(value ?? 0);

    return Number.isFinite(number)
        ? numberFormatter.format(number)
        : '0';
}

function humanize(value) {
    if (!value) {
        return '—';
    }

    return String(value)
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (letter) => letter.toUpperCase(),
        );
}

function statusClass(value) {
    return String(value ?? '')
        .toLowerCase()
        .replaceAll('_', '-');
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(date);
}

/* ==========================================================
   SMS PROVIDER DISPLAY
   ========================================================== */

function providerLabel(value) {
    const original = String(value ?? '').trim();
    const provider = original.toLowerCase();

    if (!original) {
        return 'Not recorded';
    }

    if (provider === 'textbee') {
        return 'TextBee';
    }

    if (provider === 'semaphore') {
        return 'Semaphore (legacy label)';
    }

    return original;
}

function isLegacyProvider(value) {
    return (
        String(value ?? '')
            .trim()
            .toLowerCase() === 'semaphore'
    );
}

/* ==========================================================
   ACTIVE FILTERS
   ========================================================== */

const activeFiltersCount = computed(() =>
    [
        filters.search.trim(),
        filters.status,
        filters.type,
        filters.failure_kind,
        filters.provider.trim(),
        filters.user_id,
        filters.appointment_id,
        filters.date_from,
        filters.date_to,
    ].filter(Boolean).length,
);

/* ==========================================================
   PAGINATION
   ========================================================== */

const firstResult = computed(() =>
    pagination.value.total === 0
        ? 0
        : (
            pagination.value.current_page - 1
        ) * pagination.value.per_page + 1,
);

const lastResult = computed(() =>
    Math.min(
        pagination.value.total,
        pagination.value.current_page *
            pagination.value.per_page,
    ),
);

const visiblePages = computed(() => {
    const first = Math.max(
        1,
        pagination.value.current_page - 2,
    );

    const last = Math.min(
        pagination.value.last_page,
        pagination.value.current_page + 2,
    );

    return Array.from(
        {
            length: Math.max(0, last - first + 1),
        },
        (_, index) => first + index,
    );
});

/* ==========================================================
   FILTER VALIDATION
   ========================================================== */

function validateFilters() {
    errorMessage.value = '';

    if (
        filters.date_from &&
        filters.date_to &&
        filters.date_to < filters.date_from
    ) {
        errorMessage.value =
            'The end date must be on or after the start date.';

        return false;
    }

    return true;
}

/* ==========================================================
   QUERY BUILDER
   ========================================================== */

function buildQuery(page) {
    const query = new URLSearchParams();

    for (const key of [
        'search',
        'status',
        'type',
        'failure_kind',
        'provider',
        'user_id',
        'appointment_id',
        'date_from',
        'date_to',
    ]) {
        const value = String(
            filters[key] ?? '',
        ).trim();

        if (value) {
            query.set(key, value);
        }
    }

    query.set('page', String(page));
    query.set('per_page', String(filters.per_page));

    return query.toString();
}

/* ==========================================================
   AUTHENTICATION
   ========================================================== */

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

/* ==========================================================
   LOAD SMS SUMMARY
   ========================================================== */

async function loadSummary() {
    const response = await apiRequest(
        '/admin/sms-notifications/summary',
        {
            token: auth.token,
        },
    );

    const incoming = response?.data?.summary ?? {};
    const next = {};

    for (const key of Object.keys(summary.value)) {
        next[key] = Number(incoming[key] ?? 0);
    }

    summary.value = next;
}

/* ==========================================================
   LOAD SMS NOTIFICATIONS
   ========================================================== */

async function loadNotifications(page) {
    const response = await apiRequest(
        `/admin/sms-notifications?${buildQuery(page)}`,
        {
            token: auth.token,
        },
    );

    notifications.value = Array.isArray(
        response?.data?.sms_notifications,
    )
        ? response.data.sms_notifications
        : [];

    const next = response?.data?.pagination ?? {};

    pagination.value = {
        current_page: Number(next.current_page ?? 1),
        per_page: Number(
            next.per_page ?? filters.per_page,
        ),
        total: Number(next.total ?? 0),
        last_page: Number(next.last_page ?? 1),
        has_more_pages: Boolean(
            next.has_more_pages,
        ),
    };
}

/* ==========================================================
   LOAD PAGE
   ========================================================== */

async function loadPage(page = 1) {
    if (!auth.token) {
        await authenticationFailure();
        return;
    }

    if (!validateFilters()) {
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        await Promise.all([
            loadSummary(),
            loadNotifications(page),
        ]);
    } catch (error) {
        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            await authenticationFailure();
            return;
        }

        errorMessage.value =
            error?.data?.message ??
            error?.message ??
            'Unable to load SMS monitoring data.';
    } finally {
        isLoading.value = false;
    }
}

/* ==========================================================
   FILTER ACTIONS
   ========================================================== */

async function applyFilters() {
    await loadPage(1);
}

async function resetFilters() {
    Object.assign(filters, {
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

    await loadPage(1);
}

/* ==========================================================
   PAGE NAVIGATION
   ========================================================== */

async function changePage(page) {
    if (
        isLoading.value ||
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page
    ) {
        return;
    }

    await loadPage(page);
}

async function openNotification(notification) {
    await router.push({
        name: 'admin.sms-notification.detail',
        params: {
            smsNotificationId: notification.id,
        },
    });
}

onMounted(() => {
    void loadPage();
});
</script>

<template>
    <div class="sms-page">

        <!-- =============================================
             PAGE HEADER
             ============================================= -->

        <section class="sms-intro">
            <div class="sms-intro-copy">
                

                <h2>SMS Monitoring</h2>

                <p>
                    View delivery records, provider responses,
                    failures, and retry eligibility across
                    StyleHub.
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

                            <path d="M8 7h8M8 11h8M8 15h5" />
                        </svg>
                    </div>

                    <div>
                        <span>Total SMS records</span>

                        <strong>
                            {{ formatNumber(summary.total) }}
                        </strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button sms-refresh-button"
                    :disabled="isLoading"
                    @click="
                        loadPage(pagination.current_page)
                    "
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        :class="{
                            spinning: isLoading,
                        }"
                    >
                        <path
                            d="M20 11a8 8 0 10-2.3 5.7"
                        />

                        <path d="M20 5v6h-6" />
                    </svg>

                    {{
                        isLoading
                            ? 'Refreshing...'
                            : 'Refresh'
                    }}
                </button>
            </div>
        </section>

        <!-- =============================================
             TEXTBEE INFORMATION
             ============================================= -->

        <section class="sms-info-banner">
            <div class="sms-info-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path d="M12 11v5M12 8h.01" />
                </svg>
            </div>

            <div>
                <strong>
                    TextBee SMS monitoring
                </strong>

                <p>
                    New SMS records are labeled TextBee.
                    Older records can still show
                    <b>Semaphore (legacy label)</b>;
                    check their actual sending history
                    before interpreting them.

                    "Accepted by Provider" is not proof
                    of handset delivery.

                    Retry only definite failures
                    marked safe to retry.
                </p>
            </div>
        </section>

        <!-- =============================================
             SUMMARY CARDS
             ============================================= -->

        <section
            class="sms-summary-grid"
            aria-label="SMS summary"
        >
            <article
                v-for="card in overviewCards"
                :key="card.key"
                class="sms-summary-card"
            >
                <div
                    class="sms-summary-icon"
                    :class="card.key"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path :d="card.icon" />
                    </svg>
                </div>

                <div class="sms-summary-copy">
                    <span>
                        {{ card.title }}
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                summary[card.key]
                            )
                        }}
                    </strong>

                    <small>
                        {{ card.caption }}
                    </small>
                </div>
            </article>
        </section>

        <!-- =============================================
             FILTERS
             ============================================= -->

        <section class="filter-card sms-filter-card">
            <div class="sms-section-heading">
                <div class="sms-heading-left">
                    <div class="sms-heading-icon">
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
                        <h3>Filter SMS records</h3>

                        <p>
                            Find records by status, type,
                            provider, date, user, or
                            appointment.
                        </p>
                    </div>
                </div>

                <span
                    v-if="activeFiltersCount"
                    class="sms-filter-count"
                >
                    {{ activeFiltersCount }} active
                </span>
            </div>

            <form
                class="sms-filters"
                @submit.prevent="applyFilters"
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

                            <path d="M20 20l-4-4" />
                        </svg>

                        <input
                            id="sms-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="100"
                            placeholder="Recipient, user, SMS ID, or error"
                        />
                    </div>
                </div>

                <!-- STATUS -->

                <div class="filter-field">
                    <label for="sms-status">
                        Status
                    </label>

                    <select
                        id="sms-status"
                        v-model="filters.status"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- NOTIFICATION TYPE -->

                <div class="filter-field">
                    <label for="sms-type">
                        Notification type
                    </label>

                    <select
                        id="sms-type"
                        v-model="filters.type"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- FAILURE KIND -->

                <div class="filter-field">
                    <label for="sms-failure-kind">
                        Failure kind
                    </label>

                    <select
                        id="sms-failure-kind"
                        v-model="filters.failure_kind"
                    >
                        <option value="">
                            All failure kinds
                        </option>

                        <option
                            v-for="option in failureOptions"
                            :key="option.value"
                            :value="option.value"
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
                        v-model="filters.provider"
                        type="text"
                        maxlength="32"
                        placeholder="e.g. textbee"
                    />
                </div>

                <!-- USER ID -->

                <div class="filter-field">
                    <label for="sms-user-id">
                        User ID
                    </label>

                    <input
                        id="sms-user-id"
                        v-model="filters.user_id"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    />
                </div>

                <!-- APPOINTMENT ID -->

                <div class="filter-field">
                    <label for="sms-appointment-id">
                        Appointment ID
                    </label>

                    <input
                        id="sms-appointment-id"
                        v-model="filters.appointment_id"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    />
                </div>

                <!-- DATE FROM -->

                <div class="filter-field">
                    <label for="sms-date-from">
                        Date from
                    </label>

                    <input
                        id="sms-date-from"
                        v-model="filters.date_from"
                        type="date"
                    />
                </div>

                <!-- DATE TO -->

                <div class="filter-field">
                    <label for="sms-date-to">
                        Date to
                    </label>

                    <input
                        id="sms-date-to"
                        v-model="filters.date_to"
                        type="date"
                    />
                </div>

                <!-- FILTER ACTIONS -->

                <div class="sms-filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="isLoading"
                    >
                        Apply filters
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="
                            isLoading ||
                            activeFiltersCount === 0
                        "
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <!-- =============================================
             ERROR STATE
             ============================================= -->

        <div
            v-if="errorMessage"
            class="error-message sms-error"
            role="alert"
        >
            <div class="sms-error-copy">
                <strong>
                    SMS monitoring needs attention
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="
                    loadPage(pagination.current_page)
                "
            >
                Try again
            </button>
        </div>

        <!-- =============================================
             SMS RECORDS
             ============================================= -->

        <section class="sms-card">
            <div class="sms-table-heading">
                <div class="sms-heading-left">
                    <div class="sms-heading-icon">
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

                <div class="sms-table-actions">
                    <span
                        v-if="activeFiltersCount"
                        class="sms-filter-count"
                    >
                        Filtered
                    </span>

                    <div class="per-page sms-per-page">
                        <label for="sms-per-page">
                            Rows
                        </label>

                        <select
                            id="sms-per-page"
                            v-model.number="filters.per_page"
                            :disabled="isLoading"
                            @change="loadPage(1)"
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

            <!-- =========================================
                 LOADING
                 ========================================= -->

            <div
                v-if="
                    isLoading &&
                    notifications.length === 0
                "
                class="sms-empty"
            >
                <div class="sms-spinner"></div>

                <strong>
                    Loading SMS records...
                </strong>

                <p>
                    Please wait while StyleHub retrieves
                    the latest records.
                </p>
            </div>

            <!-- =========================================
                 EMPTY STATE
                 ========================================= -->

            <div
                v-else-if="notifications.length === 0"
                class="sms-empty"
            >
                <div class="sms-empty-icon">
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

                <strong>
                    No SMS records found
                </strong>

                <p>
                    There are no SMS records matching
                    your selected filters.
                </p>

                <button
                    v-if="activeFiltersCount"
                    type="button"
                    class="secondary-button"
                    :disabled="isLoading"
                    @click="resetFilters"
                >
                    Clear filters
                </button>
            </div>

            <!-- =========================================
                 SMS TABLE
                 ========================================= -->

            <div
                v-else
                class="table-wrapper sms-table-wrapper"
                :class="{
                    'sms-refreshing': isLoading,
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th>Notification</th>
                            <th>Recipient / provider</th>
                            <th>User</th>
                            <th>Appointment</th>
                            <th>Status</th>
                            <th>Failure</th>
                            <th>Attempts</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="notification in notifications"
                            :key="notification.id"
                        >
                            <!-- NOTIFICATION -->

                            <td>
                                <div class="sms-cell-with-icon">
                                    <span class="sms-record-icon">
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M4 5h16v12H9l-5 4V5z"
                                            />

                                            <path d="M8 9h8M8 13h5" />
                                        </svg>
                                    </span>

                                    <div class="sms-stacked">
                                        <strong>
                                            #{{ notification.id }}
                                        </strong>

                                        <span>
                                            {{
                                                notification.type_label ||
                                                humanize(
                                                    notification.type
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- RECIPIENT AND PROVIDER -->

                            <td>
                                <div class="sms-stacked">
                                    <strong>
                                        {{
                                            notification.recipient ||
                                            'Unavailable'
                                        }}
                                    </strong>

                                    <span
                                        :class="{
                                            'sms-legacy-provider':
                                                isLegacyProvider(
                                                    notification.provider
                                                ),
                                        }"
                                    >
                                        {{
                                            providerLabel(
                                                notification.provider
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>

                            <!-- USER -->

                            <td>
                                <div class="sms-cell-with-icon">
                                    <span class="sms-avatar">
                                        {{
                                            String(
                                                notification.user?.full_name ||
                                                notification.user?.email ||
                                                'S'
                                            )
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </span>

                                    <div class="sms-stacked">
                                        <strong>
                                            {{
                                                notification.user?.full_name ||
                                                'System / unavailable'
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                notification.user?.email ||
                                                '—'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- APPOINTMENT -->

                            <td>
                                <div
                                    v-if="notification.appointment?.id"
                                    class="sms-stacked"
                                >
                                    <strong>
                                        #{{ notification.appointment.id }}
                                    </strong>

                                    <span>
                                        {{
                                            notification.appointment
                                                ?.shop?.name ||
                                            'Shop unavailable'
                                        }}
                                    </span>
                                </div>

                                <span v-else>
                                    —
                                </span>
                            </td>

                            <!-- STATUS -->

                            <td>
                                <span
                                    class="status-badge sms-status"
                                    :class="
                                        statusClass(
                                            notification.status
                                        )
                                    "
                                >
                                    <span class="sms-status-dot"></span>

                                    {{
                                        notification.status_label ||
                                        humanize(
                                            notification.status
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- FAILURE -->

                            <td>
                                <div class="sms-failure">
                                    <span
                                        v-if="notification.failure_kind"
                                        class="sms-failure-badge"
                                        :class="
                                            statusClass(
                                                notification.failure_kind
                                            )
                                        "
                                    >
                                        {{
                                            notification.failure_kind_label ||
                                            humanize(
                                                notification.failure_kind
                                            )
                                        }}
                                    </span>

                                    <span v-else>
                                        —
                                    </span>

                                    <span
                                        v-if="notification.can_retry"
                                        class="sms-retry-badge"
                                    >
                                        Safe to retry
                                    </span>
                                </div>
                            </td>

                            <!-- ATTEMPTS -->

                            <td>
                                <strong>
                                    {{
                                        formatNumber(
                                            notification.attempts ?? 0
                                        )
                                    }}
                                </strong>
                            </td>

                            <!-- CREATED -->

                            <td>
                                <span class="sms-date">
                                    {{
                                        formatDateTime(
                                            notification.created_at
                                        )
                                    }}
                                </span>
                            </td>

                            <!-- ACTION -->

                            <td>
                                <button
                                    type="button"
                                    class="view-button sms-view"
                                    @click="
                                        openNotification(
                                            notification
                                        )
                                    "
                                >
                                    View

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

            <!-- =========================================
                 PAGINATION
                 ========================================= -->

            <div
                v-if="pagination.total > 0"
                class="pagination sms-pagination"
            >
                <div class="sms-pagination-summary">
                    Showing
                    <strong>{{ firstResult }}</strong>
                    –
                    <strong>{{ lastResult }}</strong>
                    of
                    <strong>
                        {{ formatNumber(pagination.total) }}
                    </strong>
                    SMS records
                </div>

                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button"
                        :disabled="
                            isLoading ||
                            pagination.current_page <= 1
                        "
                        @click="
                            changePage(
                                pagination.current_page - 1
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        class="page-button"
                        :class="{
                            active:
                                page === pagination.current_page,
                        }"
                        :disabled="isLoading"
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        class="page-button"
                        :disabled="
                            isLoading ||
                            pagination.current_page >=
                                pagination.last_page
                        "
                        @click="
                            changePage(
                                pagination.current_page + 1
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
/* ==========================================================
   PAGE LAYOUT
   ========================================================== */

.sms-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ==========================================================
   PAGE HEADER
   ========================================================== */

.sms-intro {
    min-height: 126px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 20px 22px;
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-xl);
    background:
        radial-gradient(
            circle at 94% 10%,
            rgba(214,154,45,.07),
            transparent 26%
        ),
        var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.sms-intro-copy {
    min-width: 0;
    flex: 1;
}

.sms-intro-label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 5px;
    color: var(--sh-accent);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.1px;
    text-transform: uppercase;
}

.sms-intro-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sh-accent);
    box-shadow: 0 0 0 4px rgba(214,154,45,.08);
}

.sms-intro h2 {
    margin: 0;
    color: var(--sh-text);
    font-size: 22px;
    line-height: 29px;
    font-weight: 800;
    letter-spacing: -.55px;
}

.sms-intro p {
    max-width: 650px;
    margin: 5px 0 0;
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 17px;
}

.sms-intro-actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ==========================================================
   TOTAL RECORD COUNT
   ========================================================== */

.sms-total-card {
    min-width: 162px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--sh-border);
    border-radius: 12px;
    background: var(--sh-surface-muted);
}

.sms-total-icon,
.sms-heading-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sms-total-icon svg,
.sms-heading-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-total-card > div:last-child {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.sms-total-card span {
    color: var(--sh-text-3);
    font-size: 9px;
}

.sms-total-card strong {
    color: var(--sh-text);
    font-size: 18px;
    font-weight: 800;
}

.sms-refresh-button {
    min-height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

.sms-refresh-button svg {
    width: 16px;
    height: 16px;
}

/* ==========================================================
   TEXTBEE INFORMATION
   ========================================================== */

.sms-info-banner {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 13px 15px;
    border: 1px solid #d9e7f2;
    border-radius: 12px;
    background: #f3f7fb;
}

.sms-info-icon {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #ffffff;
    color: var(--sh-info);
}

.sms-info-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-info-banner > div:last-child {
    min-width: 0;
    flex: 1;
}

.sms-info-banner strong {
    color: #38546c;
    font-size: 11px;
}

.sms-info-banner p {
    margin: 4px 0 0;
    color: #627b91;
    font-size: 10px;
    line-height: 16px;
}

/* ==========================================================
   SMS STATISTICS
   ========================================================== */

.sms-summary-grid {
    display: grid;
    grid-template-columns: repeat(
        4,
        minmax(0, 1fr)
    );
    gap: 12px;
}

.sms-summary-card {
    min-width: 0;
    min-height: 102px;
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 14px;
    border: 1px solid var(--sh-border);
    border-radius: 14px;
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.sms-summary-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sms-summary-icon svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-summary-icon.accepted,
.sms-summary-icon.safe_to_retry {
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.sms-summary-icon.pending,
.sms-summary-icon.uncertain_failures {
    background: var(--sh-warning-bg);
    color: var(--sh-warning);
}

.sms-summary-icon.processing {
    background: var(--sh-info-bg);
    color: var(--sh-info);
}

.sms-summary-icon.failed {
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
}

.sms-summary-icon.skipped {
    background: var(--sh-surface-muted);
    color: var(--sh-text-3);
}

.sms-summary-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.sms-summary-copy span {
    color: var(--sh-text-3);
    font-size: 9px;
    font-weight: 700;
}

.sms-summary-copy strong {
    color: var(--sh-text);
    font-size: 23px;
    line-height: 27px;
    font-weight: 800;
}

.sms-summary-copy small {
    color: var(--sh-text-3);
    font-size: 9px;
    line-height: 13px;
}

/* ==========================================================
   FILTER CARD
   ========================================================== */

.sms-filter-card {
    padding: 18px;
    margin-bottom: 0;
}

.sms-section-heading,
.sms-table-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.sms-section-heading {
    margin-bottom: 16px;
}

.sms-heading-left {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
}

.sms-section-heading h3,
.sms-table-heading h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 14px;
    font-weight: 800;
}

.sms-section-heading p,
.sms-table-heading p {
    margin: 3px 0 0;
    color: var(--sh-text-3);
    font-size: 10px;
    line-height: 15px;
}

.sms-filter-count {
    display: inline-flex;
    align-items: center;
    min-height: 25px;
    padding: 0 10px;
    background: var(--sh-accent-soft);
    border-radius: 999px;
    color: var(--sh-accent);
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}

.sms-filters {
    display: grid;
    grid-template-columns: repeat(
        4,
        minmax(0, 1fr)
    );
    gap: 12px;
    align-items: end;
}

.sms-search-field {
    grid-column: span 2;
}

.sms-filter-actions {
    grid-column: span 2;
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.sms-filter-actions button {
    min-height: 42px;
}

/* ==========================================================
   ERROR HANDLING
   ========================================================== */

.sms-error {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sms-error-copy {
    min-width: 0;
    flex: 1;
}

.sms-error-copy strong {
    display: block;
}

.sms-error-copy p {
    margin: 3px 0 0;
}

/* ==========================================================
   TABLE CARD
   ========================================================== */

.sms-card {
    overflow: hidden;
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-xl);
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.sms-table-heading {
    padding: 16px 18px;
    border-bottom: 1px solid var(--sh-border);
}

.sms-table-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sms-per-page {
    display: flex;
    align-items: center;
    gap: 7px;
}

.sms-per-page label {
    margin: 0;
}

.sms-per-page select {
    min-width: 72px;
}

/* ==========================================================
   TABLE CONTENT
   ========================================================== */

.sms-table-wrapper {
    overflow-x: auto;
}

.sms-table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

.sms-table-wrapper th,
.sms-table-wrapper td {
    padding: 12px 13px;
}

.sms-table-wrapper tbody tr:hover {
    background: #fafbfc;
}

.sms-refreshing {
    opacity: .65;
}

/* ==========================================================
   TABLE CELLS
   ========================================================== */

.sms-cell-with-icon {
    display: flex;
    align-items: center;
    gap: 9px;
}

.sms-record-icon,
.sms-avatar {
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--sh-info-bg);
    color: var(--sh-info);
}

.sms-record-icon svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-avatar {
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
    font-weight: 800;
    font-size: 12px;
}

.sms-stacked {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.sms-stacked strong {
    max-width: 190px;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--sh-text);
    font-size: 10px;
}

.sms-stacked span {
    max-width: 190px;
    overflow: hidden;
    text-overflow: ellipsis;
    color: var(--sh-text-3);
    font-size: 9px;
}

.sms-stacked .sms-legacy-provider {
    color: #956523;
    font-weight: 700;
}

/* ==========================================================
   STATUS AND FAILURE
   ========================================================== */

.sms-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.sms-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.sms-failure {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    gap: 5px;
}

.sms-failure-badge,
.sms-retry-badge {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    border-radius: 999px;
    padding: 0 7px;
    font-size: 9px;
    font-weight: 700;
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
}

.sms-failure-badge.uncertain {
    background: var(--sh-warning-bg);
    color: var(--sh-warning);
}

.sms-retry-badge {
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.sms-date {
    font-size: 10px;
    color: var(--sh-text-3);
}

.sms-view {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.sms-view svg {
    width: 13px;
    height: 13px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* ==========================================================
   LOADING AND EMPTY STATES
   ========================================================== */

.sms-empty {
    min-height: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 35px;
    text-align: center;
}

.sms-empty strong {
    color: var(--sh-text);
    font-size: 14px;
}

.sms-empty p {
    margin: 0;
    color: var(--sh-text-3);
    font-size: 10px;
}

.sms-empty-icon {
    width: 52px;
    height: 52px;
    display: grid;
    place-items: center;
    border-radius: 15px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sms-empty-icon svg {
    width: 23px;
    height: 23px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sms-spinner {
    width: 28px;
    height: 28px;
    border: 3px solid var(--sh-border);
    border-top-color: var(--sh-accent);
    border-radius: 50%;
    animation: sms-rotate .8s linear infinite;
}

@keyframes sms-rotate {
    to {
        transform: rotate(360deg);
    }
}

.spinning {
    animation: sms-rotate .8s linear infinite;
}

/* ==========================================================
   PAGINATION
   ========================================================== */

.sms-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 18px;
    border-top: 1px solid var(--sh-border);
}

.sms-pagination-summary {
    color: var(--sh-text-3);
    font-size: 10px;
}

.sms-pagination-summary strong {
    color: var(--sh-text);
}

/* ==========================================================
   RESPONSIVE LAYOUT
   ========================================================== */

@media (max-width: 1100px) {
    .sms-summary-grid {
        grid-template-columns: repeat(
            2,
            minmax(0, 1fr)
        );
    }

    .sms-filters {
        grid-template-columns: repeat(
            3,
            minmax(0, 1fr)
        );
    }
}

@media (max-width: 720px) {
    .sms-intro {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }

    .sms-filters {
        grid-template-columns: repeat(
            2,
            minmax(0, 1fr)
        );
    }

    .sms-search-field,
    .sms-filter-actions {
        grid-column: span 2;
    }

    .sms-pagination {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .sms-summary-grid,
    .sms-filters {
        grid-template-columns: 1fr;
    }

    .sms-search-field,
    .sms-filter-actions {
        grid-column: span 1;
    }

    .sms-intro-actions {
        flex-wrap: wrap;
    }
}
</style>
