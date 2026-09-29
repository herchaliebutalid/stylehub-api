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

const feedback = ref(null);

const isLoading = ref(false);
const errorMessage = ref('');

const feedbackId = computed(
    () =>
        route.params
            .feedbackId,
);


/*
|--------------------------------------------------------------------------
| RATING PRESENTATION
|--------------------------------------------------------------------------
*/

const ratingLabel = computed(() => {
    const rating =
        Number(
            feedback.value
                ?.rating
            ?? 0,
        );

    if (rating >= 5) {
        return 'Excellent';
    }

    if (rating >= 4) {
        return 'Very Good';
    }

    if (rating >= 3) {
        return 'Good';
    }

    if (rating >= 2) {
        return 'Fair';
    }

    if (rating >= 1) {
        return 'Poor';
    }

    return 'No Rating';
});

const ratingPercentage = computed(() => {
    const rating =
        Number(
            feedback.value
                ?.rating
            ?? 0,
        );

    return Math.min(
        100,
        Math.max(
            0,
            (
                rating
                / 5
            )
            * 100,
        ),
    );
});


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function humanize(
    value,
) {
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

function formatDate(
    value,
) {
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

function formatDateTime(
    value,
) {
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

            timeZone:
                'Asia/Manila',
        },
    ).format(date);
}

function formatTime(
    value,
) {
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

    const hour =
        Number(
            parts[0],
        );

    const minute =
        Number(
            parts[1],
        );

    if (
        !Number.isInteger(hour)
        ||
        !Number.isInteger(minute)
    ) {
        return value;
    }

    const date =
        new Date(
            2000,
            0,
            1,
            hour,
            minute,
        );

    return new Intl.DateTimeFormat(
        'en-PH',
        {
            hour:
                'numeric',

            minute:
                '2-digit',
        },
    ).format(date);
}

function statusClass(
    value,
) {
    return String(
        value ?? '',
    )
        .toLowerCase()
        .replaceAll(
            '_',
            '-',
        );
}

function customerInitial() {
    const value =
        feedback.value
            ?.customer
            ?.full_name
        ||
        feedback.value
            ?.customer
            ?.email
        ||
        'C';

    return String(value)
        .charAt(0)
        .toUpperCase();
}

function shopInitial() {
    const value =
        feedback.value
            ?.shop
            ?.name
        || 'S';

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
| LOAD FEEDBACK
|--------------------------------------------------------------------------
*/

async function loadFeedback() {
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
                `/admin/feedback/${encodeURIComponent(
                    feedbackId.value,
                )}`,
                {
                    token:
                        auth.token,
                },
            );

        feedback.value =
            response
                ?.data
                ?.feedback
            ?? null;

        if (
            !feedback.value
        ) {
            throw new Error(
                'The feedback response was invalid.',
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

        if (
            error?.status === 404
        ) {
            errorMessage.value =
                'The requested feedback record could not be found.';
        } else {
            errorMessage.value =
                error
                    ?.data
                    ?.message
                ??
                error
                    ?.message
                ??
                'Unable to load feedback.';
        }
    } finally {
        isLoading.value =
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
            'admin.feedback',
    });
}

async function openCustomer() {
    if (
        !feedback.value
            ?.customer
            ?.id
    ) {
        return;
    }

    await router.push({
        name:
            'admin.user.detail',

        params: {
            userId:
                feedback.value
                    .customer
                    .id,
        },
    });
}

async function openShop() {
    if (
        !feedback.value
            ?.shop
            ?.id
    ) {
        return;
    }

    await router.push({
        name:
            'admin.shop.detail',

        params: {
            shopId:
                feedback.value
                    .shop
                    .id,
        },
    });
}

async function openAppointment() {
    if (
        !feedback.value
            ?.appointment
            ?.id
    ) {
        return;
    }

    await router.push({
        name:
            'admin.appointment.detail',

        params: {
            appointmentId:
                feedback.value
                    .appointment
                    .id,
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
            .feedbackId,

    () => {
        loadFeedback();
    },
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadFeedback();
});
</script>


<template>
    <div class="detail-page feedback-detail-page">
        <!-- ============================================================
             TOP NAVIGATION
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
                    Back to feedback
                </span>
            </button>


            <button
                v-if="feedback"
                type="button"
                class="secondary-button refresh-detail-button"
                :disabled="isLoading"
                @click="loadFeedback"
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
             ERROR
             ============================================================ -->

        <div
            v-if="errorMessage"
            class="error-alert feedback-error"
            role="alert"
        >
            <div class="feedback-error-icon">
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

            <div class="feedback-error-copy">
                <strong>
                    Unable to load feedback
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="loadFeedback"
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             LOADING
             ============================================================ -->

        <div
            v-if="
                isLoading
                && !feedback
            "
            class="loading-card feedback-loading-card"
        >
            <div class="feedback-loading-icon">
                <div class="spinner" />
            </div>

            <h3>
                Loading feedback
            </h3>

            <p>
                Retrieving the customer review and
                related booking information.
            </p>
        </div>


        <!-- ============================================================
             CONTENT
             ============================================================ -->

        <template v-else-if="feedback">
            <!-- ========================================================
                 HERO
                 ======================================================== -->

            <section class="feedback-profile-hero">
                <div class="feedback-profile-main">
                    <div class="feedback-hero-icon">
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

                    <div class="feedback-profile-copy">
                        <div class="feedback-eyebrow">
                            <span class="feedback-eyebrow-dot" />

                            Customer review

                            <span class="feedback-id">
                                #{{ feedback.id }}
                            </span>
                        </div>

                        <h2>
                            Feedback #{{ feedback.id }}
                        </h2>

                        <div class="feedback-meta">
                            <div class="feedback-meta-item">
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

                                <span>
                                    Submitted
                                    {{
                                        formatDateTime(
                                            feedback.created_at
                                        )
                                    }}
                                </span>
                            </div>

                            <span class="feedback-meta-divider" />

                            <div class="feedback-meta-item">
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

                                <span>
                                    {{
                                        feedback.shop
                                            ?.name
                                        || 'Shop unavailable'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- RATING -->

                <div class="hero-rating-card">
                    <div class="hero-rating-number">
                        {{ feedback.rating }}
                    </div>

                    <div class="hero-rating-copy">
                        <div class="stars hero-stars">
                            <span
                                v-for="star in 5"
                                :key="star"
                                :class="{
                                    filled:
                                        star
                                        <= feedback.rating,
                                }"
                            >
                                ★
                            </span>
                        </div>

                        <strong>
                            {{ ratingLabel }}
                        </strong>

                        <span>
                            {{ feedback.rating }} out of 5
                        </span>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 READ ONLY
                 ======================================================== -->

            <section class="feedback-readonly-banner">
                <div class="readonly-icon-wrap">
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

                <div class="readonly-copy">
                    <strong>
                        Read-only feedback record
                    </strong>

                    <p>
                        This review is displayed for administrative
                        monitoring. The Administrator portal does
                        not provide feedback editing or deletion.
                    </p>
                </div>

                <span class="readonly-status">
                    Monitoring only
                </span>
            </section>


            <!-- ========================================================
                 REVIEW CARD
                 ======================================================== -->

            <section class="comment-card feedback-comment-card">
                <div class="comment-header">
                    <div>
                        <p class="eyebrow">
                            CUSTOMER COMMENT
                        </p>

                        <h3>
                            Customer review
                        </h3>
                    </div>

                    <span class="review-submitted-date">
                        {{
                            formatDateTime(
                                feedback.created_at
                            )
                        }}
                    </span>
                </div>


                <div class="review-overview">
                    <div class="large-rating-number">
                        {{ feedback.rating }}
                    </div>

                    <div class="review-rating-information">
                        <div class="stars large">
                            <span
                                v-for="star in 5"
                                :key="star"
                                :class="{
                                    filled:
                                        star
                                        <= feedback.rating,
                                }"
                            >
                                ★
                            </span>
                        </div>

                        <strong>
                            {{ ratingLabel }}
                        </strong>

                        <span>
                            Customer rating
                        </span>
                    </div>

                    <div class="review-score-progress">
                        <div class="score-progress-heading">
                            <span>
                                Rating score
                            </span>

                            <strong>
                                {{
                                    Math.round(
                                        ratingPercentage
                                    )
                                }}%
                            </strong>
                        </div>

                        <div class="score-progress-track">
                            <div
                                class="score-progress-fill"
                                :style="{
                                    width:
                                        `${
                                            ratingPercentage
                                        }%`,
                                }"
                            />
                        </div>
                    </div>
                </div>


                <div class="customer-comment">
                    <div class="quote-mark">
                        “
                    </div>

                    <p>
                        {{
                            feedback.comment
                            || 'No written comment was provided.'
                        }}
                    </p>
                </div>
            </section>


            <!-- ========================================================
                 INFORMATION GRID
                 ======================================================== -->

            <section class="feedback-information-grid">
                <!-- CUSTOMER -->

                <article class="detail-card feedback-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon customer">
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
                                <p class="eyebrow">
                                    CUSTOMER
                                </p>

                                <h3>
                                    Customer information
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                feedback.customer
                                    ?.id
                            "
                            type="button"
                            class="link-button"
                            @click="openCustomer"
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


                    <div class="entity-preview customer-preview">
                        <div class="customer-avatar">
                            {{
                                customerInitial()
                            }}
                        </div>

                        <div class="entity-preview-copy">
                            <strong>
                                {{
                                    feedback.customer
                                        ?.full_name
                                    || 'Unavailable'
                                }}
                            </strong>

                            <span>
                                {{
                                    feedback.customer
                                        ?.email
                                    || 'No email'
                                }}
                            </span>
                        </div>

                        <span
                            class="status-badge"
                            :class="
                                statusClass(
                                    feedback.customer
                                        ?.status
                                )
                            "
                        >
                            <span class="status-indicator" />

                            {{
                                humanize(
                                    feedback.customer
                                        ?.status
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Full name
                            </dt>

                            <dd>
                                {{
                                    feedback.customer
                                        ?.full_name
                                    || 'Unavailable'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Email
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    feedback.customer
                                        ?.email
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Account status
                            </dt>

                            <dd>
                                <span
                                    class="status-badge"
                                    :class="
                                        statusClass(
                                            feedback.customer
                                                ?.status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            feedback.customer
                                                ?.status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- SHOP -->

                <article class="detail-card feedback-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon shop">
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
                                    SHOP
                                </p>

                                <h3>
                                    Shop information
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                feedback.shop
                                    ?.id
                            "
                            type="button"
                            class="link-button"
                            @click="openShop"
                        >
                            <span>
                                View shop
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


                    <div class="entity-preview shop-preview">
                        <div class="shop-avatar">
                            {{
                                shopInitial()
                            }}
                        </div>

                        <div class="entity-preview-copy">
                            <strong>
                                {{
                                    feedback.shop
                                        ?.name
                                    || 'Unavailable'
                                }}
                            </strong>

                            <span>
                                {{
                                    feedback.shop
                                        ?.city
                                    || 'Location unavailable'
                                }},
                                {{
                                    feedback.shop
                                        ?.province
                                    || '—'
                                }}
                            </span>
                        </div>

                        <span
                            class="status-badge"
                            :class="
                                statusClass(
                                    feedback.shop
                                        ?.approval_status
                                )
                            "
                        >
                            {{
                                humanize(
                                    feedback.shop
                                        ?.approval_status
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Shop name
                            </dt>

                            <dd>
                                {{
                                    feedback.shop
                                        ?.name
                                    || 'Unavailable'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                City
                            </dt>

                            <dd>
                                {{
                                    feedback.shop
                                        ?.city
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Province
                            </dt>

                            <dd>
                                {{
                                    feedback.shop
                                        ?.province
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Approval status
                            </dt>

                            <dd>
                                <span
                                    class="status-badge"
                                    :class="
                                        statusClass(
                                            feedback.shop
                                                ?.approval_status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            feedback.shop
                                                ?.approval_status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- APPOINTMENT -->

                <article class="detail-card feedback-information-card">
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

                                    <path
                                        d="M8 14h3M14 14h2"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    APPOINTMENT
                                </p>

                                <h3>
                                    Related booking
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                feedback.appointment
                                    ?.id
                            "
                            type="button"
                            class="link-button"
                            @click="openAppointment"
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


                    <div
                        v-if="
                            feedback.appointment
                                ?.id
                        "
                        class="appointment-preview"
                    >
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
                                    feedback.appointment
                                        .id
                                }}
                            </strong>
                        </div>

                        <span
                            class="status-badge"
                            :class="
                                statusClass(
                                    feedback.appointment
                                        ?.status
                                )
                            "
                        >
                            <span class="status-indicator" />

                            {{
                                humanize(
                                    feedback.appointment
                                        ?.status
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Appointment ID
                            </dt>

                            <dd>
                                <template
                                    v-if="
                                        feedback.appointment
                                            ?.id
                                    "
                                >
                                    #{{
                                        feedback.appointment
                                            .id
                                    }}
                                </template>

                                <template v-else>
                                    —
                                </template>
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Status
                            </dt>

                            <dd>
                                <span
                                    v-if="
                                        feedback.appointment
                                            ?.status
                                    "
                                    class="status-badge"
                                    :class="
                                        statusClass(
                                            feedback.appointment
                                                ?.status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            feedback.appointment
                                                ?.status
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
                                Appointment date
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        feedback.appointment
                                            ?.appointment_date
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Start time
                            </dt>

                            <dd>
                                {{
                                    formatTime(
                                        feedback.appointment
                                            ?.start_time
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- SYSTEM RECORD -->

                <article class="detail-card feedback-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon record">
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
                                <p class="eyebrow">
                                    SYSTEM RECORD
                                </p>

                                <h3>
                                    Feedback information
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="record-rating-preview">
                        <div class="record-rating-icon">
                            <span>
                                ★
                            </span>
                        </div>

                        <div>
                            <span>
                                Customer rating
                            </span>

                            <strong>
                                {{ feedback.rating }}/5
                            </strong>
                        </div>

                        <span class="record-rating-label">
                            {{ ratingLabel }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Feedback ID
                            </dt>

                            <dd class="record-id">
                                #{{ feedback.id }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Rating
                            </dt>

                            <dd>
                                {{ feedback.rating }}/5
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Submitted
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        feedback.created_at
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Last updated
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        feedback.updated_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
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

.feedback-detail-page {
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

.feedback-detail-page
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
| ERROR
|--------------------------------------------------------------------------
*/

.feedback-error {
    align-items: center;

    margin: 0;
}

.feedback-error-icon {
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

.feedback-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.feedback-error-copy {
    min-width: 0;

    flex: 1;
}

.feedback-error-copy strong {
    display: block;

    font-size: 10px;
}

.feedback-error-copy p {
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

.feedback-loading-card {
    min-height: 360px;
}

.feedback-loading-icon {
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

.feedback-loading-icon
.spinner {
    margin: 0;
}


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

.feedback-profile-hero {
    position: relative;

    overflow: hidden;

    min-height: 168px;

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
                0.09
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

.feedback-profile-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.feedback-hero-icon {
    width: 66px;
    height: 66px;

    flex: 0 0 66px;

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
            0.12
        );
}

.feedback-hero-icon svg {
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

.feedback-profile-copy {
    min-width: 0;

    flex: 1;
}

.feedback-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.feedback-eyebrow-dot {
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

.feedback-id {
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

.feedback-profile-copy h2 {
    margin: 0;

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

.feedback-meta {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.feedback-meta-item {
    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.feedback-meta-item svg {
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

.feedback-meta-divider {
    width: 3px;
    height: 3px;

    flex: 0 0 3px;

    border-radius: 999px;

    background:
        #c7ccd3;
}


/*
|--------------------------------------------------------------------------
| HERO RATING
|--------------------------------------------------------------------------
*/

.hero-rating-card {
    min-width: 210px;

    display: flex;
    align-items: center;

    gap: 12px;

    padding:
        13px
        15px;

    border:
        1px solid
        #eadfbf;

    border-radius: 13px;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            var(--sh-accent-soft)
        );
}

.hero-rating-number {
    color:
        var(--sh-text);

    font-size: 34px;
    font-weight: 800;

    line-height: 38px;

    letter-spacing:
        -1px;
}

.hero-rating-copy {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.hero-rating-copy strong {
    color:
        #7e5a20;

    font-size: 10px;
    font-weight: 800;
}

.hero-rating-copy > span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.stars {
    display: flex;
    align-items: center;

    gap: 2px;
}

.stars span {
    color:
        #d8dce1;
}

.stars span.filled {
    color:
        var(--sh-accent);
}

.hero-stars span {
    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| READ ONLY BANNER
|--------------------------------------------------------------------------
*/

.feedback-readonly-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #d7e2f7;

    border-radius: 13px;

    background:
        var(--sh-info-bg);

    box-shadow:
        var(--sh-shadow-sm);
}

.readonly-icon-wrap {
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
        var(--sh-info);
}

.readonly-icon-wrap svg {
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

.readonly-copy {
    min-width: 0;

    flex: 1;
}

.readonly-copy strong {
    display: block;

    color:
        #31558e;

    font-size: 9.5px;
}

.readonly-copy p {
    max-width: 800px;

    margin:
        3px
        0
        0;

    color:
        #607ca9;

    font-size: 8.5px;

    line-height: 14px;
}

.readonly-status {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #ccdaf1;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-info);

    font-size: 7.5px;
    font-weight: 800;

    text-transform: uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| COMMENT CARD
|--------------------------------------------------------------------------
*/

.feedback-comment-card {
    padding: 20px;
}

.comment-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 12px;
}

.comment-header h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 15px;
    font-weight: 800;
}

.review-submitted-date {
    color:
        var(--sh-text-3);

    font-size: 8px;

    white-space: nowrap;
}

.review-overview {
    display: grid;

    grid-template-columns:
        auto
        auto
        minmax(
            180px,
            1fr
        );

    align-items: center;

    gap: 17px;

    margin-top: 18px;

    padding:
        15px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface-muted);
}

.large-rating-number {
    color:
        var(--sh-text);

    font-size: 42px;
    font-weight: 800;

    line-height: 45px;

    letter-spacing:
        -1.3px;
}

.review-rating-information {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.stars.large span {
    font-size: 17px;
}

.review-rating-information strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 10px;
}

.review-rating-information > span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.review-score-progress {
    min-width: 0;

    margin-left: auto;
}

.score-progress-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    margin-bottom: 7px;

    color:
        var(--sh-text-3);

    font-size: 8px;
}

.score-progress-heading strong {
    color:
        #805a1e;

    font-size: 8.5px;
}

.score-progress-track {
    width: 100%;
    height: 6px;

    overflow: hidden;

    border-radius: 999px;

    background:
        #e8ebef;
}

.score-progress-fill {
    height: 100%;

    border-radius: inherit;

    background:
        var(--sh-accent);
}


/*
|--------------------------------------------------------------------------
| COMMENT
|--------------------------------------------------------------------------
*/

.customer-comment {
    position: relative;

    margin-top: 14px;

    padding:
        17px
        18px
        17px
        46px;

    border-left:
        3px solid
        var(--sh-accent);

    border-radius:
        0
        12px
        12px
        0;

    background:
        #fbfaf7;
}

.quote-mark {
    position: absolute;

    top: 7px;
    left: 13px;

    color:
        rgba(
            214,
            154,
            45,
            0.42
        );

    font-family:
        Georgia,
        serif;

    font-size: 38px;

    line-height: 1;
}

.customer-comment p {
    margin: 0;

    white-space: pre-wrap;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 10.5px;

    line-height: 18px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION GRID
|--------------------------------------------------------------------------
*/

.feedback-information-grid {
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

.feedback-information-card {
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

.information-icon.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.information-icon.shop {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.information-icon.appointment {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.information-icon.record {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.information-card-header
.eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.information-card-header
h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| LINK BUTTON
|--------------------------------------------------------------------------
*/

.feedback-information-card
.link-button {
    min-height: 33px;

    gap: 5px;

    white-space: nowrap;
}

.feedback-information-card
.link-button svg {
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
| ENTITY PREVIEW
|--------------------------------------------------------------------------
*/

.entity-preview {
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

.customer-avatar,
.shop-avatar {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    font-size: 12px;
    font-weight: 800;
}

.customer-avatar {
    border:
        1px solid
        #dce6f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.shop-avatar {
    border:
        1px solid
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #835c1d;
}

.entity-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.entity-preview-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.entity-preview-copy span {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.status-indicator {
    width: 5px;
    height: 5px;

    flex: 0 0 5px;

    border-radius: 999px;

    background:
        currentColor;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT PREVIEW
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
        #e4def8;

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


/*
|--------------------------------------------------------------------------
| SYSTEM RATING
|--------------------------------------------------------------------------
*/

.record-rating-preview {
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

.record-rating-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;
}

.record-rating-icon span {
    color:
        var(--sh-accent);

    font-size: 18px;
}

.record-rating-preview
> div:nth-child(2) {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.record-rating-preview
> div:nth-child(2)
span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.record-rating-preview
> div:nth-child(2)
strong {
    color:
        var(--sh-text);

    font-size: 12px;
}

.record-rating-label {
    min-height: 23px;

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
    font-weight: 800;

    text-transform: uppercase;
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

    font-size: 10px;
    font-weight: 650;

    text-align: right;
}

.record-id {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT STATUS
|--------------------------------------------------------------------------
*/

.status-badge.confirmed {
    border-color:
        #d9e4f8 !important;

    background:
        var(--sh-info-bg) !important;

    color:
        var(--sh-info) !important;
}

.status-badge.pending {
    border-color:
        #f0dfb9 !important;

    background:
        var(--sh-warning-bg) !important;

    color:
        var(--sh-warning) !important;
}

.status-badge.completed,
.status-badge.approved,
.status-badge.active {
    border-color:
        #d4ebdf !important;

    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.status-badge.cancelled,
.status-badge.declined,
.status-badge.rejected,
.status-badge.suspended {
    border-color:
        #eed3d3 !important;

    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        feedback-spin
        0.8s
        linear
        infinite;
}

@keyframes feedback-spin {
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

@media (max-width: 1000px) {
    .feedback-profile-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .hero-rating-card {
        width: 100%;
    }
}


@media (max-width: 900px) {
    .feedback-information-grid {
        grid-template-columns:
            1fr;
    }

    .review-overview {
        grid-template-columns:
            auto
            1fr;
    }

    .review-score-progress {
        grid-column:
            1
            / -1;

        width: 100%;

        margin-left: 0;
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

    .feedback-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .feedback-error
    .retry-button {
        width: 100%;
    }

    .feedback-profile-hero {
        padding: 17px;
    }

    .feedback-profile-main {
        align-items:
            flex-start;
    }

    .feedback-hero-icon {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;
    }

    .feedback-hero-icon svg {
        width: 22px;
        height: 22px;
    }

    .feedback-profile-copy h2 {
        font-size: 20px;

        line-height: 25px;
    }

    .feedback-meta {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .feedback-meta-divider {
        display: none;
    }

    .feedback-readonly-banner {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .readonly-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .readonly-status {
        margin-left: 48px;
    }

    .feedback-comment-card {
        padding: 16px;
    }

    .comment-header {
        flex-direction:
            column;
    }

    .review-overview {
        grid-template-columns:
            auto
            1fr;

        gap: 12px;
    }

    .large-rating-number {
        font-size: 34px;
    }

    .customer-comment {
        padding:
            16px
            15px
            16px
            42px;
    }

    .information-card-header {
        align-items:
            flex-start;
    }

    .information-heading {
        align-items:
            flex-start;
    }

    .entity-preview,
    .appointment-preview,
    .record-rating-preview {
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

    .feedback-information-card
    .link-button {
        flex: 0 0 auto;
    }
}
</style>