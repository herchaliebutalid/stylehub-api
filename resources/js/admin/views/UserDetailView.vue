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

const user = ref(null);

const isLoading = ref(false);
const isProcessing = ref(false);

const errorMessage = ref('');
const successMessage = ref('');

const modal = ref(null);

const userId = computed(
    () =>
        route.params.userId,
);

const isActive = computed(
    () =>
        user.value?.status
        === 'active',
);

const isSuspended = computed(
    () =>
        user.value?.status
        === 'suspended',
);

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

function formatDate(
    value,
    includeTime = false,
) {
    if (!value) {
        return '—';
    }

    const date =
        new Date(
            value,
        );

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return '—';
    }

    const options = {
        year:
            'numeric',

        month:
            'short',

        day:
            'numeric',

        timeZone:
            'Asia/Manila',
    };

    if (
        includeTime
    ) {
        options.hour =
            'numeric';

        options.minute =
            '2-digit';
    }

    return new Intl
        .DateTimeFormat(
            'en-PH',
            options,
        )
        .format(
            date,
        );
}

function userInitial() {
    const value =
        user.value?.first_name
        ||
        user.value?.full_name
        ||
        user.value?.email
        ||
        'U';

    return String(value)
        .charAt(0)
        .toUpperCase();
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
| LOAD USER
|--------------------------------------------------------------------------
*/

async function loadUser() {
    if (
        !auth.token
    ) {
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
                `/admin/users/${encodeURIComponent(
                    userId.value,
                )}`,
                {
                    token:
                        auth.token,
                },
            );

        user.value =
            response
                ?.data
                ?.user
            ?? null;

        if (
            !user.value
        ) {
            throw new Error(
                'The user response was invalid.',
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
                'The requested user account could not be found.';
        } else {
            errorMessage.value =
                error
                    ?.data
                    ?.message
                ??
                error
                    ?.message
                ??
                'Unable to load user account.';
        }
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| MODALS
|--------------------------------------------------------------------------
*/

function openSuspendModal() {
    if (
        !isActive.value
    ) {
        return;
    }

    errorMessage.value =
        '';

    successMessage.value =
        '';

    modal.value =
        'suspend';
}

function openReactivateModal() {
    if (
        !isSuspended.value
    ) {
        return;
    }

    errorMessage.value =
        '';

    successMessage.value =
        '';

    modal.value =
        'reactivate';
}

function closeModal() {
    if (
        isProcessing.value
    ) {
        return;
    }

    modal.value =
        null;
}


/*
|--------------------------------------------------------------------------
| SUSPEND ACCOUNT
|--------------------------------------------------------------------------
*/

async function suspendUser() {
    if (
        isProcessing.value
        ||
        !isActive.value
    ) {
        return;
    }

    isProcessing.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/users/${encodeURIComponent(
                    userId.value,
                )}/suspend`,
                {
                    method:
                        'PATCH',

                    token:
                        auth.token,
                },
            );

        user.value =
            response
                ?.data
                ?.user
            ?? user.value;

        successMessage.value =
            response
                ?.message
            ?? 'Account suspended successfully.';

        modal.value =
            null;
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
            'Unable to suspend this account.';

        if (
            error?.status === 409
        ) {
            modal.value =
                null;

            await loadUser();
        }
    } finally {
        isProcessing.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| REACTIVATE ACCOUNT
|--------------------------------------------------------------------------
*/

async function reactivateUser() {
    if (
        isProcessing.value
        ||
        !isSuspended.value
    ) {
        return;
    }

    isProcessing.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/users/${encodeURIComponent(
                    userId.value,
                )}/reactivate`,
                {
                    method:
                        'PATCH',

                    token:
                        auth.token,
                },
            );

        user.value =
            response
                ?.data
                ?.user
            ?? user.value;

        successMessage.value =
            response
                ?.message
            ?? 'Account reactivated successfully.';

        modal.value =
            null;
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
            'Unable to reactivate this account.';

        if (
            error?.status === 409
        ) {
            modal.value =
                null;

            await loadUser();
        }
    } finally {
        isProcessing.value =
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
            'admin.users',
    });
}

async function openShop() {
    if (
        !user.value
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
                user.value
                    .shop
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
        route.params.userId,

    () => {
        loadUser();
    },
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadUser();
});
</script>


<template>
    <div class="detail-page user-detail-page">
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
                    Back to users
                </span>
            </button>

            <button
                v-if="user"
                type="button"
                class="secondary-button refresh-detail-button"
                :disabled="
                    isLoading
                    || isProcessing
                "
                @click="loadUser"
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
            v-if="successMessage"
            class="success-alert detail-alert"
        >
            <div class="alert-icon success-icon">
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
                    Account updated
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
            v-if="errorMessage"
            class="error-alert detail-alert"
            role="alert"
        >
            <div class="alert-icon error-icon">
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
                    Something went wrong
                </strong>

                <span>
                    {{ errorMessage }}
                </span>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="loadUser"
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
                && !user
            "
            class="loading-card user-loading-card"
        >
            <div class="loading-icon-wrap">
                <div class="spinner" />
            </div>

            <h3>
                Loading user profile
            </h3>

            <p>
                Retrieving account information,
                activity and administrative status.
            </p>
        </div>


        <!-- ============================================================
             USER CONTENT
             ============================================================ -->

        <template v-else-if="user">
            <!-- ========================================================
                 PROFILE HERO
                 ======================================================== -->

            <section class="user-profile-hero">
                <div class="user-profile-main">
                    <div
                        class="profile-avatar"
                        :class="
                            statusClass(
                                user.role
                            )
                        "
                    >
                        {{
                            userInitial()
                        }}
                    </div>

                    <div class="profile-copy">
                        <div class="profile-eyebrow">
                            <span class="profile-eyebrow-dot" />

                            StyleHub account

                            <span class="profile-id">
                                #{{ user.id }}
                            </span>
                        </div>

                        <h2>
                            {{
                                user.full_name
                                || 'Unnamed User'
                            }}
                        </h2>

                        <div class="profile-metadata">
                            <div class="profile-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="12"
                                        cy="8"
                                        r="3"
                                    />

                                    <path
                                        d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                                    />
                                </svg>

                                <span>
                                    {{
                                        humanize(
                                            user.role
                                        )
                                    }}
                                </span>
                            </div>

                            <span class="profile-meta-divider" />

                            <div class="profile-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 6h16v12H4z"
                                    />

                                    <path
                                        d="M4 7l8 6 8-6"
                                    />
                                </svg>

                                <span>
                                    {{
                                        user.email
                                        || 'No email'
                                    }}
                                </span>
                            </div>

                            <span class="profile-meta-divider" />

                            <div class="profile-meta-item">
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
                                    Joined
                                    {{
                                        formatDate(
                                            user.created_at
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- PROFILE ACTIONS -->

                <div class="profile-actions">
                    <div class="profile-status-area">
                        <span
                            class="role-badge profile-role-badge"
                            :class="
                                statusClass(
                                    user.role
                                )
                            "
                        >
                            <svg
                                v-if="
                                    user.role
                                    === 'customer'
                                "
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="8"
                                    r="3"
                                />

                                <path
                                    d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                                />
                            </svg>

                            <svg
                                v-else
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

                            {{
                                humanize(
                                    user.role
                                )
                            }}
                        </span>

                        <span
                            class="status-badge profile-status-badge"
                            :class="
                                statusClass(
                                    user.status
                                )
                            "
                        >
                            <span class="status-indicator" />

                            {{
                                humanize(
                                    user.status
                                )
                            }}
                        </span>
                    </div>


                    <div class="profile-action-buttons">
                        <button
                            v-if="isActive"
                            type="button"
                            class="suspend-button"
                            :disabled="isProcessing"
                            @click="
                                openSuspendModal
                            "
                        >
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                />

                                <path
                                    d="M9 9l6 6M15 9l-6 6"
                                />
                            </svg>

                            Suspend account
                        </button>

                        <button
                            v-if="isSuspended"
                            type="button"
                            class="reactivate-button"
                            :disabled="isProcessing"
                            @click="
                                openReactivateModal
                            "
                        >
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

                            Reactivate account
                        </button>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 SUSPENDED NOTICE
                 ======================================================== -->

            <section
                v-if="isSuspended"
                class="account-suspension-panel"
            >
                <div class="account-suspension-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                        />

                        <path
                            d="M9 9l6 6M15 9l-6 6"
                        />
                    </svg>
                </div>

                <div class="account-suspension-copy">
                    <div class="account-suspension-heading">
                        <div>
                            <span>
                                ACCOUNT RESTRICTION
                            </span>

                            <h3>
                                This account is currently suspended
                            </h3>
                        </div>

                        <span class="status-badge suspended">
                            Suspended
                        </span>
                    </div>

                    <p>
                        Existing login sessions for this account
                        have been revoked. The user cannot sign in
                        again until an administrator reactivates
                        the account.
                    </p>
                </div>
            </section>


            <!-- ========================================================
                 SUMMARY CARDS
                 ======================================================== -->

            <section class="user-summary-grid">
                <!-- ACCOUNT STATUS -->

                <article class="user-summary-card">
                    <div
                        class="summary-icon"
                        :class="
                            isSuspended
                                ? 'danger'
                                : 'success'
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                v-if="
                                    isActive
                                "
                                d="M8 12l3 3 5-6"
                            />

                            <path
                                v-else
                                d="M9 9l6 6M15 9l-6 6"
                            />
                        </svg>
                    </div>

                    <div class="summary-card-copy">
                        <span>
                            Account status
                        </span>

                        <strong class="summary-text-value">
                            {{
                                humanize(
                                    user.status
                                )
                            }}
                        </strong>

                        <small>
                            Current account state
                        </small>
                    </div>
                </article>


                <!-- APPOINTMENTS -->

                <article class="user-summary-card">
                    <div class="summary-icon appointments">
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

                    <div class="summary-card-copy">
                        <span>
                            Appointments
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    user.appointments_count
                                    ?? 0
                                )
                            }}
                        </strong>

                        <small>
                            Total appointment records
                        </small>
                    </div>
                </article>


                <!-- EMAIL -->

                <article class="user-summary-card">
                    <div
                        class="summary-icon"
                        :class="
                            user.email_verified
                                ? 'success'
                                : 'warning'
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6h16v12H4z"
                            />

                            <path
                                d="M4 7l8 6 8-6"
                            />

                            <path
                                v-if="
                                    user.email_verified
                                "
                                d="M15 17l2 2 4-4"
                            />
                        </svg>
                    </div>

                    <div class="summary-card-copy">
                        <span>
                            Email verification
                        </span>

                        <strong class="summary-text-value">
                            {{
                                user.email_verified
                                    ? 'Verified'
                                    : 'Not Verified'
                            }}
                        </strong>

                        <small>
                            Email verification state
                        </small>
                    </div>
                </article>


                <!-- ROLE -->

                <article class="user-summary-card">
                    <div class="summary-icon role">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="8"
                                r="3"
                            />

                            <path
                                d="M5 21c0-5 2.5-8 7-8s7 3 7 8"
                            />
                        </svg>
                    </div>

                    <div class="summary-card-copy">
                        <span>
                            Account role
                        </span>

                        <strong class="summary-text-value">
                            {{
                                humanize(
                                    user.role
                                )
                            }}
                        </strong>

                        <small>
                            StyleHub account type
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 DETAILS
                 ======================================================== -->

            <section class="user-information-grid">
                <!-- PERSONAL INFORMATION -->

                <article class="detail-card information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon personal">
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
                                    PERSONAL INFORMATION
                                </p>

                                <h3>
                                    Account details
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="identity-preview">
                        <div
                            class="identity-preview-avatar"
                            :class="
                                statusClass(
                                    user.role
                                )
                            "
                        >
                            {{
                                userInitial()
                            }}
                        </div>

                        <div class="identity-preview-copy">
                            <strong>
                                {{
                                    user.full_name
                                    || 'Unnamed User'
                                }}
                            </strong>

                            <span>
                                {{
                                    user.email
                                    || 'No email address'
                                }}
                            </span>
                        </div>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                First name
                            </dt>

                            <dd>
                                {{
                                    user.first_name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Middle name
                            </dt>

                            <dd>
                                {{
                                    user.middle_name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Last name
                            </dt>

                            <dd>
                                {{
                                    user.last_name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Email address
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    user.email
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Phone number
                            </dt>

                            <dd>
                                {{
                                    user.phone_number
                                    || '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- SECURITY -->

                <article class="detail-card information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon security">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                    />

                                    <path
                                        d="M9 12l2 2 4-4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    ACCOUNT ACTIVITY
                                </p>

                                <h3>
                                    Security & activity
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="security-overview">
                        <div
                            class="security-state-icon"
                            :class="
                                isSuspended
                                    ? 'suspended'
                                    : 'active'
                            "
                        >
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                />

                                <path
                                    v-if="
                                        isActive
                                    "
                                    d="M8 12l3 3 5-6"
                                />

                                <path
                                    v-else
                                    d="M9 9l6 6M15 9l-6 6"
                                />
                            </svg>
                        </div>

                        <div>
                            <strong>
                                {{
                                    isActive
                                        ? 'Account access enabled'
                                        : 'Account access restricted'
                                }}
                            </strong>

                            <span>
                                {{
                                    isActive
                                        ? 'This user can currently sign in to StyleHub.'
                                        : 'This user cannot currently sign in to StyleHub.'
                                }}
                            </span>
                        </div>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Role
                            </dt>

                            <dd>
                                <span
                                    class="role-badge compact-role-badge"
                                    :class="
                                        statusClass(
                                            user.role
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            user.role
                                        )
                                    }}
                                </span>
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
                                            user.status
                                        )
                                    "
                                >
                                    <span class="status-indicator" />

                                    {{
                                        humanize(
                                            user.status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Email verified
                            </dt>

                            <dd>
                                <span
                                    class="verification-badge"
                                    :class="{
                                        verified:
                                            user.email_verified,

                                        unverified:
                                            !user.email_verified,
                                    }"
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
                                                user.email_verified
                                            "
                                            d="M8 12l3 3 5-6"
                                        />

                                        <path
                                            v-else
                                            d="M12 8v5M12 17h.01"
                                        />
                                    </svg>

                                    {{
                                        user.email_verified
                                            ? 'Verified'
                                            : 'Not verified'
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Last login
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        user.last_login_at,
                                        true,
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Account created
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        user.created_at,
                                        true,
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
                                    formatDate(
                                        user.updated_at,
                                        true,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- SHOP OWNER BUSINESS -->

                <article
                    v-if="
                        user.role
                        === 'shop_owner'
                    "
                    class="detail-card information-card"
                >
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon business">
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
                                    BUSINESS
                                </p>

                                <h3>
                                    Shop information
                                </h3>
                            </div>
                        </div>

                        <button
                            v-if="user.shop"
                            type="button"
                            class="view-shop-button"
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


                    <template v-if="user.shop">
                        <div class="shop-preview">
                            <div class="shop-preview-icon">
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

                            <div class="shop-preview-copy">
                                <strong>
                                    {{
                                        user.shop.name
                                        || 'Unnamed shop'
                                    }}
                                </strong>

                                <span>
                                    Shop #{{ user.shop.id }}
                                </span>
                            </div>

                            <span
                                class="status-badge"
                                :class="
                                    statusClass(
                                        user.shop
                                            .approval_status
                                    )
                                "
                            >
                                {{
                                    humanize(
                                        user.shop
                                            .approval_status
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
                                        user.shop.name
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
                                                user.shop
                                                    .approval_status
                                            )
                                        "
                                    >
                                        {{
                                            humanize(
                                                user.shop
                                                    .approval_status
                                            )
                                        }}
                                    </span>
                                </dd>
                            </div>

                            <div class="information-row">
                                <dt>
                                    Availability
                                </dt>

                                <dd>
                                    <span
                                        class="availability-badge"
                                        :class="
                                            statusClass(
                                                user.shop
                                                    .availability_status
                                            )
                                        "
                                    >
                                        {{
                                            humanize(
                                                user.shop
                                                    .availability_status
                                            )
                                        }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </template>


                    <div
                        v-else
                        class="no-shop-state"
                    >
                        <div class="no-shop-icon">
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
                                    d="M5 5l14 14"
                                />
                            </svg>
                        </div>

                        <div>
                            <strong>
                                No shop associated
                            </strong>

                            <span>
                                This Shop Owner account does
                                not currently have an associated
                                shop registration.
                            </span>
                        </div>
                    </div>
                </article>


                <!-- APPOINTMENTS -->

                <article class="detail-card information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon appointments">
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
                                    APPOINTMENT ACTIVITY
                                </p>

                                <h3>
                                    Usage summary
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="appointment-activity">
                        <div class="appointment-activity-icon">
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

                        <strong>
                            {{
                                formatNumber(
                                    user.appointments_count
                                    ?? 0
                                )
                            }}
                        </strong>

                        <span>
                            appointment record{{
                                Number(
                                    user.appointments_count
                                    ?? 0
                                ) === 1
                                    ? ''
                                    : 's'
                            }}
                            associated with this account.
                        </span>
                    </div>


                    <div class="activity-note">
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
                            Appointment history is retained as
                            part of the platform record even if
                            the user account is suspended.
                        </span>
                    </div>
                </article>
            </section>
        </template>


        <!-- ============================================================
             SUSPEND CONFIRMATION
             ============================================================ -->

        <div
            v-if="
                modal
                === 'suspend'
            "
            class="modal-backdrop"
            @click.self="
                closeModal
            "
        >
            <div class="modal-card user-action-modal">
                <div class="modal-heading-area">
                    <div class="modal-icon suspend">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M9 9l6 6M15 9l-6 6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span class="modal-eyebrow danger">
                            ADMINISTRATIVE ACTION
                        </span>

                        <h3>
                            Suspend account?
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    You are about to suspend

                    <strong>
                        {{
                            user?.full_name
                            || user?.email
                        }}
                    </strong>.
                </p>


                <div class="suspension-warning-box">
                    <div class="warning-box-icon">
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
                        <strong>
                            Existing sessions will be revoked
                        </strong>

                        <span>
                            Suspending this account immediately
                            revokes its existing login sessions.
                            The user will not be able to sign in
                            until an Administrator reactivates
                            the account.
                        </span>
                    </div>
                </div>


                <div class="account-preview">
                    <div
                        class="account-preview-avatar"
                        :class="
                            statusClass(
                                user?.role
                            )
                        "
                    >
                        {{
                            userInitial()
                        }}
                    </div>

                    <div class="account-preview-copy">
                        <strong>
                            {{
                                user?.full_name
                                || 'Unnamed User'
                            }}
                        </strong>

                        <span>
                            {{
                                user?.email
                            }}
                        </span>
                    </div>

                    <span
                        class="role-badge"
                        :class="
                            statusClass(
                                user?.role
                            )
                        "
                    >
                        {{
                            humanize(
                                user?.role
                            )
                        }}
                    </span>
                </div>


                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="
                            closeModal
                        "
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="suspend-confirm-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="
                            suspendUser
                        "
                    >
                        <span
                            v-if="
                                isProcessing
                            "
                            class="button-spinner"
                        />

                        <svg
                            v-else
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M9 9l6 6M15 9l-6 6"
                            />
                        </svg>

                        {{
                            isProcessing
                                ? 'Suspending...'
                                : 'Suspend account'
                        }}
                    </button>
                </div>
            </div>
        </div>


        <!-- ============================================================
             REACTIVATE CONFIRMATION
             ============================================================ -->

        <div
            v-if="
                modal
                === 'reactivate'
            "
            class="modal-backdrop"
            @click.self="
                closeModal
            "
        >
            <div class="modal-card user-action-modal">
                <div class="modal-heading-area">
                    <div class="modal-icon reactivate">
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

                    <div>
                        <span class="modal-eyebrow success">
                            ADMINISTRATIVE ACTION
                        </span>

                        <h3>
                            Reactivate account?
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    You are about to reactivate

                    <strong>
                        {{
                            user?.full_name
                            || user?.email
                        }}
                    </strong>.
                </p>


                <div class="reactivation-information-box">
                    <div class="information-box-icon">
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
                            New sign-in required
                        </strong>

                        <span>
                            Previous login sessions will not
                            be restored. The user must sign in
                            again to receive a new session.
                        </span>
                    </div>
                </div>


                <div class="account-preview">
                    <div
                        class="account-preview-avatar"
                        :class="
                            statusClass(
                                user?.role
                            )
                        "
                    >
                        {{
                            userInitial()
                        }}
                    </div>

                    <div class="account-preview-copy">
                        <strong>
                            {{
                                user?.full_name
                                || 'Unnamed User'
                            }}
                        </strong>

                        <span>
                            {{
                                user?.email
                            }}
                        </span>
                    </div>

                    <span
                        class="status-badge suspended"
                    >
                        Suspended
                    </span>
                </div>


                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="
                            closeModal
                        "
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="reactivate-confirm-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="
                            reactivateUser
                        "
                    >
                        <span
                            v-if="
                                isProcessing
                            "
                            class="button-spinner"
                        />

                        <svg
                            v-else
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

                        {{
                            isProcessing
                                ? 'Reactivating...'
                                : 'Reactivate account'
                        }}
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

.user-detail-page {
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

.user-detail-page
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

.success-icon {
    background:
        rgba(
            40,
            117,
            88,
            0.08
        );
}

.error-icon {
    background:
        rgba(
            169,
            79,
            79,
            0.08
        );
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

.user-loading-card {
    min-height: 360px;
}

.loading-icon-wrap {
    width: 54px;
    height: 54px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 13px;

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
| PROFILE HERO
|--------------------------------------------------------------------------
*/

.user-profile-hero {
    position: relative;

    overflow: hidden;

    min-height: 170px;

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
            circle at 94% 5%,
            rgba(
                214,
                154,
                45,
                0.08
            ),
            transparent 29%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.user-profile-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.profile-avatar {
    width: 68px;
    height: 68px;

    flex:
        0
        0
        68px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 19px;

    font-size: 22px;
    font-weight: 800;

    box-shadow:
        0 10px 24px
        rgba(
            15,
            23,
            42,
            0.08
        );
}

.profile-avatar.customer {
    border:
        1px solid
        #d9e4f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.profile-avatar.shop-owner {
    border:
        1px solid
        #e2daf8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.profile-copy {
    min-width: 0;

    flex: 1;
}

.profile-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing:
        1.05px;

    text-transform:
        uppercase;
}

.profile-eyebrow-dot {
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

.profile-id {
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

.profile-copy h2 {
    max-width: 700px;

    margin: 0;

    overflow-wrap:
        anywhere;

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
| PROFILE METADATA
|--------------------------------------------------------------------------
*/

.profile-metadata {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.profile-meta-item {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.profile-meta-item span {
    overflow-wrap: anywhere;
}

.profile-meta-item svg {
    width: 13px;
    height: 13px;

    flex: 0 0 13px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.profile-meta-divider {
    width: 3px;
    height: 3px;

    flex: 0 0 3px;

    border-radius: 999px;

    background:
        #c9ced5;
}


/*
|--------------------------------------------------------------------------
| PROFILE ACTIONS
|--------------------------------------------------------------------------
*/

.profile-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 12px;
}

.profile-status-area,
.profile-action-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 8px;

    flex-wrap: wrap;
}

.profile-role-badge {
    gap: 5px;
}

.profile-role-badge svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.profile-role-badge.customer {
    border-color:
        #dae5f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.profile-role-badge.shop-owner {
    border-color:
        #e1d9f8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.profile-status-badge {
    gap: 5px;
}

.status-indicator {
    width: 5px;
    height: 5px;

    flex: 0 0 5px;

    border-radius: 999px;

    background:
        currentColor;
}

.profile-action-buttons button {
    min-height: 38px;
}

.profile-action-buttons svg {
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
| SUSPENSION PANEL
|--------------------------------------------------------------------------
*/

.account-suspension-panel {
    display: flex;
    align-items: flex-start;

    gap: 12px;

    padding: 16px;

    border:
        1px solid
        #ecd3d3;

    border-radius:
        var(--sh-radius-lg);

    background:
        linear-gradient(
            145deg,
            #fffafa,
            var(--sh-danger-bg)
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.account-suspension-icon {
    width: 42px;
    height: 42px;

    flex: 0 0 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        rgba(
            169,
            79,
            79,
            0.09
        );

    color:
        var(--sh-danger);
}

.account-suspension-icon svg {
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

.account-suspension-copy {
    min-width: 0;

    flex: 1;
}

.account-suspension-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 14px;
}

.account-suspension-heading
> div
> span {
    display: block;

    margin-bottom: 2px;

    color:
        var(--sh-danger);

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.9px;
}

.account-suspension-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.account-suspension-copy p {
    max-width: 760px;

    margin:
        6px
        0
        0;

    color:
        #8f5555;

    font-size: 9px;

    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| SUMMARY GRID
|--------------------------------------------------------------------------
*/

.user-summary-grid {
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

.user-summary-card {
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

    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease;
}

.user-summary-card:hover {
    transform:
        translateY(
            -1px
        );

    box-shadow:
        var(--sh-shadow-card);
}

.summary-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.summary-icon svg {
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

.summary-icon.success {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.summary-icon.danger {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.summary-icon.warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.summary-icon.appointments {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.summary-icon.role {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.summary-card-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.summary-card-copy > span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.summary-card-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;

    line-height: 24px;

    letter-spacing:
        -0.35px;
}

.summary-card-copy
.summary-text-value {
    font-size: 14px;

    line-height: 20px;
}

.summary-card-copy small {
    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION GRID
|--------------------------------------------------------------------------
*/

.user-information-grid {
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

.information-card {
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

.information-icon.personal {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.information-icon.security {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.information-icon.business {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.information-icon.appointments {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.information-card
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
| IDENTITY PREVIEW
|--------------------------------------------------------------------------
*/

.identity-preview {
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

.identity-preview-avatar {
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

.identity-preview-avatar.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.identity-preview-avatar.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.identity-preview-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.identity-preview-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.identity-preview-copy span {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| SECURITY OVERVIEW
|--------------------------------------------------------------------------
*/

.security-overview {
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

.security-state-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.security-state-icon.active {
    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.security-state-icon.suspended {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.security-state-icon svg {
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

.security-overview
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.security-overview strong {
    color:
        var(--sh-text);

    font-size: 9.5px;
}

.security-overview span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
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
            110px,
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

    overflow-wrap:
        anywhere;

    color:
        var(--sh-text-2);

    font-size: 10px;
    font-weight: 650;

    text-align: right;
}

.compact-role-badge {
    min-height: 24px;
}

.compact-role-badge.customer {
    border-color:
        #dae5f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.compact-role-badge.shop-owner {
    border-color:
        #e2daf8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}


/*
|--------------------------------------------------------------------------
| VERIFICATION
|--------------------------------------------------------------------------
*/

.verification-badge {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    font-size: 8.5px;
    font-weight: 750;
}

.verification-badge svg {
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

.verification-badge.verified {
    color:
        var(--sh-success);
}

.verification-badge.unverified {
    color:
        var(--sh-warning);
}


/*
|--------------------------------------------------------------------------
| VIEW SHOP
|--------------------------------------------------------------------------
*/

.view-shop-button {
    min-height: 34px;

    gap: 5px;
}

.view-shop-button svg {
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
| SHOP PREVIEW
|--------------------------------------------------------------------------
*/

.shop-preview {
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

.shop-preview-icon {
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
}

.shop-preview-icon svg {
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

.shop-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.shop-preview-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.shop-preview-copy span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| NO SHOP
|--------------------------------------------------------------------------
*/

.no-shop-state {
    min-height: 180px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding:
        25px;

    text-align: center;
}

.no-shop-icon {
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

.no-shop-icon svg {
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

.no-shop-state strong {
    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;
}

.no-shop-state span {
    max-width: 340px;

    margin-top: 4px;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| AVAILABILITY
|--------------------------------------------------------------------------
*/

.availability-badge.open,
.availability-badge.available {
    border-color:
        #d4ebdf;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.availability-badge.closed,
.availability-badge.unavailable {
    border-color:
        #e1e5ea;

    background:
        #f4f6f8;

    color:
        var(--sh-text-3);
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT ACTIVITY
|--------------------------------------------------------------------------
*/

.appointment-activity {
    min-height: 205px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding:
        24px;

    text-align: center;
}

.appointment-activity-icon {
    width: 48px;
    height: 48px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 10px;

    border-radius: 14px;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.appointment-activity-icon svg {
    width: 21px;
    height: 21px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.appointment-activity strong {
    color:
        var(--sh-text);

    font-size: 32px;
    font-weight: 800;

    line-height: 38px;

    letter-spacing:
        -0.8px;
}

.appointment-activity span {
    max-width: 300px;

    margin-top: 4px;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 15px;
}

.activity-note {
    display: flex;
    align-items: flex-start;

    gap: 7px;

    margin:
        0
        15px
        15px;

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

.activity-note svg {
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


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

.user-action-modal {
    width:
        min(
            530px,
            100%
        );

    padding: 20px;
}

.modal-heading-area {
    display: flex;
    align-items: center;

    gap: 11px;

    margin-bottom: 12px;
}

.user-action-modal
.modal-icon {
    margin: 0;
}

.user-action-modal
.modal-icon svg {
    width: 21px;
    height: 21px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.modal-icon.suspend {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.modal-icon.reactivate {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.modal-heading-area h3 {
    margin: 1px 0 0;

    font-size: 15px;
}

.modal-eyebrow {
    display: block;

    font-size: 7.5px;
    font-weight: 800;

    letter-spacing: 0.9px;
}

.modal-eyebrow.danger {
    color:
        var(--sh-danger);
}

.modal-eyebrow.success {
    color:
        var(--sh-success);
}

.modal-description {
    margin-top: 0 !important;
}


/*
|--------------------------------------------------------------------------
| SUSPENSION WARNING
|--------------------------------------------------------------------------
*/

.suspension-warning-box,
.reactivation-information-box {
    display: flex;
    align-items: flex-start;

    gap: 10px;

    margin-top: 14px;

    padding:
        11px
        12px;

    border-radius: 11px;
}

.suspension-warning-box {
    border:
        1px solid
        #efd1d1;

    background:
        var(--sh-danger-bg);
}

.reactivation-information-box {
    border:
        1px solid
        #d2e9de;

    background:
        var(--sh-success-bg);
}

.warning-box-icon,
.information-box-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        rgba(
            255,
            255,
            255,
            0.65
        );
}

.warning-box-icon {
    color:
        var(--sh-danger);
}

.information-box-icon {
    color:
        var(--sh-success);
}

.warning-box-icon svg,
.information-box-icon svg {
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

.suspension-warning-box
> div:last-child,
.reactivation-information-box
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.suspension-warning-box strong {
    color:
        var(--sh-danger);

    font-size: 9px;
}

.reactivation-information-box strong {
    color:
        var(--sh-success);

    font-size: 9px;
}

.suspension-warning-box span,
.reactivation-information-box span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| ACCOUNT PREVIEW
|--------------------------------------------------------------------------
*/

.account-preview {
    display: flex;
    align-items: center;

    gap: 9px;

    margin-top: 13px;

    padding:
        10px
        11px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.account-preview-avatar {
    width: 36px;
    height: 36px;

    flex: 0 0 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    font-size: 11px;
    font-weight: 800;
}

.account-preview-avatar.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.account-preview-avatar.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.account-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.account-preview-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 9.5px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.account-preview-copy span {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| MODAL BUTTONS
|--------------------------------------------------------------------------
*/

.suspend-confirm-button {
    border:
        1px solid
        var(--sh-danger);

    background:
        var(--sh-danger);

    color:
        #ffffff;
}

.suspend-confirm-button:hover:not(:disabled) {
    background:
        #944545;
}

.reactivate-confirm-button {
    border:
        1px solid
        var(--sh-success);

    background:
        var(--sh-success);

    color:
        #ffffff;
}

.reactivate-confirm-button:hover:not(:disabled) {
    background:
        #22664d;
}

.modal-confirm-button {
    min-width: 145px;
}

.button-spinner {
    width: 13px;
    height: 13px;

    border:
        2px solid
        currentColor;

    border-right-color:
        transparent;

    border-radius:
        999px;

    animation:
        user-button-spin
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
        user-spin
        0.8s
        linear
        infinite;
}

@keyframes user-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}

@keyframes user-button-spin {
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

@media (max-width: 1150px) {
    .user-profile-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .profile-actions {
        width: 100%;

        align-items:
            flex-start;
    }

    .profile-status-area,
    .profile-action-buttons {
        justify-content:
            flex-start;
    }
}


@media (max-width: 1000px) {
    .user-summary-grid {
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


@media (max-width: 900px) {
    .user-information-grid {
        grid-template-columns:
            1fr;
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

    .user-profile-hero {
        padding: 17px;
    }

    .user-profile-main {
        align-items:
            flex-start;
    }

    .profile-avatar {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;

        font-size: 18px;
    }

    .profile-copy h2 {
        font-size: 20px;

        line-height: 25px;
    }

    .profile-metadata {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .profile-meta-divider {
        display: none;
    }

    .profile-actions {
        align-items:
            stretch;
    }

    .profile-status-area {
        justify-content:
            flex-start;
    }

    .profile-action-buttons {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .profile-action-buttons
    button {
        width: 100%;
    }

    .account-suspension-panel {
        flex-direction:
            column;
    }

    .account-suspension-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .user-summary-grid {
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

    .shop-preview {
        align-items:
            flex-start;

        flex-wrap: wrap;
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