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

const auditLogs = ref([]);
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
    event: '',
    actor_id: '',
    actor_role: '',
    resource_type: '',
    date_from: '',
    date_to: '',
    per_page: 20,
});

const eventOptions = [
    { value: 'shop_approved', label: 'Shop Approved' },
    { value: 'shop_rejected', label: 'Shop Rejected' },
    { value: 'shop_availability_updated', label: 'Shop Availability Updated' },
    { value: 'shop_booking_rules_updated', label: 'Shop Booking Rules Updated' },
    { value: 'shop_suspended', label: 'Shop Suspended' },
    { value: 'shop_reactivated', label: 'Shop Reactivated' },

    { value: 'user_suspended', label: 'User Suspended' },
    { value: 'user_reactivated', label: 'User Reactivated' },

    { value: 'admin_account_updated', label: 'Admin Account Updated' },
    { value: 'admin_password_changed', label: 'Admin Password Changed' },

    { value: 'service_created', label: 'Service Created' },
    { value: 'service_updated', label: 'Service Updated' },
    { value: 'service_deleted', label: 'Service Deleted' },

    { value: 'shop_schedule_updated', label: 'Shop Schedule Updated' },

    { value: 'appointment_booked', label: 'Appointment Booked' },
    { value: 'appointment_confirmed', label: 'Appointment Confirmed' },
    { value: 'appointment_declined', label: 'Appointment Declined' },
    { value: 'appointment_cancelled', label: 'Appointment Cancelled' },
    { value: 'appointment_completed', label: 'Appointment Completed' },
    { value: 'appointment_no_show', label: 'Appointment No-show' },

    { value: 'reschedule_requested', label: 'Reschedule Requested' },
    { value: 'reschedule_approved', label: 'Reschedule Approved' },
    { value: 'reschedule_rejected', label: 'Reschedule Rejected' },
    { value: 'reschedule_withdrawn', label: 'Reschedule Withdrawn' },

    { value: 'promo_created', label: 'Promotion Created' },
    { value: 'promo_updated', label: 'Promotion Updated' },
    { value: 'promo_deleted', label: 'Promotion Deleted' },

    { value: 'feedback_submitted', label: 'Feedback Submitted' },

    { value: 'sms_retry_queued', label: 'SMS Retry Queued' },
    { value: 'sms_template_updated', label: 'SMS Template Updated' },
];

const roleOptions = [
    {
        value: 'admin',
        label: 'Administrator',
    },
    {
        value: 'customer',
        label: 'Customer',
    },
    {
        value: 'shop_owner',
        label: 'Shop Owner',
    },
];

const resourceOptions = [
    { value: 'user', label: 'User' },
    { value: 'shop', label: 'Shop' },
    { value: 'service', label: 'Service' },
    { value: 'shop_schedule', label: 'Shop Schedule' },
    { value: 'appointment', label: 'Appointment' },
    { value: 'reschedule_request', label: 'Reschedule Request' },
    { value: 'promo', label: 'Promotion' },
    { value: 'feedback', label: 'Feedback' },
    { value: 'sms_notification', label: 'SMS Notification' },
    { value: 'sms_template', label: 'SMS Template' },
];


/*
|--------------------------------------------------------------------------
| FILTER SUMMARY
|--------------------------------------------------------------------------
*/

const activeFiltersCount = computed(() => {
    let count = 0;

    if (filters.event) {
        count += 1;
    }

    if (filters.actor_id) {
        count += 1;
    }

    if (filters.actor_role) {
        count += 1;
    }

    if (filters.resource_type) {
        count += 1;
    }

    if (filters.date_from) {
        count += 1;
    }

    if (filters.date_to) {
        count += 1;
    }

    return count;
});


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const firstResult = computed(() => {
    if (
        pagination.value.total === 0
    ) {
        return 0;
    }

    return (
        (
            pagination.value.current_page
            - 1
        )
        * pagination.value.per_page
    ) + 1;
});

const lastResult = computed(() =>
    Math.min(
        pagination.value.total,

        pagination.value.current_page
        * pagination.value.per_page,
    ),
);

const visiblePages = computed(() => {
    const current =
        pagination.value.current_page;

    const last =
        pagination.value.last_page;

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
        pages.push(page);
    }

    return pages;
});


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function humanize(value) {
    if (!value) {
        return '—';
    }

    return String(value)
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (character) =>
                character.toUpperCase(),
        );
}

function formatNumber(value) {
    const number =
        Number(
            value ?? 0,
        );

    if (
        !Number.isFinite(number)
    ) {
        return '0';
    }

    return new Intl.NumberFormat(
        'en-PH',
    ).format(number);
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
            second: '2-digit',
            timeZone: 'Asia/Manila',
        },
    ).format(date);
}

function metadataSummary(metadata) {
    if (
        !metadata
        ||
        typeof metadata !== 'object'
        ||
        Array.isArray(metadata)
    ) {
        return 'No metadata';
    }

    const keys =
        Object.keys(metadata);

    if (
        keys.length === 0
    ) {
        return 'No metadata';
    }

    return keys
        .slice(0, 3)
        .map(humanize)
        .join(', ');
}

function actorRoleClass(value) {
    return String(
        value ?? '',
    )
        .toLowerCase()
        .replaceAll(
            '_',
            '-',
        );
}

function eventTone(value) {
    const event =
        String(
            value ?? '',
        );

    if (
        event.includes('approved')
        ||
        event.includes('reactivated')
        ||
        event.includes('completed')
        ||
        event.includes('confirmed')
        ||
        event.includes('created')
        ||
        event.includes('submitted')
    ) {
        return 'positive';
    }

    if (
        event.includes('rejected')
        ||
        event.includes('suspended')
        ||
        event.includes('deleted')
        ||
        event.includes('declined')
        ||
        event.includes('cancelled')
        ||
        event.includes('no_show')
    ) {
        return 'negative';
    }

    if (
        event.includes('updated')
        ||
        event.includes('changed')
        ||
        event.includes('requested')
        ||
        event.includes('retry')
    ) {
        return 'warning';
    }

    return 'neutral';
}


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

function buildQuery(page) {
    const query =
        new URLSearchParams();

    for (
        const field of [
            'event',
            'actor_id',
            'actor_role',
            'resource_type',
            'date_from',
            'date_to',
        ]
    ) {
        if (
            filters[field]
        ) {
            query.set(
                field,
                String(
                    filters[field],
                ),
            );
        }
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
| LOAD AUDIT LOGS
|--------------------------------------------------------------------------
*/

async function loadAuditLogs(
    page = 1,
) {
    if (!auth.token) {
        await authenticationFailure();

        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response =
            await apiRequest(
                `/admin/audit-logs?${buildQuery(page)}`,
                {
                    token:
                        auth.token,
                },
            );

        auditLogs.value =
            Array.isArray(
                response?.data
                    ?.audit_logs,
            )
                ? response.data
                    .audit_logs
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
    } catch (error) {
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
            ?? error?.message
            ?? 'Unable to load audit logs.';
    } finally {
        isLoading.value = false;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER ACTIONS
|--------------------------------------------------------------------------
*/

async function applyFilters() {
    if (
        filters.date_from
        &&
        filters.date_to
        &&
        filters.date_to
            < filters.date_from
    ) {
        errorMessage.value =
            'The end date must be on or after the start date.';

        return;
    }

    await loadAuditLogs(1);
}

async function resetFilters() {
    Object.assign(
        filters,
        {
            event: '',
            actor_id: '',
            actor_role: '',
            resource_type: '',
            date_from: '',
            date_to: '',
            per_page: 20,
        },
    );

    await loadAuditLogs(1);
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
            > pagination.value.last_page
        ||
        page
            === pagination.value.current_page
        ||
        isLoading.value
    ) {
        return;
    }

    await loadAuditLogs(page);
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function openAuditLog(
    auditLog,
) {
    await router.push({
        name:
            'admin.audit-log.detail',

        params: {
            auditLogId:
                auditLog.id,
        },
    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadAuditLogs();
});
</script>


<template>
    <div class="audit-page">
        <!-- ============================================================
             INTRODUCTION
             ============================================================ -->

        <section class="audit-intro">
            <div class="audit-intro-copy">
                

                <h2>
                    Audit logs
                </h2>

                <p>
                    Review immutable operational and security
                    events recorded across the StyleHub platform.
                </p>
            </div>


            <div class="audit-intro-actions">
                <div class="audit-total-card">
                    <div class="audit-total-icon">
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

                            <path
                                d="M9 11h6M9 15h6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Recorded events
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
                    class="secondary-button audit-refresh-button"
                    :disabled="
                        isLoading
                    "
                    @click="
                        loadAuditLogs(
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
             SECURITY NOTICE
             ============================================================ -->

        <section class="audit-security-banner">
            <div class="audit-security-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                    />

                    <path
                        d="M8 12l3 3 5-6"
                    />
                </svg>
            </div>

            <div class="audit-security-copy">
                <strong>
                    Immutable Administrator history
                </strong>

                <p>
                    Administrator account changes, SMS template
                    updates and other sensitive actions are recorded
                    without storing passwords, provider credentials
                    or SMS template contents in audit metadata.
                </p>
            </div>

            <span class="audit-security-badge">
                Security log
            </span>
        </section>


        <!-- ============================================================
             FILTERS
             ============================================================ -->

        <section class="filter-card audit-filter-card">
            <div class="filter-card-heading">
                <div class="filter-heading-main">
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
                            Filter audit history
                        </h3>

                        <p>
                            Narrow the audit trail by event,
                            actor, resource or recording date.
                        </p>
                    </div>
                </div>


                <span
                    v-if="
                        activeFiltersCount > 0
                    "
                    class="active-filter-count"
                >
                    {{ activeFiltersCount }}
                    active
                </span>
            </div>


            <form
                class="audit-filters"
                @submit.prevent="
                    applyFilters
                "
            >
                <!-- EVENT -->

                <label class="filter-field">
                    <span>
                        Event
                    </span>

                    <select
                        v-model="
                            filters.event
                        "
                    >
                        <option value="">
                            All events
                        </option>

                        <option
                            v-for="
                                option
                                in eventOptions
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
                </label>


                <!-- ACTOR ROLE -->

                <label class="filter-field">
                    <span>
                        Actor role
                    </span>

                    <select
                        v-model="
                            filters.actor_role
                        "
                    >
                        <option value="">
                            All roles
                        </option>

                        <option
                            v-for="
                                option
                                in roleOptions
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
                </label>


                <!-- ACTOR ID -->

                <label class="filter-field">
                    <span>
                        Actor ID
                    </span>

                    <input
                        v-model="
                            filters.actor_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </label>


                <!-- RESOURCE -->

                <label class="filter-field">
                    <span>
                        Resource
                    </span>

                    <select
                        v-model="
                            filters.resource_type
                        "
                    >
                        <option value="">
                            All resources
                        </option>

                        <option
                            v-for="
                                option
                                in resourceOptions
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
                </label>


                <!-- DATE FROM -->

                <label class="filter-field">
                    <span>
                        Date from
                    </span>

                    <input
                        v-model="
                            filters.date_from
                        "
                        type="date"
                    >
                </label>


                <!-- DATE TO -->

                <label class="filter-field">
                    <span>
                        Date to
                    </span>

                    <input
                        v-model="
                            filters.date_to
                        "
                        type="date"
                    >
                </label>


                <!-- ACTIONS -->

                <div class="filter-actions audit-filter-actions">
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
            class="error-message audit-error"
            role="alert"
        >
            <div class="audit-error-icon">
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

            <div class="audit-error-copy">
                <strong>
                    Unable to load audit logs
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
                    loadAuditLogs(
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

        <section class="audit-card">
            <div class="table-header audit-table-header">
                <div class="table-heading">
                    <div class="table-heading-icon">
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

                            <path
                                d="M9 11h6M9 15h6"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3>
                            Recorded events
                        </h3>

                        <p>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}
                            audit entr{{
                                pagination.total === 1
                                    ? 'y'
                                    : 'ies'
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


                    <label class="per-page">
                        <span>
                            Rows
                        </span>

                        <select
                            v-model.number="
                                filters.per_page
                            "
                            :disabled="
                                isLoading
                            "
                            @change="
                                loadAuditLogs(1)
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
                    </label>
                </div>
            </div>


            <!-- ========================================================
                 LOADING
                 ======================================================== -->

            <div
                v-if="
                    isLoading
                    &&
                    auditLogs.length === 0
                "
                class="audit-loading-state"
            >
                <div class="audit-loading-icon">
                    <div class="spinner" />
                </div>

                <h3>
                    Loading audit history
                </h3>

                <p>
                    Retrieving StyleHub security and
                    operational audit records.
                </p>
            </div>


            <!-- ========================================================
                 EMPTY
                 ======================================================== -->

            <div
                v-else-if="
                    auditLogs.length === 0
                "
                class="empty-state audit-empty-state"
            >
                <div class="empty-icon">
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

                        <path
                            d="M9 11h6M9 15h6"
                        />
                    </svg>
                </div>

                <h3>
                    No audit logs found
                </h3>

                <p>
                    No audit records match the
                    selected filters.
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
                class="table-wrapper audit-table-wrapper"
                :class="{
                    'table-refreshing':
                        isLoading,
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th>
                                Event
                            </th>

                            <th>
                                Actor
                            </th>

                            <th>
                                Resource
                            </th>

                            <th>
                                Metadata
                            </th>

                            <th>
                                IP Address
                            </th>

                            <th>
                                Recorded
                            </th>

                            <th class="table-actions-heading">
                                Action
                            </th>
                        </tr>
                    </thead>


                    <tbody>
                        <tr
                            v-for="
                                auditLog
                                in auditLogs
                            "
                            :key="
                                auditLog.id
                            "
                            class="audit-table-row"
                            @dblclick="
                                openAuditLog(
                                    auditLog
                                )
                            "
                        >
                            <!-- EVENT -->

                            <td>
                                <div class="audit-event-cell">
                                    <div
                                        class="event-icon"
                                        :class="
                                            eventTone(
                                                auditLog.event
                                                    ?.value
                                            )
                                        "
                                    >
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

                                            <path
                                                d="M9 12h6M9 16h4"
                                            />
                                        </svg>
                                    </div>


                                    <div class="event-copy">
                                        <strong>
                                            {{
                                                auditLog.event
                                                    ?.label
                                                ||
                                                humanize(
                                                    auditLog.event
                                                        ?.value
                                                )
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                auditLog.event
                                                    ?.value
                                                || '—'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- ACTOR -->

                            <td>
                                <div class="actor-cell">
                                    <div
                                        class="actor-avatar"
                                        :class="
                                            actorRoleClass(
                                                auditLog.actor
                                                    ?.role
                                            )
                                        "
                                    >
                                        {{
                                            auditLog.actor
                                                ?.role
                                                ? String(
                                                    humanize(
                                                        auditLog.actor
                                                            .role
                                                    )
                                                )
                                                    .charAt(0)
                                                    .toUpperCase()
                                                : 'S'
                                        }}
                                    </div>

                                    <div class="actor-copy">
                                        <strong>
                                            {{
                                                humanize(
                                                    auditLog.actor
                                                        ?.role
                                                )
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                auditLog.actor
                                                    ?.id
                                                    ? `User #${auditLog.actor.id}`
                                                    : 'System / unavailable'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- RESOURCE -->

                            <td>
                                <div class="resource-cell">
                                    <div class="resource-icon">
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M5 4h14v16H5V4z"
                                            />

                                            <path
                                                d="M9 8h6M9 12h6M9 16h4"
                                            />
                                        </svg>
                                    </div>

                                    <div class="resource-copy">
                                        <strong>
                                            {{
                                                auditLog.resource
                                                    ?.type_label
                                                ||
                                                humanize(
                                                    auditLog.resource
                                                        ?.type
                                                )
                                            }}
                                        </strong>

                                        <span>
                                            {{
                                                auditLog.resource
                                                    ?.id
                                                    ? `#${auditLog.resource.id}`
                                                    : 'No resource ID'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </td>


                            <!-- METADATA -->

                            <td>
                                <div class="metadata-cell">
                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M8 4H5a2 2 0 00-2 2v12a2 2 0 002 2h3"
                                        />

                                        <path
                                            d="M16 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"
                                        />

                                        <path
                                            d="M9 12h6"
                                        />
                                    </svg>

                                    <span>
                                        {{
                                            metadataSummary(
                                                auditLog.metadata
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- IP -->

                            <td>
                                <div class="ip-cell">
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
                                            d="M3 12h18"
                                        />

                                        <path
                                            d="M12 3c3 3 4 6 4 9s-1 6-4 9"
                                        />

                                        <path
                                            d="M12 3c-3 3-4 6-4 9s1 6 4 9"
                                        />
                                    </svg>

                                    <code>
                                        {{
                                            auditLog.request_context
                                                ?.ip_address
                                            || '—'
                                        }}
                                    </code>
                                </div>
                            </td>


                            <!-- RECORDED -->

                            <td>
                                <div class="recorded-cell">
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
                                                auditLog.created_at
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- ACTION -->

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button audit-view-button"
                                    @click="
                                        openAuditLog(
                                            auditLog
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
                class="pagination audit-pagination"
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
                        audit entries
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

.audit-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRO
|--------------------------------------------------------------------------
*/

.audit-intro {
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
            circle at 94% 8%,
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

.audit-intro-copy {
    min-width: 0;

    flex: 1;
}

.audit-intro-label {
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

    text-transform: uppercase;
}

.audit-intro-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

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

.audit-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 23px;
    font-weight: 800;

    line-height: 30px;

    letter-spacing:
        -0.65px;
}

.audit-intro p {
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

.audit-intro-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

.audit-total-card {
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

.audit-total-icon {
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

.audit-total-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-total-card
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.audit-total-card span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
}

.audit-total-card strong {
    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;
}

.audit-refresh-button {
    min-height: 48px;
}

.audit-refresh-button svg {
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
| SECURITY BANNER
|--------------------------------------------------------------------------
*/

.audit-security-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #d4ebdf;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            var(--sh-success-bg),
            #fbfefd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.audit-security-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;

    color:
        var(--sh-success);
}

.audit-security-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-security-copy {
    min-width: 0;

    flex: 1;
}

.audit-security-copy strong {
    display: block;

    color:
        #285f4b;

    font-size: 9.5px;
}

.audit-security-copy p {
    max-width: 860px;

    margin:
        3px
        0
        0;

    color:
        #5f7c70;

    font-size: 8.5px;

    line-height: 14px;
}

.audit-security-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #cce2d6;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-success);

    font-size: 7.5px;
    font-weight: 800;

    text-transform: uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.audit-filter-card {
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

.filter-heading-main {
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

.filter-heading-main h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.filter-heading-main p {
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
| FILTER FORM
|--------------------------------------------------------------------------
*/

.audit-filters {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
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

.audit-filter-actions {
    grid-column:
        1
        / -1;

    align-items: center;
}

.audit-filter-actions
.primary-button,
.audit-filter-actions
.secondary-button {
    min-height: 40px;
}

.audit-filter-actions svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.audit-error {
    align-items: center;

    margin: 0;
}

.audit-error-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.audit-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-error-copy {
    min-width: 0;

    flex: 1;
}

.audit-error-copy strong {
    display: block;

    font-size: 10px;
}

.audit-error-copy p {
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

.audit-table-header {
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

.per-page > span {
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

.audit-table-wrapper {
    transition:
        opacity
        0.16s ease;
}

.table-refreshing {
    opacity: 0.55;

    pointer-events: none;
}

.audit-table-wrapper table {
    min-width: 1180px;
}

.audit-table-row {
    cursor: default;
}


/*
|--------------------------------------------------------------------------
| EVENT
|--------------------------------------------------------------------------
*/

.audit-event-cell {
    min-width: 215px;

    display: flex;
    align-items: center;

    gap: 9px;
}

.event-icon {
    width: 35px;
    height: 35px;

    flex: 0 0 35px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.event-icon svg {
    width: 16px;
    height: 16px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.event-icon.positive {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.event-icon.negative {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.event-icon.warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.event-icon.neutral {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.event-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.event-copy strong {
    max-width: 205px;

    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 9px;
    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.event-copy span {
    max-width: 205px;

    overflow: hidden;

    color:
        var(--sh-text-3);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7.5px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| ACTOR
|--------------------------------------------------------------------------
*/

.actor-cell {
    min-width: 160px;

    display: flex;
    align-items: center;

    gap: 8px;
}

.actor-avatar {
    width: 33px;
    height: 33px;

    flex: 0 0 33px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        #f1f3f5;

    color:
        var(--sh-text-3);

    font-size: 9px;
    font-weight: 800;
}

.actor-avatar.admin {
    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.actor-avatar.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.actor-avatar.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.actor-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.actor-copy strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
}

.actor-copy span {
    color:
        var(--sh-text-3);

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| RESOURCE
|--------------------------------------------------------------------------
*/

.resource-cell {
    min-width: 145px;

    display: flex;
    align-items: center;

    gap: 8px;
}

.resource-icon {
    width: 31px;
    height: 31px;

    flex: 0 0 31px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.resource-icon svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.resource-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.resource-copy strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
}

.resource-copy span {
    color:
        var(--sh-text-3);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| METADATA
|--------------------------------------------------------------------------
*/

.metadata-cell {
    min-width: 165px;

    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-3);
}

.metadata-cell svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.metadata-cell span {
    max-width: 185px;

    overflow: hidden;

    font-size: 8px;

    line-height: 13px;

    text-overflow: ellipsis;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| IP ADDRESS
|--------------------------------------------------------------------------
*/

.ip-cell {
    min-width: 120px;

    display: flex;
    align-items: center;

    gap: 6px;
}

.ip-cell svg {
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

.ip-cell code {
    padding:
        4px
        6px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 6px;

    background:
        var(--sh-surface-muted);

    color:
        #5f6874;

    font-size: 7.5px;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| RECORDED
|--------------------------------------------------------------------------
*/

.recorded-cell {
    min-width: 150px;

    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-2);
}

.recorded-cell svg {
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

.recorded-cell span {
    font-size: 8.2px;

    white-space: nowrap;
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

.audit-view-button {
    min-height: 34px;

    padding:
        0
        10px;
}

.audit-view-button svg {
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

.audit-loading-state {
    min-height: 285px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;
}

.audit-loading-icon {
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

.audit-loading-icon
.spinner {
    margin: 0;
}

.audit-loading-state h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.audit-loading-state p {
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

.audit-empty-state {
    min-height: 285px;
}

.empty-reset-button {
    margin-top: 13px;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.audit-pagination {
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
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        audit-spin
        0.8s
        linear
        infinite;
}

@keyframes audit-spin {
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

@media (max-width: 1100px) {
    .audit-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .audit-intro-actions {
        width: 100%;
    }

    .audit-total-card {
        flex: 1;
    }
}


@media (max-width: 900px) {
    .audit-filters {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .audit-filter-actions {
        grid-column:
            1
            / -1;
    }

    .audit-table-header {
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
    .audit-intro {
        padding: 17px;
    }

    .audit-intro h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .audit-intro-actions {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .audit-total-card,
    .audit-refresh-button {
        width: 100%;
    }

    .audit-security-banner {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .audit-security-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .audit-security-badge {
        margin-left: 49px;
    }

    .filter-card-heading {
        align-items:
            flex-start;
    }

    .filter-heading-main {
        align-items:
            flex-start;
    }

    .audit-filters {
        display: flex;

        align-items:
            stretch;

        flex-direction:
            column;

        padding: 14px;
    }

    .audit-filter-actions {
        width: 100%;

        align-items:
            stretch;

        flex-direction:
            column;
    }

    .audit-filter-actions
    .primary-button,
    .audit-filter-actions
    .secondary-button {
        width: 100%;
    }

    .audit-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .audit-error
    .retry-button {
        width: 100%;
    }

    .audit-pagination {
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