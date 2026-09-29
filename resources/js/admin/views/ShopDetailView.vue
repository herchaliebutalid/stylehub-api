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

const shop = ref(null);

const isLoading = ref(false);
const isProcessing = ref(false);

const errorMessage = ref('');
const successMessage = ref('');

const modal = ref(null);

const rejectionReason = ref('');
const rejectionError = ref('');

const suspensionReason = ref('');
const suspensionError = ref('');


/*
|--------------------------------------------------------------------------
| SHOP STATE
|--------------------------------------------------------------------------
*/

const shopId = computed(
    () =>
        route.params.shopId,
);

const isPending = computed(
    () =>
        shop.value?.approval_status
        === 'pending',
);

const isApproved = computed(
    () =>
        shop.value?.approval_status
        === 'approved',
);

const isSuspended = computed(
    () =>
        shop.value?.administrative_status
        === 'suspended',
);

const canSuspend = computed(
    () =>
        isApproved.value
        &&
        !isSuspended.value,
);

const canReactivate = computed(
    () =>
        isApproved.value
        &&
        isSuspended.value,
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

function formatDate(
    date,
    includeTime = false,
) {
    if (!date) {
        return '—';
    }

    const parsed =
        new Date(
            date,
        );

    if (
        Number.isNaN(
            parsed.getTime(),
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

    return new Intl.DateTimeFormat(
        'en-PH',
        options,
    ).format(
        parsed,
    );
}

function statusClass(status) {
    return String(
        status ?? '',
    )
        .toLowerCase()
        .replaceAll(
            '_',
            '-',
        );
}

function shopInitial() {
    const name =
        String(
            shop.value?.name
            ?? '',
        )
            .trim();

    if (!name) {
        return 'S';
    }

    return name
        .charAt(0)
        .toUpperCase();
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
| LOAD SHOP
|--------------------------------------------------------------------------
*/

async function loadShop() {
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
                `/admin/shops/${encodeURIComponent(
                    shopId.value,
                )}`,
                {
                    token:
                        auth.token,
                },
            );

        shop.value =
            response?.data?.shop
            ?? null;

        if (
            !shop.value
        ) {
            throw new Error(
                'The shop response was invalid.',
            );
        }
    } catch (
        error
    ) {
        if (
            error?.status
                === 401
            ||
            error?.status
                === 403
        ) {
            await authenticationFailure();

            return;
        }

        errorMessage.value =
            error?.status
                === 404
                ? 'The requested shop could not be found.'
                : error?.data?.message
                    ?? error?.message
                    ?? 'Unable to load shop.';
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

function openApprovalModal() {
    successMessage.value =
        '';

    errorMessage.value =
        '';

    modal.value =
        'approve';
}

function openRejectionModal() {
    successMessage.value =
        '';

    errorMessage.value =
        '';

    rejectionReason.value =
        '';

    rejectionError.value =
        '';

    modal.value =
        'reject';
}

function openSuspensionModal() {
    if (
        !canSuspend.value
    ) {
        return;
    }

    successMessage.value =
        '';

    errorMessage.value =
        '';

    suspensionReason.value =
        '';

    suspensionError.value =
        '';

    modal.value =
        'suspend';
}

function openReactivationModal() {
    if (
        !canReactivate.value
    ) {
        return;
    }

    successMessage.value =
        '';

    errorMessage.value =
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

    rejectionError.value =
        '';

    suspensionError.value =
        '';
}


/*
|--------------------------------------------------------------------------
| APPROVE SHOP
|--------------------------------------------------------------------------
*/

async function approveShop() {
    if (
        isProcessing.value
        ||
        !isPending.value
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
                `/admin/shops/${encodeURIComponent(
                    shopId.value,
                )}/approve`,
                {
                    method:
                        'POST',

                    token:
                        auth.token,
                },
            );

        successMessage.value =
            response?.message
            ??
            'Shop approved successfully.';

        modal.value =
            null;

        await loadShop();
    } catch (
        error
    ) {
        if (
            error?.status
                === 401
            ||
            error?.status
                === 403
        ) {
            await authenticationFailure();

            return;
        }

        errorMessage.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to approve shop.';

        if (
            error?.status
                === 409
        ) {
            modal.value =
                null;

            await loadShop();
        }
    } finally {
        isProcessing.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| REJECT SHOP
|--------------------------------------------------------------------------
*/

async function rejectShop() {
    if (
        isProcessing.value
        ||
        !isPending.value
    ) {
        return;
    }

    rejectionError.value =
        '';

    const reason =
        rejectionReason.value
            .trim();

    if (
        reason.length
        < 10
    ) {
        rejectionError.value =
            'The rejection reason must contain at least 10 characters.';

        return;
    }

    if (
        reason.length
        > 1000
    ) {
        rejectionError.value =
            'The rejection reason may not exceed 1000 characters.';

        return;
    }

    isProcessing.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/shops/${encodeURIComponent(
                    shopId.value,
                )}/reject`,
                {
                    method:
                        'POST',

                    token:
                        auth.token,

                    body: {
                        reason,
                    },
                },
            );

        successMessage.value =
            response?.message
            ??
            'Shop rejected successfully.';

        modal.value =
            null;

        await loadShop();
    } catch (
        error
    ) {
        if (
            error?.status
                === 401
            ||
            error?.status
                === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status
                === 422
            &&
            error?.data?.errors
                ?.reason?.[0]
        ) {
            rejectionError.value =
                error.data.errors
                    .reason[0];

            return;
        }

        errorMessage.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to reject shop.';

        if (
            error?.status
                === 409
        ) {
            modal.value =
                null;

            await loadShop();
        }
    } finally {
        isProcessing.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| SUSPEND SHOP
|--------------------------------------------------------------------------
*/

async function suspendShop() {
    if (
        isProcessing.value
        ||
        !canSuspend.value
    ) {
        return;
    }

    suspensionError.value =
        '';

    const reason =
        suspensionReason.value
            .trim();

    if (
        reason.length
        < 10
    ) {
        suspensionError.value =
            'The suspension reason must contain at least 10 characters.';

        return;
    }

    if (
        reason.length
        > 1000
    ) {
        suspensionError.value =
            'The suspension reason may not exceed 1000 characters.';

        return;
    }

    isProcessing.value =
        true;

    errorMessage.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/shops/${encodeURIComponent(
                    shopId.value,
                )}/suspend`,
                {
                    method:
                        'PATCH',

                    token:
                        auth.token,

                    body: {
                        reason,
                    },
                },
            );

        successMessage.value =
            response?.message
            ??
            'Shop suspended successfully.';

        modal.value =
            null;

        await loadShop();
    } catch (
        error
    ) {
        if (
            error?.status
                === 401
            ||
            error?.status
                === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status
                === 422
            &&
            error?.data?.errors
                ?.reason?.[0]
        ) {
            suspensionError.value =
                error.data.errors
                    .reason[0];

            return;
        }

        errorMessage.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to suspend shop.';

        if (
            error?.status
                === 409
        ) {
            modal.value =
                null;

            await loadShop();
        }
    } finally {
        isProcessing.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| REACTIVATE SHOP
|--------------------------------------------------------------------------
*/

async function reactivateShop() {
    if (
        isProcessing.value
        ||
        !canReactivate.value
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
                `/admin/shops/${encodeURIComponent(
                    shopId.value,
                )}/reactivate`,
                {
                    method:
                        'PATCH',

                    token:
                        auth.token,
                },
            );

        successMessage.value =
            response?.message
            ??
            'Shop reactivated successfully.';

        modal.value =
            null;

        await loadShop();
    } catch (
        error
    ) {
        if (
            error?.status
                === 401
            ||
            error?.status
                === 403
        ) {
            await authenticationFailure();

            return;
        }

        errorMessage.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to reactivate shop.';

        if (
            error?.status
                === 409
        ) {
            modal.value =
                null;

            await loadShop();
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
            'admin.shops',
    });
}


/*
|--------------------------------------------------------------------------
| WATCH ROUTE
|--------------------------------------------------------------------------
*/

watch(
    () =>
        route.params.shopId,

    () => {
        loadShop();
    },
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadShop();
});
</script>


<template>
    <div class="detail-page shop-detail-page">
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
                    Back to shops
                </span>
            </button>

            <button
                v-if="shop"
                type="button"
                class="secondary-button refresh-detail-button"
                :disabled="
                    isLoading
                    || isProcessing
                "
                @click="loadShop"
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

                Refresh
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
                    Action completed
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
                @click="loadShop"
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             INITIAL LOADING
             ============================================================ -->

        <div
            v-if="
                isLoading
                && !shop
            "
            class="loading-card shop-loading-card"
        >
            <div class="loading-icon-wrap">
                <div class="spinner" />
            </div>

            <h3>
                Loading shop profile
            </h3>

            <p>
                Retrieving shop information,
                owner account details and
                administrative status.
            </p>
        </div>


        <!-- ============================================================
             SHOP CONTENT
             ============================================================ -->

        <template v-else-if="shop">
            <!-- ========================================================
                 SHOP PROFILE HERO
                 ======================================================== -->

            <section class="shop-profile-hero">
                <div class="shop-profile-main">
                    <div class="shop-logo">
                        {{
                            shopInitial()
                        }}
                    </div>

                    <div class="shop-profile-copy">
                        <div class="shop-profile-eyebrow">
                            <span class="profile-dot" />

                            Registered shop

                            <span class="shop-id">
                                #{{ shop.id }}
                            </span>
                        </div>

                        <h2>
                            {{
                                shop.name
                                || 'Unnamed Shop'
                            }}
                        </h2>

                        <div class="shop-meta">
                            <div class="shop-meta-item">
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
                                        humanize(
                                            shop.type
                                        )
                                    }}
                                </span>
                            </div>

                            <div class="shop-meta-divider" />

                            <div class="shop-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"
                                    />

                                    <circle
                                        cx="12"
                                        cy="10"
                                        r="2"
                                    />
                                </svg>

                                <span>
                                    {{
                                        shop.city
                                        || 'Unknown City'
                                    }},
                                    {{
                                        shop.province
                                        || 'Unknown Province'
                                    }}
                                </span>
                            </div>

                            <div class="shop-meta-divider" />

                            <div class="shop-meta-item">
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
                                    Registered
                                    {{
                                        formatDate(
                                            shop.created_at
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- STATUS + ACTIONS -->

                <div class="profile-actions">
                    <div class="profile-statuses">
                        <span
                            class="status-badge"
                            :class="
                                statusClass(
                                    shop.approval_status
                                )
                            "
                        >
                            <span class="status-dot-small" />

                            {{
                                humanize(
                                    shop.approval_status
                                )
                            }}
                        </span>

                        <span
                            class="administrative-badge"
                            :class="
                                statusClass(
                                    shop.administrative_status
                                )
                            "
                        >
                            <span class="status-dot-small" />

                            {{
                                humanize(
                                    shop.administrative_status
                                )
                            }}
                        </span>
                    </div>


                    <div class="profile-action-buttons">
                        <template v-if="isPending">
                            <button
                                type="button"
                                class="reject-button"
                                :disabled="isProcessing"
                                @click="
                                    openRejectionModal
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
                                        d="M9 9l6 6M15 9l-6 6"
                                    />
                                </svg>

                                Reject
                            </button>

                            <button
                                type="button"
                                class="approve-button"
                                :disabled="isProcessing"
                                @click="
                                    openApprovalModal
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
                                        d="M8 12l3 3 5-6"
                                    />
                                </svg>

                                Approve shop
                            </button>
                        </template>


                        <button
                            v-if="canSuspend"
                            type="button"
                            class="suspend-button"
                            :disabled="isProcessing"
                            @click="
                                openSuspensionModal
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

                            Suspend shop
                        </button>


                        <button
                            v-if="canReactivate"
                            type="button"
                            class="reactivate-button"
                            :disabled="isProcessing"
                            @click="
                                openReactivationModal
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

                            Reactivate shop
                        </button>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 SUSPENSION WARNING
                 ======================================================== -->

            <section
                v-if="isSuspended"
                class="suspension-panel shop-suspension-panel"
            >
                <div class="suspension-panel-icon">
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

                <div class="suspension-panel-content">
                    <div class="suspension-panel-heading">
                        <div>
                            <p class="suspension-label">
                                ADMINISTRATIVE SUSPENSION
                            </p>

                            <h3>
                                Shop access is currently suspended
                            </h3>
                        </div>

                        <span class="administrative-badge suspended">
                            Suspended
                        </span>
                    </div>

                    <div class="suspension-reason">
                        <span class="reason-title">
                            Reason
                        </span>

                        <p>
                            {{
                                shop.suspension
                                    ?.reason
                                ||
                                'No suspension reason is available.'
                            }}
                        </p>
                    </div>

                    <div class="suspension-meta">
                        <span>
                            Suspended by

                            <strong>
                                {{
                                    shop.suspension
                                        ?.suspender
                                        ?.full_name
                                    ||
                                    'Administrator'
                                }}
                            </strong>
                        </span>

                        <span class="meta-separator">
                            •
                        </span>

                        <span>
                            {{
                                formatDate(
                                    shop.suspension
                                        ?.suspended_at,
                                    true,
                                )
                            }}
                        </span>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 STATISTICS
                 ======================================================== -->

            <section class="statistics-grid shop-statistics-grid">
                <!-- SERVICES -->

                <article class="shop-stat-card">
                    <div class="shop-stat-icon services">
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

                    <div class="shop-stat-copy">
                        <span>
                            Services
                        </span>

                        <strong>
                            {{
                                shop.statistics
                                    ?.services
                                ?? 0
                            }}
                        </strong>

                        <small>
                            {{
                                shop.statistics
                                    ?.active_services
                                ?? 0
                            }}
                            active services
                        </small>
                    </div>
                </article>


                <!-- APPOINTMENTS -->

                <article class="shop-stat-card">
                    <div class="shop-stat-icon appointments">
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

                    <div class="shop-stat-copy">
                        <span>
                            Appointments
                        </span>

                        <strong>
                            {{
                                shop.statistics
                                    ?.appointments
                                ?? 0
                            }}
                        </strong>

                        <small>
                            Total bookings
                        </small>
                    </div>
                </article>


                <!-- FEEDBACK -->

                <article class="shop-stat-card">
                    <div class="shop-stat-icon feedback">
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

                    <div class="shop-stat-copy">
                        <span>
                            Feedback
                        </span>

                        <strong>
                            {{
                                shop.statistics
                                    ?.feedback
                                ?? 0
                            }}
                        </strong>

                        <small>
                            Customer reviews
                        </small>
                    </div>
                </article>


                <!-- PROMOS -->

                <article class="shop-stat-card">
                    <div class="shop-stat-icon promos">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 8h16v12H4z"
                            />

                            <path
                                d="M8 8a2.5 2.5 0 110-5c3 0 4 5 4 5"
                            />

                            <path
                                d="M16 8a2.5 2.5 0 100-5c-3 0-4 5-4 5"
                            />

                            <path
                                d="M12 8v12"
                            />
                        </svg>
                    </div>

                    <div class="shop-stat-copy">
                        <span>
                            Promos
                        </span>

                        <strong>
                            {{
                                shop.statistics
                                    ?.promos
                                ?? 0
                            }}
                        </strong>

                        <small>
                            {{
                                shop.statistics
                                    ?.active_promos
                                ?? 0
                            }}
                            active promos
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 DETAILS GRID
                 ======================================================== -->

            <section class="shop-information-grid">
                <!-- BUSINESS DETAILS -->

                <article class="detail-card information-card">
                    <div class="card-heading">
                        <div class="card-heading-main">
                            <div class="card-heading-icon">
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
                                    SHOP INFORMATION
                                </p>

                                <h3>
                                    Business details
                                </h3>
                            </div>
                        </div>
                    </div>

                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>
                                Shop name
                            </dt>

                            <dd>
                                {{
                                    shop.name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Shop type
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        shop.type
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Email
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    shop.email
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Phone number
                            </dt>

                            <dd>
                                {{
                                    shop.phone_number
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Availability
                            </dt>

                            <dd>
                                <span
                                    class="availability-badge"
                                    :class="
                                        statusClass(
                                            shop.availability_status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            shop.availability_status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Admin status
                            </dt>

                            <dd>
                                <span
                                    class="administrative-badge"
                                    :class="
                                        statusClass(
                                            shop.administrative_status
                                        )
                                    "
                                >
                                    {{
                                        humanize(
                                            shop.administrative_status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Registered
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        shop.created_at,
                                        true,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <div class="description-section">
                        <div class="description-heading">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 5h16v14H4z"
                                />

                                <path
                                    d="M8 9h8M8 13h8"
                                />
                            </svg>

                            <span>
                                Description
                            </span>
                        </div>

                        <p>
                            {{
                                shop.description
                                ||
                                'No shop description provided.'
                            }}
                        </p>
                    </div>
                </article>


                <!-- OWNER -->

                <article class="detail-card information-card">
                    <div class="card-heading">
                        <div class="card-heading-main">
                            <div class="card-heading-icon owner">
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
                                    SHOP OWNER
                                </p>

                                <h3>
                                    Owner account
                                </h3>
                            </div>
                        </div>

                        <span
                            v-if="shop.owner"
                            class="owner-status"
                            :class="
                                statusClass(
                                    shop.owner.status
                                )
                            "
                        >
                            {{
                                humanize(
                                    shop.owner.status
                                )
                            }}
                        </span>
                    </div>


                    <div
                        v-if="shop.owner"
                        class="owner-profile"
                    >
                        <div class="owner-avatar">
                            {{
                                shop.owner
                                    ?.full_name
                                    ?.charAt(0)
                                    ?.toUpperCase()
                                || 'O'
                            }}
                        </div>

                        <div class="owner-profile-copy">
                            <strong>
                                {{
                                    shop.owner
                                        ?.full_name
                                    || 'Unknown owner'
                                }}
                            </strong>

                            <span>
                                {{
                                    shop.owner
                                        ?.email
                                    || 'No email'
                                }}
                            </span>
                        </div>
                    </div>


                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>
                                Full name
                            </dt>

                            <dd>
                                {{
                                    shop.owner
                                        ?.full_name
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Email
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    shop.owner
                                        ?.email
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Phone number
                            </dt>

                            <dd>
                                {{
                                    shop.owner
                                        ?.phone_number
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Role
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        shop.owner
                                            ?.role
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Last login
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        shop.owner
                                            ?.last_login_at,
                                        true,
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Account created
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        shop.owner
                                            ?.created_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- LOCATION -->

                <article class="detail-card information-card">
                    <div class="card-heading">
                        <div class="card-heading-main">
                            <div class="card-heading-icon location">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"
                                    />

                                    <circle
                                        cx="12"
                                        cy="10"
                                        r="2"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    LOCATION
                                </p>

                                <h3>
                                    Shop address
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="address-preview">
                        <div class="address-preview-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 21s6-5.2 6-11a6 6 0 10-12 0c0 5.8 6 11 6 11z"
                                />

                                <circle
                                    cx="12"
                                    cy="10"
                                    r="2"
                                />
                            </svg>
                        </div>

                        <div>
                            <strong>
                                {{
                                    shop.city
                                    || 'Location unavailable'
                                }}
                            </strong>

                            <span>
                                {{
                                    shop.province
                                    || 'Province not specified'
                                }}
                            </span>
                        </div>
                    </div>

                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>
                                Address
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    shop.address
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                City
                            </dt>

                            <dd>
                                {{
                                    shop.city
                                    || '—'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Province
                            </dt>

                            <dd>
                                {{
                                    shop.province
                                    || '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- REVIEW -->

                <article class="detail-card information-card">
                    <div class="card-heading">
                        <div class="card-heading-main">
                            <div class="card-heading-icon review">
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
                                        d="M9 11h6M9 15h4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    ADMINISTRATIVE REVIEW
                                </p>

                                <h3>
                                    Review information
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div class="review-status-card">
                        <span>
                            Current approval status
                        </span>

                        <strong>
                            <span
                                class="status-badge"
                                :class="
                                    statusClass(
                                        shop.approval_status
                                    )
                                "
                            >
                                <span class="status-dot-small" />

                                {{
                                    humanize(
                                        shop.approval_status
                                    )
                                }}
                            </span>
                        </strong>
                    </div>

                    <dl class="detail-list">
                        <div class="detail-row">
                            <dt>
                                Reviewed by
                            </dt>

                            <dd>
                                {{
                                    shop.review
                                        ?.reviewer
                                        ?.full_name
                                    ||
                                    'Not reviewed yet'
                                }}
                            </dd>
                        </div>

                        <div class="detail-row">
                            <dt>
                                Reviewed at
                            </dt>

                            <dd>
                                {{
                                    formatDate(
                                        shop.review
                                            ?.reviewed_at,
                                        true,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="
                            shop.review
                                ?.rejection_reason
                        "
                        class="rejection-section"
                    >
                        <div class="rejection-heading">
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
                                Rejection reason
                            </span>
                        </div>

                        <p>
                            {{
                                shop.review
                                    .rejection_reason
                            }}
                        </p>
                    </div>
                </article>
            </section>
        </template>


        <!-- ============================================================
             APPROVE MODAL
             ============================================================ -->

        <div
            v-if="
                modal
                === 'approve'
            "
            class="modal-backdrop"
            @click.self="closeModal"
        >
            <div class="modal-card action-modal">
                <div class="modal-top">
                    <div class="modal-icon approve">
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

                    <div class="modal-heading">
                        <span class="modal-eyebrow">
                            SHOP APPROVAL
                        </span>

                        <h3>
                            Approve shop?
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    You are about to approve

                    <strong>
                        {{ shop?.name }}
                    </strong>.

                    The Shop Owner will be able
                    to access approved
                    shop-management features.
                </p>

                <div class="approval-notice">
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

                    <div>
                        <strong>
                            Administrator action
                        </strong>

                        <span>
                            Confirm that the shop
                            registration information
                            has been reviewed.
                        </span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="approve-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="approveShop"
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
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M8 12l3 3 5-6"
                            />
                        </svg>

                        {{
                            isProcessing
                                ? 'Approving...'
                                : 'Approve shop'
                        }}
                    </button>
                </div>
            </div>
        </div>


        <!-- ============================================================
             REJECT MODAL
             ============================================================ -->

        <div
            v-if="
                modal
                === 'reject'
            "
            class="modal-backdrop"
            @click.self="closeModal"
        >
            <div class="modal-card action-modal">
                <div class="modal-top">
                    <div class="modal-icon reject">
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

                    <div class="modal-heading">
                        <span class="modal-eyebrow danger">
                            SHOP REVIEW
                        </span>

                        <h3>
                            Reject shop registration
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    Provide a clear reason
                    explaining why

                    <strong>
                        {{ shop?.name }}
                    </strong>

                    cannot be approved.
                </p>

                <div class="reason-field">
                    <div class="reason-label-row">
                        <label
                            class="reason-label"
                            for="rejection-reason"
                        >
                            Rejection reason
                        </label>

                        <span>
                            Required
                        </span>
                    </div>

                    <textarea
                        id="rejection-reason"
                        v-model="
                            rejectionReason
                        "
                        minlength="10"
                        maxlength="1000"
                        rows="6"
                        placeholder="Explain why this shop registration is being rejected..."
                        :disabled="
                            isProcessing
                        "
                    />

                    <div class="reason-footer">
                        <span
                            v-if="
                                rejectionError
                            "
                            class="field-error"
                        >
                            {{ rejectionError }}
                        </span>

                        <span
                            v-else
                            class="reason-helper"
                        >
                            Minimum 10 characters.
                        </span>

                        <span class="character-count">
                            {{
                                rejectionReason
                                    .length
                            }}/1000
                        </span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="reject-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="rejectShop"
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
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />

                            <path
                                d="M9 9l6 6M15 9l-6 6"
                            />
                        </svg>

                        {{
                            isProcessing
                                ? 'Rejecting...'
                                : 'Reject shop'
                        }}
                    </button>
                </div>
            </div>
        </div>


        <!-- ============================================================
             SUSPEND MODAL
             ============================================================ -->

        <div
            v-if="
                modal
                === 'suspend'
            "
            class="modal-backdrop"
            @click.self="closeModal"
        >
            <div class="modal-card action-modal">
                <div class="modal-top">
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

                    <div class="modal-heading">
                        <span class="modal-eyebrow danger">
                            ADMINISTRATIVE ACTION
                        </span>

                        <h3>
                            Suspend shop?
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    Suspending

                    <strong>
                        {{ shop?.name }}
                    </strong>

                    will hide the shop from
                    customers and block
                    shop-management actions.
                    The Shop Owner account itself
                    remains active.
                </p>

                <div class="reason-field">
                    <div class="reason-label-row">
                        <label
                            class="reason-label"
                            for="suspension-reason"
                        >
                            Suspension reason
                        </label>

                        <span>
                            Required
                        </span>
                    </div>

                    <textarea
                        id="suspension-reason"
                        v-model="
                            suspensionReason
                        "
                        minlength="10"
                        maxlength="1000"
                        rows="6"
                        placeholder="Explain why this shop is being suspended..."
                        :disabled="
                            isProcessing
                        "
                    />

                    <div class="reason-footer">
                        <span
                            v-if="
                                suspensionError
                            "
                            class="field-error"
                        >
                            {{ suspensionError }}
                        </span>

                        <span
                            v-else
                            class="reason-helper"
                        >
                            Minimum 10 characters.
                        </span>

                        <span class="character-count">
                            {{
                                suspensionReason
                                    .length
                            }}/1000
                        </span>
                    </div>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="suspend-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="suspendShop"
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
                                : 'Suspend shop'
                        }}
                    </button>
                </div>
            </div>
        </div>


        <!-- ============================================================
             REACTIVATE MODAL
             ============================================================ -->

        <div
            v-if="
                modal
                === 'reactivate'
            "
            class="modal-backdrop"
            @click.self="closeModal"
        >
            <div class="modal-card action-modal">
                <div class="modal-top">
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

                    <div class="modal-heading">
                        <span class="modal-eyebrow success">
                            ADMINISTRATIVE ACTION
                        </span>

                        <h3>
                            Reactivate shop?
                        </h3>
                    </div>
                </div>

                <p class="modal-description">
                    Reactivating

                    <strong>
                        {{ shop?.name }}
                    </strong>

                    will remove its independent
                    administrative suspension.
                    Normal customer visibility
                    and Shop Owner management
                    can resume if all other
                    requirements are satisfied.
                </p>

                <div class="reactivation-note">
                    <div class="reactivation-note-icon">
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
                            Current suspension reason
                        </strong>

                        <p>
                            {{
                                shop?.suspension
                                    ?.reason
                                ||
                                'No suspension reason is available.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="modal-actions">
                    <button
                        type="button"
                        class="cancel-button"
                        :disabled="
                            isProcessing
                        "
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="reactivate-button modal-confirm-button"
                        :disabled="
                            isProcessing
                        "
                        @click="reactivateShop"
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
                                : 'Reactivate shop'
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

.shop-detail-page {
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

.shop-detail-page .back-button {
    min-height: 38px;

    margin: 0;
}

.refresh-detail-button svg,
.back-button svg {
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

.shop-loading-card {
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

.loading-icon-wrap .spinner {
    margin: 0;
}


/*
|--------------------------------------------------------------------------
| SHOP HERO
|--------------------------------------------------------------------------
*/

.shop-profile-hero {
    position: relative;

    overflow: hidden;

    min-height: 170px;

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

.shop-profile-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.shop-profile-hero .shop-logo {
    width: 68px;
    height: 68px;

    flex:
        0
        0
        68px;

    border:
        1px solid
        rgba(
            214,
            154,
            45,
            0.18
        );

    border-radius: 19px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary),
            var(--sh-primary-2)
        );

    color:
        var(--sh-accent);

    font-size: 23px;
    font-weight: 800;

    box-shadow:
        0 10px 24px
        rgba(
            23,
            32,
            42,
            0.13
        );
}

.shop-profile-copy {
    min-width: 0;

    flex: 1;
}

.shop-profile-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.05px;

    text-transform: uppercase;
}

.profile-dot {
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

.shop-id {
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

.shop-profile-copy h2 {
    max-width: 700px;

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
| HERO METADATA
|--------------------------------------------------------------------------
*/

.shop-meta {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.shop-meta-item {
    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.shop-meta-item svg {
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

.shop-meta-divider {
    width: 3px;
    height: 3px;

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
    max-width: 520px;

    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 12px;
}

.profile-statuses,
.profile-action-buttons {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 8px;

    flex-wrap: wrap;
}

.status-dot-small {
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
| SUSPENSION
|--------------------------------------------------------------------------
*/

.shop-suspension-panel {
    display: flex;
    align-items: flex-start;

    gap: 12px;

    padding: 16px;
}

.shop-suspension-panel
.suspension-panel-heading {
    align-items: flex-start;
}

.suspension-label {
    margin:
        0
        0
        3px;

    color:
        var(--sh-danger);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1px;
}

.suspension-panel-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 13px;
    font-weight: 800;
}

.reason-title {
    display: block;

    margin-bottom: 3px;

    color:
        var(--sh-danger);

    font-size: 8px;
    font-weight: 800;

    text-transform: uppercase;

    letter-spacing: 0.6px;
}

.suspension-reason p {
    margin: 0;
}

.meta-separator {
    color:
        #c1c7cf;
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

.shop-statistics-grid {
    gap: 11px;
}

.shop-stat-card {
    min-width: 0;

    min-height: 104px;

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

.shop-stat-card:hover {
    transform:
        translateY(
            -1px
        );

    box-shadow:
        var(--sh-shadow-card);
}

.shop-stat-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.shop-stat-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.65;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.shop-stat-icon.services {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.shop-stat-icon.appointments {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.shop-stat-icon.feedback {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.shop-stat-icon.promos {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.shop-stat-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.shop-stat-copy span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.shop-stat-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 20px;
    font-weight: 800;

    line-height: 24px;

    letter-spacing:
        -0.4px;
}

.shop-stat-copy small {
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

.shop-information-grid {
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

.information-card .card-heading {
    min-height: 68px;

    align-items: center;

    padding:
        13px
        15px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.card-heading-main {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.card-heading-icon {
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

.card-heading-icon.owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.card-heading-icon.location {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.card-heading-icon.review {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.card-heading-icon svg {
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

.information-card .eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.information-card .card-heading h3 {
    margin: 0;

    font-size: 12px;
}


/*
|--------------------------------------------------------------------------
| DETAIL LIST
|--------------------------------------------------------------------------
*/

.detail-list {
    margin: 0;

    padding:
        4px
        15px;
}

.detail-row {
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

.detail-row:last-child {
    border-bottom: 0;
}

.detail-row dt {
    color:
        var(--sh-text-3);

    font-size: 9px;
    font-weight: 650;
}

.detail-row dd {
    min-width: 0;

    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 10px;
    font-weight: 650;

    text-align: right;
}


/*
|--------------------------------------------------------------------------
| DESCRIPTION
|--------------------------------------------------------------------------
*/

.description-section {
    margin:
        3px
        15px
        15px;

    padding:
        12px;

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.description-heading {
    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-2);

    font-size: 8.5px;
    font-weight: 800;
}

.description-heading svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.description-section p {
    margin:
        6px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| OWNER PROFILE
|--------------------------------------------------------------------------
*/

.owner-profile {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        4px;

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

.owner-avatar {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary),
            var(--sh-primary-2)
        );

    color:
        #ffffff;

    font-size: 12px;
    font-weight: 800;
}

.owner-profile-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.owner-profile-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.owner-profile-copy span {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    text-overflow: ellipsis;
    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| LOCATION PREVIEW
|--------------------------------------------------------------------------
*/

.address-preview {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        12px;

    border:
        1px solid
        #dce5f6;

    border-radius: 11px;

    background:
        var(--sh-info-bg);
}

.address-preview-icon {
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

.address-preview-icon svg {
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

.address-preview > div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.address-preview strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.address-preview span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
}


/*
|--------------------------------------------------------------------------
| REVIEW STATUS
|--------------------------------------------------------------------------
*/

.review-status-card {
    display: flex;
    align-items: center;
    justify-content: space-between;

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

.review-status-card > span {
    color:
        var(--sh-text-3);

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| REJECTION
|--------------------------------------------------------------------------
*/

.rejection-section {
    margin:
        6px
        15px
        15px;

    padding:
        11px
        12px;

    border:
        1px solid
        #efd4d4;

    border-radius: 11px;

    background:
        var(--sh-danger-bg);
}

.rejection-heading {
    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-danger);

    font-size: 8.5px;
    font-weight: 800;
}

.rejection-heading svg {
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

.rejection-section p {
    margin:
        6px
        0
        0;

    color:
        #8d4c4c;

    font-size: 9px;

    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| MODALS
|--------------------------------------------------------------------------
*/

.action-modal {
    width:
        min(
            530px,
            100%
        );

    padding: 20px;
}

.modal-top {
    display: flex;
    align-items: center;

    gap: 11px;

    margin-bottom: 12px;
}

.action-modal .modal-icon {
    margin: 0;
}

.action-modal .modal-icon svg {
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

.modal-icon.approve,
.modal-icon.reactivate {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.modal-icon.reject,
.modal-icon.suspend {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.modal-heading {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.modal-heading h3 {
    font-size: 15px;
}

.modal-eyebrow {
    color:
        var(--sh-accent);

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
| MODAL NOTICE
|--------------------------------------------------------------------------
*/

.approval-notice {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin-top: 14px;

    padding:
        11px;

    border:
        1px solid
        #d7eadf;

    border-radius: 10px;

    background:
        var(--sh-success-bg);
}

.approval-notice > svg {
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

.approval-notice > div {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.approval-notice strong {
    color:
        var(--sh-success);

    font-size: 9px;
}

.approval-notice span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| REASON FIELD
|--------------------------------------------------------------------------
*/

.reason-field {
    margin-top: 15px;
}

.reason-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    margin-bottom: 6px;
}

.reason-label-row span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;

    text-transform: uppercase;

    letter-spacing: 0.5px;
}

.reason-field textarea {
    min-height: 125px;
}

.reason-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;

    margin-top: 6px;

    padding: 0;

    background: none;
}

.reason-helper {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.character-count {
    margin-left: auto;

    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| REACTIVATION NOTE
|--------------------------------------------------------------------------
*/

.reactivation-note {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin-top: 14px;

    padding:
        11px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 10px;

    background:
        var(--sh-surface-muted);
}

.reactivation-note-icon {
    width: 31px;
    height: 31px;

    flex: 0 0 31px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        #ffffff;

    color:
        var(--sh-text-3);
}

.reactivation-note-icon svg {
    width: 16px;
    height: 16px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.reactivation-note > div:last-child {
    min-width: 0;
}

.reactivation-note strong {
    color:
        var(--sh-text-2);

    font-size: 9px;
}

.reactivation-note p {
    margin:
        3px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| MODAL BUTTON
|--------------------------------------------------------------------------
*/

.modal-confirm-button {
    min-width: 125px;
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
        shop-button-spin
        0.7s
        linear
        infinite;
}


/*
|--------------------------------------------------------------------------
| REFRESH ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        shop-spin
        0.8s
        linear
        infinite;
}

@keyframes shop-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}

@keyframes shop-button-spin {
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

@media (max-width: 1200px) {
    .shop-profile-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .profile-actions {
        width: 100%;
        max-width: none;

        align-items:
            flex-start;
    }

    .profile-statuses,
    .profile-action-buttons {
        justify-content:
            flex-start;
    }
}


@media (max-width: 900px) {
    .shop-information-grid {
        grid-template-columns:
            1fr;
    }

    .shop-statistics-grid {
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
    .detail-navigation {
        align-items: stretch;

        flex-direction: column;
    }

    .detail-navigation .back-button,
    .detail-navigation
    .refresh-detail-button {
        width: 100%;
    }

    .detail-alert {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .detail-alert .retry-button {
        width: 100%;
    }

    .shop-profile-hero {
        padding: 17px;
    }

    .shop-profile-main {
        align-items:
            flex-start;
    }

    .shop-profile-hero
    .shop-logo {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;

        font-size: 18px;
    }

    .shop-profile-copy h2 {
        font-size: 20px;

        line-height: 25px;
    }

    .shop-meta {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .shop-meta-divider {
        display: none;
    }

    .profile-actions {
        align-items: stretch;
    }

    .profile-statuses {
        justify-content:
            flex-start;
    }

    .profile-action-buttons {
        align-items: stretch;

        flex-direction:
            column;
    }

    .profile-action-buttons button {
        width: 100%;
    }

    .shop-suspension-panel {
        flex-direction: column;
    }

    .suspension-panel-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .suspension-meta {
        display: flex;
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 3px;
    }

    .meta-separator {
        display: none;
    }

    .shop-statistics-grid {
        grid-template-columns:
            1fr;
    }

    .detail-row {
        grid-template-columns:
            1fr;

        gap: 4px;

        align-items:
            flex-start;
    }

    .detail-row dd {
        text-align: left;
    }

    .information-card
    .card-heading {
        align-items:
            flex-start;
    }

    .owner-status {
        flex: 0 0 auto;
    }

    .review-status-card {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .modal-actions {
        width: 100%;

        align-items: stretch;
    }

    .modal-actions button {
        width: 100%;
    }
}
</style>