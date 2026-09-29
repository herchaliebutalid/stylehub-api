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

const appointment = ref(null);

const isLoading = ref(false);
const errorMessage = ref('');

const appointmentId = computed(
    () =>
        route.params
            .appointmentId,
);

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
            month: 'long',
            day: 'numeric',
            timeZone: 'Asia/Manila',
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
        Number(parts[0]);

    const minutes =
        Number(parts[1]);

    if (
        !Number.isInteger(hours)
        || !Number.isInteger(minutes)
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

function formatCurrency(value) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return '—';
    }

    const amount =
        Number(value);

    if (
        !Number.isFinite(amount)
    ) {
        return String(value);
    }

    return new Intl.NumberFormat(
        'en-PH',
        {
            style: 'currency',
            currency: 'PHP',
        },
    ).format(amount);
}

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

async function loadAppointment() {
    if (!auth.token) {
        await authenticationFailure();

        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response =
            await apiRequest(
                `/admin/appointments/${encodeURIComponent(appointmentId.value)}`,
                {
                    token: auth.token,
                },
            );

        appointment.value =
            response?.data
                ?.appointment
            ?? null;

        if (!appointment.value) {
            throw new Error(
                'The appointment response was invalid.',
            );
        }
    } catch (error) {
        if (
            error?.status === 401
            || error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status === 404
        ) {
            errorMessage.value =
                'The requested appointment could not be found.';
        } else {
            errorMessage.value =
                error?.data?.message
                ?? error?.message
                ?? 'Unable to load appointment.';
        }
    } finally {
        isLoading.value = false;
    }
}

async function goBack() {
    await router.push({
        name: 'admin.appointments',
    });
}

async function openCustomer() {
    if (
        !appointment.value
            ?.customer
            ?.id
    ) {
        return;
    }

    await router.push({
        name: 'admin.user.detail',

        params: {
            userId:
                appointment.value
                    .customer
                    .id,
        },
    });
}

async function openShop() {
    if (
        !appointment.value
            ?.shop
            ?.id
    ) {
        return;
    }

    await router.push({
        name: 'admin.shop.detail',

        params: {
            shopId:
                appointment.value
                    .shop
                    .id,
        },
    });
}

watch(
    () =>
        route.params
            .appointmentId,

    () => {
        loadAppointment();
    },
);

onMounted(() => {
    loadAppointment();
});
</script>

<template>
    <div class="detail-page">
        <button
            type="button"
            class="back-button"
            @click="
                goBack
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

            Back to Appointments
        </button>

        <div
            v-if="
                errorMessage
            "
            class="error-alert"
            role="alert"
        >
            <div>
                <strong>
                    Unable to load appointment
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                @click="
                    loadAppointment
                "
            >
                Try again
            </button>
        </div>

        <div
            v-if="
                isLoading
                && !appointment
            "
            class="loading-card"
        >
            <div class="spinner"></div>

            <p>
                Loading appointment...
            </p>
        </div>

        <template
            v-else-if="
                appointment
            "
        >
            <section class="appointment-header">
                <div>
                    <p class="eyebrow">
                        APPOINTMENT RECORD
                    </p>

                    <h2>
                        Appointment
                        #{{ appointment.id }}
                    </h2>

                    <p class="header-subtitle">
                        {{
                            formatDate(
                                appointment.appointment_date
                            )
                        }}
                        ·
                        {{
                            formatTime(
                                appointment.start_time
                            )
                        }}
                        –
                        {{
                            formatTime(
                                appointment.end_time
                            )
                        }}
                    </p>
                </div>

                <span
                    class="status-badge"
                    :class="
                        appointment.status
                    "
                >
                    {{
                        humanize(
                            appointment.status
                        )
                    }}
                </span>
            </section>

            <section class="readonly-banner">
                <div class="readonly-icon">
                    i
                </div>

                <div>
                    <strong>
                        Read-only Administrator record
                    </strong>

                    <p>
                        Appointment workflow actions are
                        intentionally unavailable in the
                        Administrator application.
                    </p>
                </div>
            </section>

            <section class="summary-grid">
                <article>
                    <span>
                        Status
                    </span>

                    <strong class="small-value">
                        {{
                            humanize(
                                appointment.status
                            )
                        }}
                    </strong>

                    <small>
                        Current booking status
                    </small>
                </article>

                <article>
                    <span>
                        Appointment Date
                    </span>

                    <strong class="small-value">
                        {{
                            formatDate(
                                appointment.appointment_date
                            )
                        }}
                    </strong>

                    <small>
                        Scheduled date
                    </small>
                </article>

                <article>
                    <span>
                        Time
                    </span>

                    <strong class="small-value">
                        {{
                            formatTime(
                                appointment.start_time
                            )
                        }}
                    </strong>

                    <small>
                        to
                        {{
                            formatTime(
                                appointment.end_time
                            )
                        }}
                    </small>
                </article>

                <article>
                    <span>
                        Reschedule Requests
                    </span>

                    <strong>
                        {{
                            appointment
                                .reschedule_requests_count
                            ?? 0
                        }}
                    </strong>

                    <small>
                        Historical requests
                    </small>
                </article>
            </section>

            <section class="detail-grid">
                <!-- ==================================================
                     CUSTOMER
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                CUSTOMER
                            </p>

                            <h3>
                                Customer Information
                            </h3>
                        </div>

                        <button
                            v-if="
                                appointment.customer?.id
                            "
                            type="button"
                            class="link-button"
                            @click="
                                openCustomer
                            "
                        >
                            View User
                        </button>
                    </div>

                    <dl>
                        <div>
                            <dt>
                                Full Name
                            </dt>

                            <dd>
                                {{
                                    appointment.customer
                                        ?.full_name
                                    || 'Unavailable'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Email
                            </dt>

                            <dd>
                                {{
                                    appointment.customer
                                        ?.email
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Phone Number
                            </dt>

                            <dd>
                                {{
                                    appointment.customer
                                        ?.phone_number
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Account Status
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        appointment.customer
                                            ?.status
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ==================================================
                     SHOP
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                SHOP
                            </p>

                            <h3>
                                Shop Information
                            </h3>
                        </div>

                        <button
                            v-if="
                                appointment.shop?.id
                            "
                            type="button"
                            class="link-button"
                            @click="
                                openShop
                            "
                        >
                            View Shop
                        </button>
                    </div>

                    <dl>
                        <div>
                            <dt>
                                Shop Name
                            </dt>

                            <dd>
                                {{
                                    appointment.shop
                                        ?.name
                                    || 'Unavailable'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Location
                            </dt>

                            <dd>
                                {{
                                    appointment.shop
                                        ?.city
                                    || '—'
                                }},
                                {{
                                    appointment.shop
                                        ?.province
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Approval Status
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        appointment.shop
                                            ?.approval_status
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Availability
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        appointment.shop
                                            ?.availability_status
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ==================================================
                     BOOKING SNAPSHOT
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                BOOKING SNAPSHOT
                            </p>

                            <h3>
                                Service at Booking
                            </h3>
                        </div>
                    </div>

                    <dl>
                        <div>
                            <dt>
                                Service Name
                            </dt>

                            <dd>
                                {{
                                    appointment
                                        .booking_snapshot
                                        ?.service_name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Price
                            </dt>

                            <dd>
                                {{
                                    formatCurrency(
                                        appointment
                                            .booking_snapshot
                                            ?.price
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Duration
                            </dt>

                            <dd>
                                <template
                                    v-if="
                                        appointment
                                            .booking_snapshot
                                            ?.duration_minutes
                                        !== null
                                        && appointment
                                            .booking_snapshot
                                            ?.duration_minutes
                                        !== undefined
                                    "
                                >
                                    {{
                                        appointment
                                            .booking_snapshot
                                            .duration_minutes
                                    }}
                                    minutes
                                </template>

                                <template v-else>
                                    —
                                </template>
                            </dd>
                        </div>
                    </dl>

                    <div class="snapshot-note">
                        These values preserve the service
                        information recorded when the
                        appointment was booked.
                    </div>
                </article>


                <!-- ==================================================
                     CURRENT SERVICE
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                CURRENT SERVICE
                            </p>

                            <h3>
                                Current Service Record
                            </h3>
                        </div>
                    </div>

                    <dl>
                        <div>
                            <dt>
                                Service Name
                            </dt>

                            <dd>
                                {{
                                    appointment.service
                                        ?.name
                                    || 'Unavailable'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Current Price
                            </dt>

                            <dd>
                                {{
                                    formatCurrency(
                                        appointment.service
                                            ?.price
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Duration
                            </dt>

                            <dd>
                                <template
                                    v-if="
                                        appointment.service
                                            ?.duration_minutes
                                        !== null
                                        && appointment.service
                                            ?.duration_minutes
                                        !== undefined
                                    "
                                >
                                    {{
                                        appointment.service
                                            .duration_minutes
                                    }}
                                    minutes
                                </template>

                                <template v-else>
                                    —
                                </template>
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Active
                            </dt>

                            <dd>
                                {{
                                    appointment.service
                                        ?.is_active
                                    ? 'Yes'
                                    : 'No'
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ==================================================
                     NOTES
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                CUSTOMER NOTES
                            </p>

                            <h3>
                                Booking Notes
                            </h3>
                        </div>
                    </div>

                    <div class="notes-box">
                        {{
                            appointment.notes
                            || 'No notes were provided for this appointment.'
                        }}
                    </div>
                </article>


                <!-- ==================================================
                     SYSTEM RECORD
                     ================================================== -->

                <article class="detail-card">
                    <div class="card-heading">
                        <div>
                            <p class="eyebrow">
                                RECORD INFORMATION
                            </p>

                            <h3>
                                System Record
                            </h3>
                        </div>
                    </div>

                    <dl>
                        <div>
                            <dt>
                                Appointment ID
                            </dt>

                            <dd>
                                #{{ appointment.id }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Reschedule Requests
                            </dt>

                            <dd>
                                {{
                                    appointment
                                        .reschedule_requests_count
                                    ?? 0
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Created
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        appointment.created_at
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Last Updated
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        appointment.updated_at
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