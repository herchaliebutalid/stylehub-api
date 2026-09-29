<script setup>
import {
    computed,
    onMounted,
    ref,
    watch,
} from 'vue';

import {
    useRoute,
    useRouter,
} from 'vue-router';

import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const notification = ref(null);

const isLoading = ref(false);
const isRetrying = ref(false);

const errorMessage = ref('');
const successMessage = ref('');

const retryModalOpen = ref(false);


const smsNotificationId =
    computed(
        () =>
            route.params
                .smsNotificationId,
    );


/*
|--------------------------------------------------------------------------
| SMS STATE
|--------------------------------------------------------------------------
*/

const canRetry =
    computed(
        () =>
            notification.value
                ?.can_retry
            === true,
    );

const isUncertain =
    computed(
        () =>
            notification.value
                ?.failure_kind
            === 'uncertain',
    );

const isDefiniteFailure =
    computed(
        () =>
            notification.value
                ?.failure_kind
            === 'definite',
    );


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
            year:
                'numeric',

            month:
                'short',

            day:
                'numeric',

            hour:
                'numeric',

            minute:
                '2-digit',

            second:
                '2-digit',

            timeZone:
                'Asia/Manila',
        },
    ).format(date);
}

function formatAppointmentDate(value) {
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
            year:
                'numeric',

            month:
                'long',

            day:
                'numeric',

            timeZone:
                'Asia/Manila',
        },
    ).format(date);
}

function userInitial() {
    const value =
        notification.value
            ?.user
            ?.full_name
        ||
        notification.value
            ?.user
            ?.email
        ||
        'S';

    return String(value)
        .charAt(0)
        .toUpperCase();
}

function shopInitial() {
    const value =
        notification.value
            ?.appointment
            ?.shop
            ?.name
        ||
        'S';

    return String(value)
        .charAt(0)
        .toUpperCase();
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
| LOAD NOTIFICATION
|--------------------------------------------------------------------------
*/

async function loadNotification() {
    if (!auth.token) {
        await authenticationFailure();

        return;
    }

    isLoading.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/sms-notifications/${encodeURIComponent(
                    smsNotificationId.value,
                )}`,
                {
                    token:
                        auth.token,
                },
            );

        notification.value =
            response
                ?.data
                ?.sms_notification
            ?? null;

        if (
            !notification.value
        ) {
            throw new Error(
                'The SMS notification response was invalid.',
            );
        }
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
            error?.status === 404
                ? 'The requested SMS notification could not be found.'
                : error
                    ?.data
                    ?.message
                    ??
                    error
                        ?.message
                    ??
                    'Unable to load SMS notification.';
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| RETRY MODAL
|--------------------------------------------------------------------------
*/

function openRetryModal() {
    if (
        !canRetry.value
        ||
        isRetrying.value
    ) {
        return;
    }

    successMessage.value =
        '';

    errorMessage.value =
        '';

    retryModalOpen.value =
        true;
}

function closeRetryModal() {
    if (
        isRetrying.value
    ) {
        return;
    }

    retryModalOpen.value =
        false;
}


/*
|--------------------------------------------------------------------------
| CONTROLLED RETRY
|--------------------------------------------------------------------------
*/

async function retryNotification() {
    if (
        !canRetry.value
        ||
        isRetrying.value
    ) {
        return;
    }

    isRetrying.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/sms-notifications/${encodeURIComponent(
                    smsNotificationId.value,
                )}/retry`,
                {
                    method:
                        'POST',

                    token:
                        auth.token,
                },
            );

        notification.value =
            response
                ?.data
                ?.sms_notification
            ??
            notification.value;

        successMessage.value =
            response?.message
            ??
            'SMS notification was queued for a controlled retry.';

        retryModalOpen.value =
            false;
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
            'Unable to retry SMS notification.';

        if (
            error?.status === 409
        ) {
            retryModalOpen.value =
                false;

            await loadNotification();
        }
    } finally {
        isRetrying.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function goBack() {
    await router.push({
        name:
            'admin.sms-monitoring',
    });
}

async function openUser() {
    const id =
        notification.value
            ?.user
            ?.id;

    if (!id) {
        return;
    }

    await router.push({
        name:
            'admin.user.detail',

        params: {
            userId:
                id,
        },
    });
}

async function openAppointment() {
    const id =
        notification.value
            ?.appointment
            ?.id;

    if (!id) {
        return;
    }

    await router.push({
        name:
            'admin.appointment.detail',

        params: {
            appointmentId:
                id,
        },
    });
}

async function openShop() {
    const id =
        notification.value
            ?.appointment
            ?.shop
            ?.id;

    if (!id) {
        return;
    }

    await router.push({
        name:
            'admin.shop.detail',

        params: {
            shopId:
                id,
        },
    });
}


/*
|--------------------------------------------------------------------------
| ROUTE WATCH
|--------------------------------------------------------------------------
*/

watch(
    () =>
        route.params
            .smsNotificationId,

    () => {
        loadNotification();
    },
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadNotification();
});
</script>


<template>
    <div class="detail-page sms-detail-page">
        <!-- ============================================================
             NAVIGATION
             ============================================================ -->

        <div class="detail-navigation">
            <button
                type="button"
                class="back-button"
                @click="goBack"
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
                    Back to SMS Monitoring
                </span>
            </button>


            <button
                v-if="notification"
                type="button"
                class="secondary-button refresh-detail-button"
                :disabled="
                    isLoading
                    ||
                    isRetrying
                "
                @click="
                    loadNotification
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
                    Refresh
                </span>
            </button>
        </div>


        <!-- ============================================================
             SUCCESS
             ============================================================ -->

        <div
            v-if="
                successMessage
            "
            class="success-alert detail-alert"
        >
            <div class="alert-icon success">
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

            <div class="alert-copy">
                <strong>
                    Retry queued
                </strong>

                <span>
                    {{ successMessage }}
                </span>
            </div>
        </div>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                errorMessage
            "
            class="error-alert detail-alert"
            role="alert"
        >
            <div class="alert-icon error">
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

            <div class="alert-copy">
                <strong>
                    Unable to complete request
                </strong>

                <span>
                    {{ errorMessage }}
                </span>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="
                    isLoading
                    ||
                    isRetrying
                "
                @click="
                    loadNotification
                "
            >
                Refresh
            </button>
        </div>


        <!-- ============================================================
             LOADING
             ============================================================ -->

        <div
            v-if="
                isLoading
                &&
                !notification
            "
            class="loading-card sms-loading-card"
        >
            <div class="loading-icon-wrap">
                <div class="spinner" />
            </div>

            <h3>
                Loading SMS delivery record
            </h3>

            <p>
                Retrieving notification, recipient,
                provider and delivery information.
            </p>
        </div>


        <!-- ============================================================
             CONTENT
             ============================================================ -->

        <template
            v-else-if="
                notification
            "
        >
            <!-- ========================================================
                 HERO
                 ======================================================== -->

            <section class="sms-record-hero">
                <div class="sms-record-main">
                    <div class="sms-record-icon">
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

                    <div class="sms-record-copy">
                        <div class="sms-record-eyebrow">
                            <span class="sms-record-dot" />

                            SMS delivery record

                            <span class="sms-record-id">
                                #{{ notification.id }}
                            </span>
                        </div>

                        <h2>
                            {{
                                notification.type_label
                                ||
                                humanize(
                                    notification.type
                                )
                            }}
                        </h2>

                        <div class="sms-record-meta">
                            <div class="sms-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M7 3h4l2 5-3 2c1.4 3 3.7 5.3 6.7 6.7l2-3 5 2v4c0 1.1-.9 2-2 2C11.4 21.7 2.3 12.6 2.3 2.3 2.3 1.2 3.2.3 4.3.3"
                                    />
                                </svg>

                                <span>
                                    {{
                                        notification.recipient
                                        ||
                                        'Recipient unavailable'
                                    }}
                                </span>
                            </div>

                            <span class="sms-meta-divider" />

                            <div class="sms-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 4h16v16H4z"
                                    />

                                    <path
                                        d="M8 8h8M8 12h8M8 16h5"
                                    />
                                </svg>

                                <span>
                                    {{
                                        notification.provider
                                        ||
                                        'Provider unavailable'
                                    }}
                                </span>
                            </div>

                            <span class="sms-meta-divider" />

                            <div class="sms-meta-item">
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
                        </div>
                    </div>
                </div>


                <!-- HERO ACTIONS -->

                <div class="sms-hero-actions">
                    <div class="sms-hero-statuses">
                        <span
                            class="status-badge hero-status"
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
                            {{
                                notification.failure_kind_label
                                ||
                                humanize(
                                    notification.failure_kind
                                )
                            }}
                        </span>
                    </div>


                    <button
                        v-if="
                            canRetry
                        "
                        type="button"
                        class="controlled-retry-button"
                        :disabled="
                            isRetrying
                        "
                        @click="
                            openRetryModal
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

                            <path
                                d="M15 12l2 2 4-4"
                            />
                        </svg>

                        <span>
                            Retry SMS
                        </span>
                    </button>
                </div>
            </section>


            <!-- ========================================================
                 RETRY SAFETY
                 ======================================================== -->

            <section
                v-if="
                    canRetry
                "
                class="retry-safety-panel safe"
            >
                <div class="retry-safety-icon">
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

                <div class="retry-safety-copy">
                    <div class="retry-safety-heading">
                        <div>
                            <span>
                                CONTROLLED RETRY AVAILABLE
                            </span>

                            <h3>
                                This SMS is safe to retry
                            </h3>
                        </div>

                        <span class="retry-safety-badge">
                            Safe
                        </span>
                    </div>

                    <p>
                        This record represents a definite provider
                        failure. StyleHub can safely requeue the
                        stored SMS without changing the recipient
                        or message content.
                    </p>
                </div>
            </section>


            <section
                v-else-if="
                    isUncertain
                "
                class="retry-safety-panel uncertain"
            >
                <div class="retry-safety-icon">
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

                <div class="retry-safety-copy">
                    <div class="retry-safety-heading">
                        <div>
                            <span>
                                RETRY BLOCKED
                            </span>

                            <h3>
                                Delivery outcome is uncertain
                            </h3>
                        </div>

                        <span class="retry-warning-badge">
                            Do not retry
                        </span>
                    </div>

                    <p>
                        The provider may already have accepted
                        this message. Retrying an uncertain
                        delivery could send a duplicate SMS,
                        so StyleHub intentionally blocks retry.
                    </p>
                </div>
            </section>


            <section
                v-else-if="
                    isDefiniteFailure
                "
                class="retry-safety-panel unavailable"
            >
                <div class="retry-safety-icon">
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

                <div class="retry-safety-copy">
                    <div class="retry-safety-heading">
                        <div>
                            <span>
                                RETRY UNAVAILABLE
                            </span>

                            <h3>
                                This record cannot currently be retried
                            </h3>
                        </div>
                    </div>

                    <p>
                        The SMS record is classified as a definite
                        failure, but the backend has not marked this
                        record as eligible for a controlled retry.
                    </p>
                </div>
            </section>


            <!-- ========================================================
                 SUMMARY CARDS
                 ======================================================== -->

            <section class="sms-detail-summary-grid">
                <!-- STATUS -->

                <article class="sms-detail-summary-card">
                    <div
                        class="sms-detail-summary-icon"
                        :class="
                            statusClass(
                                notification.status
                            )
                        "
                    >
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
                                v-if="
                                    notification.status
                                    === 'accepted'
                                "
                                d="M8 12l3 3 5-6"
                            />

                            <path
                                v-else-if="
                                    notification.status
                                    === 'failed'
                                "
                                d="M9 9l6 6M15 9l-6 6"
                            />

                            <path
                                v-else
                                d="M12 7v5l3 2"
                            />
                        </svg>
                    </div>

                    <div class="sms-detail-summary-copy">
                        <span>
                            Delivery status
                        </span>

                        <strong class="text-value">
                            {{
                                notification.status_label
                                ||
                                humanize(
                                    notification.status
                                )
                            }}
                        </strong>

                        <small>
                            Current processing state
                        </small>
                    </div>
                </article>


                <!-- ATTEMPTS -->

                <article class="sms-detail-summary-card">
                    <div class="sms-detail-summary-icon attempts">
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

                    <div class="sms-detail-summary-copy">
                        <span>
                            Attempts
                        </span>

                        <strong>
                            {{
                                notification.attempts
                                ?? 0
                            }}
                        </strong>

                        <small>
                            Processing attempts
                        </small>
                    </div>
                </article>


                <!-- PROVIDER -->

                <article class="sms-detail-summary-card">
                    <div class="sms-detail-summary-icon provider">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 4h16v16H4z"
                            />

                            <path
                                d="M8 8h8M8 12h8M8 16h5"
                            />
                        </svg>
                    </div>

                    <div class="sms-detail-summary-copy">
                        <span>
                            Provider
                        </span>

                        <strong class="text-value provider-name">
                            {{
                                notification.provider
                                ||
                                'Unavailable'
                            }}
                        </strong>

                        <small>
                            SMS delivery provider
                        </small>
                    </div>
                </article>


                <!-- RETRY -->

                <article class="sms-detail-summary-card">
                    <div
                        class="sms-detail-summary-icon"
                        :class="
                            canRetry
                                ? 'retry-safe'
                                : isUncertain
                                    ? 'retry-warning'
                                    : 'retry-disabled'
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
                    </div>

                    <div class="sms-detail-summary-copy">
                        <span>
                            Retry eligibility
                        </span>

                        <strong class="text-value">
                            {{
                                canRetry
                                    ? 'Safe to retry'
                                    : isUncertain
                                        ? 'Blocked'
                                        : 'Not retryable'
                            }}
                        </strong>

                        <small>
                            Controlled retry safety
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 DETAILS GRID
                 ======================================================== -->

            <section class="sms-information-grid">
                <!-- ====================================================
                     DELIVERY STATE
                     ==================================================== -->

                <article class="detail-card sms-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon delivery">
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
                                    DELIVERY
                                </p>

                                <h3>
                                    Delivery state
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="delivery-overview">
                        <div
                            class="delivery-overview-icon"
                            :class="
                                statusClass(
                                    notification.status
                                )
                            "
                        >
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
                                    v-if="
                                        notification.status
                                        === 'accepted'
                                    "
                                    d="M8 12l3 3 5-6"
                                />

                                <path
                                    v-else-if="
                                        notification.status
                                        === 'failed'
                                    "
                                    d="M9 9l6 6M15 9l-6 6"
                                />

                                <path
                                    v-else
                                    d="M12 7v5l3 2"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Current status
                            </span>

                            <strong>
                                {{
                                    notification.status_label
                                    ||
                                    humanize(
                                        notification.status
                                    )
                                }}
                            </strong>
                        </div>

                        <span
                            class="status-badge"
                            :class="
                                statusClass(
                                    notification.status
                                )
                            "
                        >
                            <span class="status-dot" />

                            {{
                                humanize(
                                    notification.status
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Status
                            </dt>

                            <dd>
                                <span
                                    class="status-badge"
                                    :class="
                                        statusClass(
                                            notification.status
                                        )
                                    "
                                >
                                    {{
                                        notification.status_label
                                        ||
                                        humanize(
                                            notification.status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Failure kind
                            </dt>

                            <dd>
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
                                    {{
                                        notification.failure_kind_label
                                        ||
                                        humanize(
                                            notification.failure_kind
                                        )
                                    }}
                                </span>

                                <span v-else>
                                    —
                                </span>
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Attempts
                            </dt>

                            <dd>
                                {{
                                    notification.attempts
                                    ?? 0
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Retry eligibility
                            </dt>

                            <dd>
                                <span
                                    class="retry-state"
                                    :class="{
                                        safe:
                                            canRetry,

                                        blocked:
                                            isUncertain,
                                    }"
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

                                    {{
                                        canRetry
                                            ? 'Safe to retry'
                                            : isUncertain
                                                ? 'Retry blocked'
                                                : 'Not retryable'
                                    }}
                                </span>
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Last attempt
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        notification.last_attempt_at
                                    )
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Accepted at
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        notification.accepted_at
                                    )
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Failed at
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        notification.failed_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ====================================================
                     PROVIDER
                     ==================================================== -->

                <article class="detail-card sms-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon provider">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 4h16v16H4z"
                                    />

                                    <path
                                        d="M8 8h8M8 12h8M8 16h5"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    PROVIDER
                                </p>

                                <h3>
                                    Provider diagnostics
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="provider-overview">
                        <div class="provider-logo">
                            {{
                                String(
                                    notification.provider
                                    ||
                                    'P'
                                )
                                    .charAt(0)
                                    .toUpperCase()
                            }}
                        </div>

                        <div class="provider-overview-copy">
                            <span>
                                SMS provider
                            </span>

                            <strong>
                                {{
                                    notification.provider
                                    ||
                                    'Unavailable'
                                }}
                            </strong>
                        </div>

                        <span
                            v-if="
                                notification.provider_status
                            "
                            class="provider-status-chip"
                        >
                            {{
                                notification.provider_status
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Provider
                            </dt>

                            <dd class="capitalize-value">
                                {{
                                    notification.provider
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Provider status
                            </dt>

                            <dd>
                                {{
                                    notification.provider_status
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Provider message ID
                            </dt>

                            <dd class="code-value">
                                {{
                                    notification.provider_message_id
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Created
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        notification.created_at
                                    )
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                Updated
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        notification.updated_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>


                    <div
                        v-if="
                            notification.last_error
                        "
                        class="provider-error-panel"
                    >
                        <div class="provider-error-heading">
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
                                    d="M12 8v5M12 17h.01"
                                />
                            </svg>

                            <span>
                                Last provider error
                            </span>
                        </div>

                        <p>
                            {{
                                notification.last_error
                            }}
                        </p>
                    </div>
                </article>


                <!-- ====================================================
                     RECIPIENT
                     ==================================================== -->

                <article class="detail-card sms-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon recipient">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M7 3h4l2 5-3 2c1.4 3 3.7 5.3 6.7 6.7l2-3 5 2v4c0 1.1-.9 2-2 2C11.4 21.7 2.3 12.6 2.3 2.3 2.3 1.2 3.2.3 4.3.3"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    RECIPIENT
                                </p>

                                <h3>
                                    Recipient snapshot
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                notification.user
                                    ?.id
                            "
                            type="button"
                            class="entity-link-button"
                            @click="
                                openUser
                            "
                        >
                            <span>
                                View user
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


                    <div class="recipient-preview">
                        <div
                            class="recipient-avatar"
                            :class="{
                                system:
                                    !notification.user,
                            }"
                        >
                            {{
                                userInitial()
                            }}
                        </div>

                        <div class="recipient-preview-copy">
                            <span>
                                {{
                                    notification.user
                                        ? humanize(
                                            notification.user
                                                ?.role
                                        )
                                        : 'System recipient'
                                }}
                            </span>

                            <strong>
                                {{
                                    notification.user
                                        ?.full_name
                                    ||
                                    notification.recipient
                                    ||
                                    'Unavailable'
                                }}
                            </strong>

                            <small>
                                {{
                                    notification.recipient
                                    ||
                                    'No mobile number'
                                }}
                            </small>
                        </div>

                        <span
                            v-if="
                                notification.user
                                    ?.status
                            "
                            class="status-badge"
                            :class="
                                statusClass(
                                    notification.user
                                        .status
                                )
                            "
                        >
                            {{
                                humanize(
                                    notification.user
                                        .status
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Mobile number
                            </dt>

                            <dd class="phone-value">
                                {{
                                    notification.recipient
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                User
                            </dt>

                            <dd>
                                <button
                                    v-if="
                                        notification.user
                                            ?.id
                                    "
                                    type="button"
                                    class="inline-link-button"
                                    @click="
                                        openUser
                                    "
                                >
                                    {{
                                        notification.user
                                            .full_name
                                    }}
                                </button>

                                <span v-else>
                                    System / unavailable
                                </span>
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                User email
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    notification.user
                                        ?.email
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                User role
                            </dt>

                            <dd>
                                <span
                                    v-if="
                                        notification.user
                                            ?.role
                                    "
                                    class="role-badge"
                                    :class="
                                        statusClass(
                                            notification.user
                                                .role
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            notification.user
                                                .role
                                        )
                                    }}
                                </span>

                                <span v-else>
                                    —
                                </span>
                            </dd>
                        </div>


                        <div class="information-row">
                            <dt>
                                User status
                            </dt>

                            <dd>
                                <span
                                    v-if="
                                        notification.user
                                            ?.status
                                    "
                                    class="status-badge"
                                    :class="
                                        statusClass(
                                            notification.user
                                                .status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            notification.user
                                                .status
                                        )
                                    }}
                                </span>

                                <span v-else>
                                    —
                                </span>
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ====================================================
                     APPOINTMENT
                     ==================================================== -->

                <article class="detail-card sms-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon appointment">
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
                                    BUSINESS CONTEXT
                                </p>

                                <h3>
                                    Related appointment
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                notification.appointment
                                    ?.id
                            "
                            type="button"
                            class="entity-link-button"
                            @click="
                                openAppointment
                            "
                        >
                            <span>
                                View appointment
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


                    <template
                        v-if="
                            notification.appointment
                        "
                    >
                        <div class="appointment-preview">
                            <div class="appointment-preview-icon">
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

                            <div class="appointment-preview-copy">
                                <span>
                                    Appointment
                                </span>

                                <strong>
                                    #{{
                                        notification.appointment
                                            .id
                                    }}
                                </strong>

                                <small>
                                    {{
                                        formatAppointmentDate(
                                            notification.appointment
                                                .appointment_date
                                        )
                                    }}
                                </small>
                            </div>

                            <span
                                class="status-badge"
                                :class="
                                    statusClass(
                                        notification.appointment
                                            .status
                                    )
                                "
                            >
                                {{
                                    humanize(
                                        notification.appointment
                                            .status
                                    )
                                }}
                            </span>
                        </div>


                        <dl class="information-list">
                            <div class="information-row">
                                <dt>
                                    Appointment
                                </dt>

                                <dd>
                                    <button
                                        type="button"
                                        class="inline-link-button"
                                        @click="
                                            openAppointment
                                        "
                                    >
                                        #{{
                                            notification.appointment
                                                .id
                                        }}
                                    </button>
                                </dd>
                            </div>


                            <div class="information-row">
                                <dt>
                                    Status
                                </dt>

                                <dd>
                                    <span
                                        class="status-badge"
                                        :class="
                                            statusClass(
                                                notification.appointment
                                                    .status
                                            )
                                        "
                                    >
                                        {{
                                            humanize(
                                                notification.appointment
                                                    .status
                                            )
                                        }}
                                    </span>
                                </dd>
                            </div>


                            <div class="information-row">
                                <dt>
                                    Appointment date
                                </dt>

                                <dd>
                                    {{
                                        formatAppointmentDate(
                                            notification.appointment
                                                .appointment_date
                                        )
                                    }}
                                </dd>
                            </div>


                            <div class="information-row">
                                <dt>
                                    Starts at
                                </dt>

                                <dd>
                                    {{
                                        notification.appointment
                                            .starts_at
                                        ||
                                        '—'
                                    }}
                                </dd>
                            </div>


                            <div class="information-row">
                                <dt>
                                    Shop
                                </dt>

                                <dd>
                                    <button
                                        v-if="
                                            notification.appointment
                                                .shop
                                                ?.id
                                        "
                                        type="button"
                                        class="inline-link-button"
                                        @click="
                                            openShop
                                        "
                                    >
                                        {{
                                            notification.appointment
                                                .shop
                                                .name
                                        }}
                                    </button>

                                    <span v-else>
                                        —
                                    </span>
                                </dd>
                            </div>
                        </dl>


                        <button
                            v-if="
                                notification.appointment
                                    .shop
                                    ?.id
                            "
                            type="button"
                            class="linked-shop-card"
                            @click="
                                openShop
                            "
                        >
                            <div class="linked-shop-avatar">
                                {{
                                    shopInitial()
                                }}
                            </div>

                            <div class="linked-shop-copy">
                                <span>
                                    Related shop
                                </span>

                                <strong>
                                    {{
                                        notification.appointment
                                            .shop
                                            .name
                                    }}
                                </strong>
                            </div>

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M9 18l6-6-6-6"
                                />
                            </svg>
                        </button>
                    </template>


                    <div
                        v-else
                        class="empty-context-card"
                    >
                        <div class="empty-context-icon">
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
                                    d="M6 6l12 12"
                                />
                            </svg>
                        </div>

                        <strong>
                            No related appointment
                        </strong>

                        <span>
                            This SMS notification is not linked
                            to an appointment record.
                        </span>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 MESSAGE SNAPSHOT
                 ======================================================== -->

            <section class="detail-card sms-message-card">
                <div class="message-card-header">
                    <div class="information-heading">
                        <div class="information-icon message">
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
                                MESSAGE SNAPSHOT
                            </p>

                            <h3>
                                SMS content
                            </h3>

                            <p>
                                Stored message content associated
                                with this delivery record.
                            </p>
                        </div>
                    </div>

                    <span class="snapshot-badge">
                        Stored snapshot
                    </span>
                </div>


                <div class="message-preview">
                    <div class="message-preview-phone">
                        <div class="phone-speaker" />

                        <div class="phone-message-header">
                            <div class="message-sender-icon">
                                S
                            </div>

                            <div>
                                <strong>
                                    StyleHub
                                </strong>

                                <span>
                                    SMS notification
                                </span>
                            </div>
                        </div>

                        <div class="message-bubble">
                            {{
                                notification.message
                                ||
                                'No message snapshot is available.'
                            }}
                        </div>
                    </div>


                    <div class="message-record-information">
                        <div class="message-info-row">
                            <span>
                                Recipient
                            </span>

                            <strong>
                                {{
                                    notification.recipient
                                    ||
                                    '—'
                                }}
                            </strong>
                        </div>


                        <div class="message-info-row">
                            <span>
                                Notification type
                            </span>

                            <strong>
                                {{
                                    notification.type_label
                                    ||
                                    humanize(
                                        notification.type
                                    )
                                }}
                            </strong>
                        </div>


                        <div class="message-info-row">
                            <span>
                                Provider
                            </span>

                            <strong class="capitalize-value">
                                {{
                                    notification.provider
                                    ||
                                    '—'
                                }}
                            </strong>
                        </div>


                        <div class="dedupe-information">
                            <span>
                                Dedupe key
                            </span>

                            <code>
                                {{
                                    notification.dedupe_key
                                    ||
                                    '—'
                                }}
                            </code>
                        </div>
                    </div>
                </div>
            </section>
        </template>


        <!-- ============================================================
             RETRY CONFIRMATION
             ============================================================ -->

        <div
            v-if="
                retryModalOpen
            "
            class="modal-backdrop"
            @click.self="
                closeRetryModal
            "
        >
            <div class="modal-card controlled-retry-modal">
                <div class="retry-modal-heading">
                    <div class="retry-modal-icon">
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

                    <div>
                        <span>
                            CONTROLLED RETRY
                        </span>

                        <h3>
                            Queue SMS retry?
                        </h3>
                    </div>
                </div>


                <p class="retry-modal-description">
                    StyleHub will resend the stored SMS exactly
                    as recorded. The recipient and message
                    cannot be changed.
                </p>


                <div class="retry-safety-confirmation">
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

                    <div>
                        <strong>
                            Backend marked this record safe
                            for controlled retry
                        </strong>

                        <span>
                            This action also creates an
                            Administrator audit log.
                        </span>
                    </div>
                </div>


                <div class="retry-record-summary">
                    <div>
                        <span>
                            SMS record
                        </span>

                        <strong>
                            #{{ notification?.id }}
                        </strong>
                    </div>

                    <div>
                        <span>
                            Recipient
                        </span>

                        <strong>
                            {{
                                notification
                                    ?.recipient
                                ||
                                '—'
                            }}
                        </strong>
                    </div>

                    <div>
                        <span>
                            Previous attempts
                        </span>

                        <strong>
                            {{
                                notification
                                    ?.attempts
                                ?? 0
                            }}
                        </strong>
                    </div>

                    <div>
                        <span>
                            Failure type
                        </span>

                        <strong>
                            {{
                                notification
                                    ?.failure_kind_label
                                ||
                                humanize(
                                    notification
                                        ?.failure_kind
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isRetrying
                        "
                        @click="
                            closeRetryModal
                        "
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="confirm-retry-button"
                        :disabled="
                            isRetrying
                        "
                        @click="
                            retryNotification
                        "
                    >
                        <span
                            v-if="
                                isRetrying
                            "
                            class="button-spinner"
                        />

                        <svg
                            v-else
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

                        <span>
                            {{
                                isRetrying
                                    ? 'Queueing...'
                                    : 'Queue retry'
                            }}
                        </span>
                    </button>
                </div>
            </div>
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

.sms-detail-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

.detail-navigation {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;
}

.sms-detail-page
.back-button {
    margin: 0;
}

.back-button svg,
.refresh-detail-button svg {
    width: 14px;
    height: 14px;

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
| ALERTS
|--------------------------------------------------------------------------
*/

.detail-alert {
    align-items: center;

    margin: 0;
}

.alert-icon {
    width: 36px;
    height: 36px;

    flex: 0 0 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.alert-icon.success {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.alert-icon.error {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.alert-icon svg {
    width: 17px;
    height: 17px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.alert-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.alert-copy strong {
    font-size: 10px;
    font-weight: 800;
}

.alert-copy span {
    font-size: 9px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.sms-loading-card {
    min-height: 360px;
}

.loading-icon-wrap {
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

.loading-icon-wrap
.spinner {
    margin: 0;
}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.sms-record-hero {
    position: relative;

    overflow: hidden;

    min-height: 172px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

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
            circle at 94% 5%,
            rgba(
                214,
                154,
                45,
                0.08
            ),
            transparent 30%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.sms-record-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.sms-record-icon {
    width: 67px;
    height: 67px;

    flex: 0 0 67px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 19px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary),
            var(--sh-primary-2)
        );

    color:
        var(--sh-accent);

    box-shadow:
        0 10px 24px
        rgba(
            23,
            32,
            42,
            0.13
        );
}

.sms-record-icon svg {
    width: 27px;
    height: 27px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.6;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.sms-record-copy {
    min-width: 0;

    flex: 1;
}

.sms-record-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.05px;

    text-transform:
        uppercase;
}

.sms-record-dot {
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

.sms-record-id {
    padding:
        2px
        5px;

    border-radius: 5px;

    background:
        #f1f3f6;

    color:
        var(--sh-text-3);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7px;

    letter-spacing: 0;
}

.sms-record-copy h2 {
    max-width: 720px;

    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text);

    font-size: 25px;
    font-weight: 800;

    line-height: 31px;

    letter-spacing:
        -0.7px;
}


/*
|--------------------------------------------------------------------------
| HERO META
|--------------------------------------------------------------------------
*/

.sms-record-meta {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.sms-meta-item {
    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.sms-meta-item svg {
    width: 13px;
    height: 13px;

    flex: 0 0 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.sms-meta-divider {
    width: 3px;
    height: 3px;

    flex: 0 0 3px;

    border-radius: 999px;

    background:
        #c9ced5;
}


/*
|--------------------------------------------------------------------------
| HERO ACTIONS
|--------------------------------------------------------------------------
*/

.sms-hero-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 12px;
}

.sms-hero-statuses {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 7px;

    flex-wrap: wrap;
}

.hero-status {
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

.controlled-retry-button {
    min-height: 39px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding:
        0
        14px;

    border:
        1px solid
        var(--sh-success);

    border-radius: 9px;

    background:
        var(--sh-success);

    color:
        #ffffff;

    font: inherit;

    font-size: 9px;
    font-weight: 750;

    cursor: pointer;

    transition:
        transform 0.15s ease,
        background 0.15s ease;
}

.controlled-retry-button:hover:not(:disabled) {
    transform:
        translateY(
            -1px
        );

    background:
        #22664d;
}

.controlled-retry-button:disabled {
    cursor: not-allowed;

    opacity: 0.6;
}

.controlled-retry-button svg {
    width: 14px;
    height: 14px;

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
| STATUS COLORS
|--------------------------------------------------------------------------
*/

.status-badge.pending {
    border-color:
        #f0dfb9 !important;

    background:
        var(--sh-warning-bg) !important;

    color:
        var(--sh-warning) !important;
}

.status-badge.processing {
    border-color:
        #d9e4f8 !important;

    background:
        var(--sh-info-bg) !important;

    color:
        var(--sh-info) !important;
}

.status-badge.accepted,
.status-badge.active,
.status-badge.completed,
.status-badge.approved,
.status-badge.confirmed {
    border-color:
        #d4ebdf !important;

    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.status-badge.failed,
.status-badge.suspended,
.status-badge.cancelled,
.status-badge.declined,
.status-badge.rejected {
    border-color:
        #eed3d3 !important;

    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}

.status-badge.skipped {
    border-color:
        #e0e4e8 !important;

    background:
        #f2f4f6 !important;

    color:
        #68717c !important;
}


/*
|--------------------------------------------------------------------------
| FAILURE BADGES
|--------------------------------------------------------------------------
*/

.failure-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border:
        1px solid
        transparent;

    border-radius: 999px;

    font-size: 8px;
    font-weight: 750;

    white-space: nowrap;
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


/*
|--------------------------------------------------------------------------
| RETRY SAFETY PANEL
|--------------------------------------------------------------------------
*/

.retry-safety-panel {
    display: flex;
    align-items: flex-start;

    gap: 12px;

    padding:
        15px
        16px;

    border-radius:
        var(--sh-radius-lg);

    box-shadow:
        var(--sh-shadow-sm);
}

.retry-safety-panel.safe {
    border:
        1px solid
        #d2e8dc;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-success-bg)
        );
}

.retry-safety-panel.uncertain {
    border:
        1px solid
        #efdeb9;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-warning-bg)
        );
}

.retry-safety-panel.unavailable {
    border:
        1px solid
        #ecd4d4;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-danger-bg)
        );
}

.retry-safety-icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        rgba(
            255,
            255,
            255,
            0.7
        );
}

.retry-safety-panel.safe
.retry-safety-icon {
    color:
        var(--sh-success);
}

.retry-safety-panel.uncertain
.retry-safety-icon {
    color:
        var(--sh-warning);
}

.retry-safety-panel.unavailable
.retry-safety-icon {
    color:
        var(--sh-danger);
}

.retry-safety-icon svg {
    width: 20px;
    height: 20px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.retry-safety-copy {
    min-width: 0;

    flex: 1;
}

.retry-safety-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 12px;
}

.retry-safety-heading
> div
> span {
    display: block;

    margin-bottom: 2px;

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.9px;
}

.retry-safety-panel.safe
.retry-safety-heading
> div
> span {
    color:
        var(--sh-success);
}

.retry-safety-panel.uncertain
.retry-safety-heading
> div
> span {
    color:
        var(--sh-warning);
}

.retry-safety-panel.unavailable
.retry-safety-heading
> div
> span {
    color:
        var(--sh-danger);
}

.retry-safety-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.retry-safety-copy p {
    max-width: 850px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 15px;
}

.retry-safety-badge,
.retry-warning-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border-radius: 999px;

    background:
        #ffffff;

    font-size: 7.5px;
    font-weight: 800;

    white-space: nowrap;
}

.retry-safety-badge {
    border:
        1px solid
        #cae3d5;

    color:
        var(--sh-success);
}

.retry-warning-badge {
    border:
        1px solid
        #e8d5a9;

    color:
        var(--sh-warning);
}


/*
|--------------------------------------------------------------------------
| SUMMARY GRID
|--------------------------------------------------------------------------
*/

.sms-detail-summary-grid {
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

.sms-detail-summary-card {
    min-width: 0;

    min-height: 105px;

    display: flex;
    align-items: center;

    gap: 12px;

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

.sms-detail-summary-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.sms-detail-summary-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.sms-detail-summary-icon.pending {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.sms-detail-summary-icon.processing {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.sms-detail-summary-icon.accepted {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.sms-detail-summary-icon.failed {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.sms-detail-summary-icon.skipped {
    background:
        #f1f3f5;

    color:
        var(--sh-text-3);
}

.sms-detail-summary-icon.attempts {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.sms-detail-summary-icon.provider {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.sms-detail-summary-icon.retry-safe {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.sms-detail-summary-icon.retry-warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.sms-detail-summary-icon.retry-disabled {
    background:
        #f1f3f5;

    color:
        var(--sh-text-3);
}

.sms-detail-summary-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.sms-detail-summary-copy span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.sms-detail-summary-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;

    line-height: 24px;
}

.sms-detail-summary-copy
.text-value {
    font-size: 13px;

    line-height: 18px;
}

.sms-detail-summary-copy
.provider-name {
    text-transform:
        capitalize;
}

.sms-detail-summary-copy small {
    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION GRID
|--------------------------------------------------------------------------
*/

.sms-information-grid {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 14px;
}

.sms-information-card {
    min-width: 0;

    padding: 0;

    overflow: hidden;
}

.information-card-header {
    min-height: 68px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        13px
        15px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.information-heading {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.information-icon {
    width: 36px;
    height: 36px;

    flex: 0 0 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.information-icon svg {
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

.information-icon.delivery {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.information-icon.provider {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.information-icon.recipient {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.information-icon.appointment {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.information-icon.message {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.information-card-header
.eyebrow,
.message-card-header
.eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.information-card-header h3,
.message-card-header h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| DELIVERY OVERVIEW
|--------------------------------------------------------------------------
*/

.delivery-overview {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        11px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.delivery-overview-icon {
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
        var(--sh-text-3);
}

.delivery-overview-icon.pending {
    color:
        var(--sh-warning);
}

.delivery-overview-icon.processing {
    color:
        var(--sh-info);
}

.delivery-overview-icon.accepted {
    color:
        var(--sh-success);
}

.delivery-overview-icon.failed {
    color:
        var(--sh-danger);
}

.delivery-overview-icon svg {
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

.delivery-overview
> div:nth-child(2) {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.delivery-overview
> div:nth-child(2)
span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.delivery-overview
> div:nth-child(2)
strong {
    color:
        var(--sh-text);

    font-size: 10px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION LIST
|--------------------------------------------------------------------------
*/

.information-list {
    margin: 0;

    padding:
        4px
        15px;
}

.information-row {
    min-height: 49px;

    display: grid;

    grid-template-columns:
        minmax(
            120px,
            0.8fr
        )
        minmax(
            0,
            1.5fr
        );

    align-items: center;

    gap: 15px;

    padding:
        8px
        0;

    border-bottom:
        1px solid
        #edf0f3;
}

.information-row:last-child {
    border-bottom: 0;
}

.information-row dt {
    color:
        var(--sh-text-3);

    font-size: 9px;
    font-weight: 650;
}

.information-row dd {
    min-width: 0;

    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 9.5px;
    font-weight: 650;

    text-align: right;
}

.code-value {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size:
        8px !important;
}

.phone-value {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
}

.capitalize-value {
    text-transform:
        capitalize;
}


/*
|--------------------------------------------------------------------------
| RETRY STATE
|--------------------------------------------------------------------------
*/

.retry-state {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 750;
}

.retry-state.safe {
    color:
        var(--sh-success);
}

.retry-state.blocked {
    color:
        var(--sh-warning);
}

.retry-state svg {
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


/*
|--------------------------------------------------------------------------
| PROVIDER
|--------------------------------------------------------------------------
*/

.provider-overview {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        11px
        12px;

    border:
        1px solid
        #eadfbf;

    border-radius: 11px;

    background:
        var(--sh-accent-soft);
}

.provider-logo {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        #ffffff;

    color:
        var(--sh-accent);

    font-size: 12px;
    font-weight: 800;
}

.provider-overview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.provider-overview-copy span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.provider-overview-copy strong {
    color:
        var(--sh-text);

    font-size: 10px;

    text-transform:
        capitalize;
}

.provider-status-chip {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border:
        1px solid
        rgba(
            214,
            154,
            45,
            0.2
        );

    border-radius: 999px;

    background:
        #ffffff;

    color:
        #805a1e;

    font-size: 7.5px;
    font-weight: 750;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| PROVIDER ERROR
|--------------------------------------------------------------------------
*/

.provider-error-panel {
    margin:
        5px
        15px
        15px;

    padding:
        11px
        12px;

    border:
        1px solid
        #eed3d3;

    border-radius: 11px;

    background:
        var(--sh-danger-bg);
}

.provider-error-heading {
    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-danger);

    font-size: 8px;
    font-weight: 800;

    text-transform:
        uppercase;

    letter-spacing: 0.5px;
}

.provider-error-heading svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.provider-error-panel p {
    margin:
        6px
        0
        0;

    overflow-wrap: anywhere;

    color:
        #865252;

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| ENTITY BUTTONS
|--------------------------------------------------------------------------
*/

.entity-link-button {
    min-height: 33px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 5px;

    padding:
        0
        9px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-text-2);

    font: inherit;

    font-size: 8px;
    font-weight: 750;

    cursor: pointer;
}

.entity-link-button:hover {
    border-color:
        #d5dbe2;

    background:
        var(--sh-surface-muted);
}

.entity-link-button svg {
    width: 11px;
    height: 11px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.9;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.inline-link-button {
    padding: 0;

    border: 0;

    background:
        transparent;

    color:
        var(--sh-info);

    font: inherit;

    font-size: inherit;
    font-weight: 750;

    cursor: pointer;
}

.inline-link-button:hover {
    text-decoration:
        underline;
}


/*
|--------------------------------------------------------------------------
| RECIPIENT
|--------------------------------------------------------------------------
*/

.recipient-preview {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        11px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.recipient-avatar {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);

    font-size: 12px;
    font-weight: 800;
}

.recipient-avatar.system {
    background:
        #f0f2f4;

    color:
        var(--sh-text-3);
}

.recipient-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.recipient-preview-copy span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;

    text-transform:
        uppercase;
}

.recipient-preview-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.recipient-preview-copy small {
    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| ROLE
|--------------------------------------------------------------------------
*/

.role-badge.customer {
    border-color:
        #dce6f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.role-badge.shop-owner {
    border-color:
        #e4ddf8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT
|--------------------------------------------------------------------------
*/

.appointment-preview {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        11px
        12px;

    border:
        1px solid
        #e3dcf8;

    border-radius: 11px;

    background:
        var(--sh-purple-bg);
}

.appointment-preview-icon {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        #ffffff;

    color:
        var(--sh-purple);
}

.appointment-preview-icon svg {
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

.appointment-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.appointment-preview-copy span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.appointment-preview-copy strong {
    color:
        var(--sh-text);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 11px;
}

.appointment-preview-copy small {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}


/*
|--------------------------------------------------------------------------
| LINKED SHOP
|--------------------------------------------------------------------------
*/

.linked-shop-card {
    width:
        calc(
            100% - 30px
        );

    display: flex;
    align-items: center;

    gap: 9px;

    margin:
        5px
        15px
        15px;

    padding:
        10px
        11px;

    border:
        1px solid
        #eadfbf;

    border-radius: 11px;

    background:
        var(--sh-accent-soft);

    color:
        inherit;

    font: inherit;

    text-align: left;

    cursor: pointer;
}

.linked-shop-card:hover {
    border-color:
        #dfca99;
}

.linked-shop-avatar {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        #ffffff;

    color:
        var(--sh-accent);

    font-size: 10px;
    font-weight: 800;
}

.linked-shop-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.linked-shop-copy span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.linked-shop-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 9px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.linked-shop-card
> svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        #98702b;

    stroke-width: 1.9;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| EMPTY APPOINTMENT
|--------------------------------------------------------------------------
*/

.empty-context-card {
    min-height: 210px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 25px;

    text-align: center;
}

.empty-context-icon {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 9px;

    border-radius: 13px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.empty-context-icon svg {
    width: 20px;
    height: 20px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.empty-context-card strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.empty-context-card span {
    max-width: 330px;

    margin-top: 4px;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| MESSAGE CARD
|--------------------------------------------------------------------------
*/

.sms-message-card {
    padding: 0;

    overflow: hidden;
}

.message-card-header {
    min-height: 72px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        13px
        15px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.message-card-header
.information-heading
> div:last-child
> p:last-child {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8px;
}

.snapshot-badge {
    min-height: 24px;

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
        #845e20;

    font-size: 7.5px;
    font-weight: 800;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| MESSAGE PREVIEW
|--------------------------------------------------------------------------
*/

.message-preview {
    display: grid;

    grid-template-columns:
        minmax(
            250px,
            0.7fr
        )
        minmax(
            0,
            1.3fr
        );

    gap: 18px;

    padding:
        18px;
}

.message-preview-phone {
    width: 100%;
    max-width: 330px;

    min-height: 260px;

    margin:
        0
        auto;

    padding:
        12px;

    border:
        1px solid
        #dfe3e8;

    border-radius: 23px;

    background:
        #f7f8fa;

    box-shadow:
        inset
        0
        0
        0
        4px
        #ffffff;
}

.phone-speaker {
    width: 44px;
    height: 4px;

    margin:
        2px
        auto
        15px;

    border-radius: 999px;

    background:
        #d6dae0;
}

.phone-message-header {
    display: flex;
    align-items: center;

    gap: 8px;

    padding:
        0
        5px
        11px;

    border-bottom:
        1px solid
        #e2e5e9;
}

.message-sender-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 999px;

    background:
        var(--sh-primary);

    color:
        var(--sh-accent);

    font-size: 9px;
    font-weight: 800;
}

.phone-message-header
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.phone-message-header strong {
    color:
        var(--sh-text);

    font-size: 9px;
}

.phone-message-header span {
    color:
        var(--sh-text-3);

    font-size: 7px;
}

.message-bubble {
    margin:
        18px
        4px;

    padding:
        11px
        12px;

    border-radius:
        13px
        13px
        13px
        3px;

    background:
        #ffffff;

    color:
        var(--sh-text-2);

    font-size: 9px;

    line-height: 16px;

    white-space:
        pre-wrap;

    overflow-wrap:
        anywhere;

    box-shadow:
        var(--sh-shadow-sm);
}


/*
|--------------------------------------------------------------------------
| MESSAGE INFORMATION
|--------------------------------------------------------------------------
*/

.message-record-information {
    display: flex;
    flex-direction: column;

    gap: 0;

    border:
        1px solid
        var(--sh-border);

    border-radius: 12px;

    overflow: hidden;

    background:
        var(--sh-surface-muted);
}

.message-info-row {
    min-height: 51px;

    display: grid;

    grid-template-columns:
        120px
        minmax(
            0,
            1fr
        );

    align-items: center;

    gap: 13px;

    padding:
        10px
        12px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.message-info-row span,
.dedupe-information > span {
    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 650;
}

.message-info-row strong {
    min-width: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 9px;

    text-align: right;
}

.dedupe-information {
    display: flex;
    flex-direction: column;

    gap: 6px;

    padding:
        12px;
}

.dedupe-information code {
    display: block;

    padding:
        9px
        10px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 8px;

    background:
        #ffffff;

    color:
        #5b6470;

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7.5px;

    line-height: 13px;

    overflow-wrap:
        anywhere;
}


/*
|--------------------------------------------------------------------------
| RETRY MODAL
|--------------------------------------------------------------------------
*/

.controlled-retry-modal {
    width:
        min(
            530px,
            100%
        );

    padding: 20px;
}

.retry-modal-heading {
    display: flex;
    align-items: center;

    gap: 11px;
}

.retry-modal-icon {
    width: 43px;
    height: 43px;

    flex: 0 0 43px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.retry-modal-icon svg {
    width: 20px;
    height: 20px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.retry-modal-heading
> div:last-child
> span {
    display: block;

    margin-bottom: 2px;

    color:
        var(--sh-success);

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.9px;
}

.retry-modal-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 15px;
    font-weight: 800;
}

.retry-modal-description {
    margin:
        13px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9.5px;

    line-height: 16px;
}


/*
|--------------------------------------------------------------------------
| RETRY SAFETY CONFIRMATION
|--------------------------------------------------------------------------
*/

.retry-safety-confirmation {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin-top: 14px;

    padding:
        11px
        12px;

    border:
        1px solid
        #d2e8dc;

    border-radius: 10px;

    background:
        var(--sh-success-bg);
}

.retry-safety-confirmation
> svg {
    width: 18px;
    height: 18px;

    flex: 0 0 18px;

    fill: none;

    stroke:
        var(--sh-success);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.retry-safety-confirmation
> div {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.retry-safety-confirmation strong {
    color:
        var(--sh-success);

    font-size: 8.5px;
}

.retry-safety-confirmation span {
    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| RETRY RECORD SUMMARY
|--------------------------------------------------------------------------
*/

.retry-record-summary {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 8px;

    margin-top: 13px;
}

.retry-record-summary > div {
    min-width: 0;

    padding:
        10px
        11px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 9px;

    background:
        var(--sh-surface-muted);
}

.retry-record-summary span {
    display: block;

    margin-bottom: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.retry-record-summary strong {
    display: block;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| MODAL BUTTON
|--------------------------------------------------------------------------
*/

.confirm-retry-button {
    min-height: 38px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 6px;

    min-width: 125px;

    padding:
        0
        14px;

    border:
        1px solid
        var(--sh-success);

    border-radius: 9px;

    background:
        var(--sh-success);

    color:
        #ffffff;

    font: inherit;

    font-size: 9px;
    font-weight: 750;

    cursor: pointer;
}

.confirm-retry-button:hover:not(:disabled) {
    background:
        #22664d;
}

.confirm-retry-button:disabled {
    cursor: not-allowed;

    opacity: 0.6;
}

.confirm-retry-button svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.button-spinner {
    width: 13px;
    height: 13px;

    border:
        2px solid
        currentColor;

    border-right-color:
        transparent;

    border-radius: 999px;

    animation:
        retry-button-spin
        0.7s
        linear
        infinite;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        sms-detail-spin
        0.8s
        linear
        infinite;
}

@keyframes sms-detail-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}

@keyframes retry-button-spin {
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
    .sms-record-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .sms-hero-actions {
        width: 100%;

        align-items:
            flex-start;
    }

    .sms-hero-statuses {
        justify-content:
            flex-start;
    }
}


@media (max-width: 1000px) {
    .sms-detail-summary-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .sms-information-grid {
        grid-template-columns:
            1fr;
    }
}


@media (max-width: 800px) {
    .message-preview {
        grid-template-columns:
            1fr;
    }

    .message-preview-phone {
        max-width:
            360px;
    }
}


@media (max-width: 680px) {
    .detail-navigation {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .detail-navigation
    .back-button,
    .detail-navigation
    .refresh-detail-button {
        width: 100%;
    }

    .detail-alert {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .detail-alert
    .retry-button {
        width: 100%;
    }

    .sms-record-hero {
        padding: 17px;
    }

    .sms-record-main {
        align-items:
            flex-start;
    }

    .sms-record-icon {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;
    }

    .sms-record-icon svg {
        width: 22px;
        height: 22px;
    }

    .sms-record-copy h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .sms-record-meta {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .sms-meta-divider {
        display: none;
    }

    .sms-hero-actions {
        align-items:
            stretch;
    }

    .sms-hero-statuses {
        justify-content:
            flex-start;
    }

    .controlled-retry-button {
        width: 100%;
    }

    .retry-safety-panel {
        flex-direction:
            column;
    }

    .retry-safety-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .sms-detail-summary-grid {
        grid-template-columns:
            1fr;
    }

    .information-card-header {
        align-items:
            flex-start;
    }

    .information-heading {
        align-items:
            flex-start;
    }

    .entity-link-button {
        flex: 0 0 auto;
    }

    .delivery-overview,
    .provider-overview,
    .recipient-preview,
    .appointment-preview {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .information-row {
        grid-template-columns:
            1fr;

        gap: 4px;

        align-items:
            flex-start;
    }

    .information-row dd {
        text-align: left;
    }

    .message-card-header {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .snapshot-badge {
        margin-left: 46px;
    }

    .message-preview {
        padding: 14px;
    }

    .message-info-row {
        grid-template-columns:
            1fr;

        gap: 4px;
    }

    .message-info-row strong {
        text-align: left;
    }

    .retry-record-summary {
        grid-template-columns:
            1fr;
    }

    .modal-actions {
        width: 100%;

        align-items:
            stretch;
    }

    .modal-actions button {
        width: 100%;
    }
}
</style>