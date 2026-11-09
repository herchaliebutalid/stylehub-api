<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const dashboard = ref(null);
const isLoading = ref(false);
const errorMessage = ref('');
const lastUpdated = ref('');

const numberFormatter = new Intl.NumberFormat('en-PH');

function formatNumber(value) {
    const number = Number(value ?? 0);

    return Number.isFinite(number)
        ? numberFormatter.format(number)
        : '0';
}

function percentage(value, total) {
    const current = Number(value ?? 0);
    const maximum = Number(total ?? 0);

    if (
        !Number.isFinite(current) ||
        !Number.isFinite(maximum) ||
        maximum <= 0
    ) {
        return 0;
    }

    return Math.max(
        0,
        Math.min(
            100,
            Math.round((current / maximum) * 100)
        )
    );
}

/*
|--------------------------------------------------------------------------
| DASHBOARD DATA
|--------------------------------------------------------------------------
*/

const customerSummary = computed(() =>
    dashboard.value?.users?.customers ?? {
        total: 0,
        active: 0,
        suspended: 0,
    }
);

const shopOwnerSummary = computed(() =>
    dashboard.value?.users?.shop_owners ?? {
        total: 0,
        active: 0,
        suspended: 0,
    }
);

const shopSummary = computed(() =>
    dashboard.value?.shops ?? {
        total: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
    }
);

const appointmentSummary = computed(() =>
    dashboard.value?.appointments ?? {
        total: 0,
        pending: 0,
        confirmed: 0,
        completed: 0,
        no_show: 0,
        cancelled: 0,
        declined: 0,
    }
);

const otherSummary = computed(() =>
    dashboard.value?.other ?? {
        feedback: 0,
        active_promos: 0,
        audit_logs: 0,
    }
);

/*
|--------------------------------------------------------------------------
| COMPUTED STATISTICS
|--------------------------------------------------------------------------
*/

const totalAccounts = computed(() =>
    Number(customerSummary.value.total ?? 0) +
    Number(shopOwnerSummary.value.total ?? 0)
);

const activeAccounts = computed(() =>
    Number(customerSummary.value.active ?? 0) +
    Number(shopOwnerSummary.value.active ?? 0)
);

const suspendedAccounts = computed(() =>
    Number(customerSummary.value.suspended ?? 0) +
    Number(shopOwnerSummary.value.suspended ?? 0)
);

const accountActivePercentage = computed(() =>
    percentage(
        activeAccounts.value,
        totalAccounts.value
    )
);

const appointmentCompletionPercentage = computed(() =>
    percentage(
        appointmentSummary.value.completed,
        appointmentSummary.value.total
    )
);

const overviewCards = computed(() => [
    {
        type: 'customers',
        title: 'Customers',
        value: customerSummary.value.total,
        detail: `${formatNumber(customerSummary.value.active)} active accounts`,
    },
    {
        type: 'owners',
        title: 'Shop owners',
        value: shopOwnerSummary.value.total,
        detail: `${formatNumber(shopOwnerSummary.value.active)} active accounts`,
    },
    {
        type: 'shops',
        title: 'Registered shops',
        value: shopSummary.value.total,
        detail: `${formatNumber(shopSummary.value.pending)} awaiting review`,
    },
    {
        type: 'appointments',
        title: 'Appointments',
        value: appointmentSummary.value.total,
        detail: `${formatNumber(appointmentSummary.value.pending)} pending bookings`,
    },
]);

const accountGroups = computed(() => [
    {
        key: 'customers',
        title: 'Customers',
        data: customerSummary.value,
    },
    {
        key: 'owners',
        title: 'Shop owners',
        data: shopOwnerSummary.value,
    },
]);

const shopStatuses = computed(() => [
    {
        key: 'approved',
        title: 'Approved',
        subtitle: 'Published shops',
        count: shopSummary.value.approved,
    },
    {
        key: 'pending',
        title: 'Pending review',
        subtitle: 'Awaiting decision',
        count: shopSummary.value.pending,
    },
    {
        key: 'rejected',
        title: 'Rejected',
        subtitle: 'Not approved',
        count: shopSummary.value.rejected,
    },
]);

const bookingStatuses = computed(() => [
    {
        key: 'pending',
        title: 'Pending',
        count: appointmentSummary.value.pending,
    },
    {
        key: 'confirmed',
        title: 'Confirmed',
        count: appointmentSummary.value.confirmed,
    },
    {
        key: 'completed',
        title: 'Completed',
        count: appointmentSummary.value.completed,
    },
    {
        key: 'no_show',
        title: 'No-show',
        count: appointmentSummary.value.no_show,
    },
    {
        key: 'cancelled',
        title: 'Cancelled',
        count: appointmentSummary.value.cancelled,
    },
    {
        key: 'declined',
        title: 'Declined',
        count: appointmentSummary.value.declined,
    },
]);

const platformActivity = computed(() => [
    {
        key: 'feedback',
        title: 'Customer feedback',
        subtitle: 'Submitted reviews',
        count: otherSummary.value.feedback,
    },
    {
        key: 'promo',
        title: 'Active promotions',
        subtitle: 'Currently available offers',
        count: otherSummary.value.active_promos,
    },
    {
        key: 'audit',
        title: 'Audit logs',
        subtitle: 'Administrative records',
        count: otherSummary.value.audit_logs,
    },
]);

/*
|--------------------------------------------------------------------------
| FETCH DASHBOARD DATA
|--------------------------------------------------------------------------
*/

async function loadDashboard() {
    if (!auth.token) {
        auth.clearSession();

        await router.replace({
            name: 'admin.login',
        });

        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            '/admin/dashboard',
            {
                token: auth.token,
            }
        );

        if (
            !response?.data ||
            typeof response.data !== 'object'
        ) {
            throw new Error(
                'The dashboard response was invalid.'
            );
        }

        dashboard.value = response.data;

        lastUpdated.value = new Intl.DateTimeFormat(
            'en-PH',
            {
                hour: 'numeric',
                minute: '2-digit',
            }
        ).format(new Date());

    } catch (error) {
        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            auth.clearSession();

            await router.replace({
                name: 'admin.login',
            });

            return;
        }

        errorMessage.value =
            error?.data?.message ??
            error?.message ??
            'Unable to load the dashboard.';

    } finally {
        isLoading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| WAIT FOR INITIAL DASHBOARD DATA
|--------------------------------------------------------------------------
|
| The updated AdminLayout.vue provides a Suspense boundary.
| The dashboard will be rendered only after this initial
| request finishes.
|
| Subsequent manual refreshes preserve the displayed data.
|
*/

await loadDashboard();
</script>

<template>
    <div class="dashboard sh-dashboard">

        <!-- DASHBOARD HEADER -->

        <header class="sh-dash-header">
            <div class="sh-dash-heading">
                <p class="sh-dash-eyebrow">
                    STYLEHUB ADMINISTRATION
                </p>

                <h1>Dashboard</h1>

                <p class="sh-dash-subtitle">
                    A clear overview of users, shops,
                    appointments, and platform activity.
                </p>
            </div>

            <div class="sh-dash-toolbar">
                <span
                    v-if="lastUpdated"
                    class="sh-dash-updated"
                >
                    Updated {{ lastUpdated }}
                </span>

                <button
                    type="button"
                    class="sh-dash-refresh"
                    :disabled="isLoading"
                    @click="loadDashboard"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M20 11a8 8 0 1 1-2.3-5.7M20 4v6h-6"
                        />
                    </svg>

                    Refresh
                </button>
            </div>
        </header>

        <!-- ERROR MESSAGE -->

        <div
            v-if="errorMessage"
            class="sh-dash-error"
            role="alert"
        >
            <div>
                <strong>
                    Unable to update dashboard
                </strong>

                <p>{{ errorMessage }}</p>
            </div>

            <button
                type="button"
                :disabled="isLoading"
                @click="loadDashboard"
            >
                Try again
            </button>
        </div>

        <!-- DASHBOARD CONTENT -->

        <template v-if="dashboard">

            <!-- OVERVIEW -->

            <section
                class="sh-dash-section"
                aria-labelledby="overview-title"
            >
                <div class="sh-dash-section-heading">
                    <h2 id="overview-title">
                        Overview
                    </h2>

                    <span>Platform totals</span>
                </div>

                <div class="sh-dash-metrics">
                    <article
                        v-for="card in overviewCards"
                        :key="card.type"
                        class="sh-dash-metric"
                    >
                        <div class="sh-dash-metric-top">
                            <span class="sh-dash-metric-label">
                                {{ card.title }}
                            </span>

                            <span
                                class="sh-dash-icon"
                                :class="`sh-dash-icon--${card.type}`"
                            >
                                <svg
                                    v-if="card.type === 'customers'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 20v-2a4 4 0 0 1-3-3.87M16 2.13a4 4 0 0 1 0 7.75"
                                    />
                                </svg>

                                <svg
                                    v-else-if="card.type === 'owners'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle
                                        cx="12"
                                        cy="8"
                                        r="4"
                                    />

                                    <path
                                        d="M4 21v-2a8 8 0 0 1 16 0v2M18 4h4M20 2v4"
                                    />
                                </svg>

                                <svg
                                    v-else-if="card.type === 'shops'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 10h18l-2-6H5l-2 6zM5 10v10h14V10M9 20v-7h6v7M3 10c0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0"
                                    />
                                </svg>

                                <svg
                                    v-else
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
                                        d="M7 3v4M17 3v4M3 10h18M8 15h3M14 15h2"
                                    />
                                </svg>
                            </span>
                        </div>

                        <strong class="sh-dash-metric-value">
                            {{ formatNumber(card.value) }}
                        </strong>

                        <p class="sh-dash-metric-detail">
                            {{ card.detail }}
                        </p>
                    </article>
                </div>
            </section>

            <!-- MANAGEMENT OVERVIEW -->

            <section
                class="sh-dash-section"
                aria-labelledby="management-title"
            >
                <div class="sh-dash-section-heading">
                    <h2 id="management-title">
                        Management overview
                    </h2>

                    <span>
                        Accounts &amp; registrations
                    </span>
                </div>

                <div class="sh-dash-panels">

                    <!-- USER ACCOUNTS -->

                    <article class="sh-dash-panel">
                        <div class="sh-dash-panel-head">
                            <div>
                                <h3>User accounts</h3>

                                <p>
                                    Customer and business account status
                                </p>
                            </div>

                            <span class="sh-dash-badge">
                                {{ formatNumber(totalAccounts) }} total
                            </span>
                        </div>

                        <div class="sh-dash-main-total">
                            <div>
                                <span class="sh-dash-field-label">
                                    Active accounts
                                </span>

                                <div class="sh-dash-main-number">
                                    {{ formatNumber(activeAccounts) }}
                                </div>
                            </div>

                            <span class="sh-dash-percent">
                                {{ accountActivePercentage }}% active
                            </span>
                        </div>

                        <div
                            class="sh-dash-track"
                            role="progressbar"
                            aria-label="Active account percentage"
                            :aria-valuenow="accountActivePercentage"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span
                                class="sh-dash-fill sh-dash-fill--active"
                                :style="{
                                    width: `${accountActivePercentage}%`
                                }"
                            ></span>
                        </div>

                        <div class="sh-dash-account-list">
                            <div
                                v-for="group in accountGroups"
                                :key="group.key"
                                class="sh-dash-account-row"
                            >
                                <div class="sh-dash-account-title">
                                    <strong>
                                        {{ group.title }}
                                    </strong>

                                    <strong>
                                        {{ formatNumber(group.data.total) }}
                                    </strong>
                                </div>

                                <div class="sh-dash-account-meta">
                                    <span>
                                        <i
                                            class="sh-dash-dot sh-dash-dot--active"
                                        ></i>

                                        {{ formatNumber(group.data.active) }}
                                        active
                                    </span>

                                    <span>
                                        <i
                                            class="sh-dash-dot sh-dash-dot--suspended"
                                        ></i>

                                        {{ formatNumber(group.data.suspended) }}
                                        suspended
                                    </span>
                                </div>

                                <div
                                    class="sh-dash-track sh-dash-track--small"
                                    role="progressbar"
                                    :aria-label="`${group.title} active percentage`"
                                    :aria-valuenow="percentage(group.data.active, group.data.total)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <span
                                        class="sh-dash-fill sh-dash-fill--active"
                                        :style="{
                                            width: `${percentage(
                                                group.data.active,
                                                group.data.total
                                            )}%`
                                        }"
                                    ></span>
                                </div>
                            </div>
                        </div>

                        <p class="sh-dash-panel-foot">
                            {{ formatNumber(suspendedAccounts) }}
                            account{{ suspendedAccounts === 1 ? '' : 's' }}
                            currently suspended
                        </p>
                    </article>

                    <!-- SHOP APPROVALS -->

                    <article class="sh-dash-panel">
                        <div class="sh-dash-panel-head">
                            <div>
                                <h3>Shop approvals</h3>

                                <p>
                                    Registration and approval breakdown
                                </p>
                            </div>

                            <span class="sh-dash-badge">
                                {{ formatNumber(shopSummary.total) }}
                                shops
                            </span>
                        </div>

                        <div class="sh-dash-shop-list">
                            <div
                                v-for="status in shopStatuses"
                                :key="status.key"
                                class="sh-dash-shop-row"
                            >
                                <div class="sh-dash-shop-info">
                                    <span
                                        class="sh-dash-dot"
                                        :class="`sh-dash-dot--${status.key}`"
                                    ></span>

                                    <div>
                                        <strong>
                                            {{ status.title }}
                                        </strong>

                                        <small>
                                            {{ status.subtitle }}
                                        </small>
                                    </div>

                                    <div class="sh-dash-shop-values">
                                        <strong>
                                            {{ formatNumber(status.count) }}
                                        </strong>

                                        <span>
                                            {{
                                                percentage(
                                                    status.count,
                                                    shopSummary.total
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="sh-dash-track sh-dash-track--small"
                                    role="progressbar"
                                    :aria-label="`${status.title} shops percentage`"
                                    :aria-valuenow="percentage(status.count, shopSummary.total)"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                >
                                    <span
                                        class="sh-dash-fill"
                                        :class="`sh-dash-fill--${status.key}`"
                                        :style="{
                                            width: `${percentage(
                                                status.count,
                                                shopSummary.total
                                            )}%`
                                        }"
                                    ></span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="Number(shopSummary.pending) > 0"
                            class="sh-dash-notice"
                        >
                            <span
                                class="sh-dash-notice-symbol"
                                aria-hidden="true"
                            >
                                !
                            </span>

                            <p>
                                <strong>
                                    {{ formatNumber(shopSummary.pending) }}
                                    shop{{ Number(shopSummary.pending) === 1 ? '' : 's' }}
                                    pending review.
                                </strong>

                                Awaiting administrator action.
                            </p>
                        </div>

                        <p
                            v-else
                            class="sh-dash-panel-foot"
                        >
                            No shop registrations awaiting review
                        </p>
                    </article>
                </div>
            </section>

            <!-- PLATFORM ACTIVITY -->

            <section
                class="sh-dash-section"
                aria-labelledby="activity-title"
            >
                <div class="sh-dash-section-heading">
                    <h2 id="activity-title">
                        Platform activity
                    </h2>

                    <span>
                        Bookings &amp; records
                    </span>
                </div>

                <div class="sh-dash-panels">

                    <!-- APPOINTMENTS -->

                    <article class="sh-dash-panel">
                        <div class="sh-dash-panel-head">
                            <div>
                                <h3>Appointment activity</h3>

                                <p>
                                    Bookings by current status
                                </p>
                            </div>

                            <span class="sh-dash-badge">
                                {{ formatNumber(appointmentSummary.total) }}
                                total
                            </span>
                        </div>

                        <div class="sh-dash-booking-grid">
                            <div
                                v-for="item in bookingStatuses"
                                :key="item.key"
                                class="sh-dash-booking-item"
                            >
                                <span
                                    class="sh-dash-booking-dot"
                                    :class="`sh-dash-booking-dot--${item.key}`"
                                ></span>

                                <span>
                                    {{ item.title }}
                                </span>

                                <strong>
                                    {{ formatNumber(item.count) }}
                                </strong>
                            </div>
                        </div>

                        <div class="sh-dash-completion">
                            <div class="sh-dash-completion-head">
                                <span>
                                    Completed appointments
                                </span>

                                <strong>
                                    {{ appointmentCompletionPercentage }}%
                                </strong>
                            </div>

                            <div
                                class="sh-dash-track"
                                role="progressbar"
                                aria-label="Completed appointments percentage"
                                :aria-valuenow="appointmentCompletionPercentage"
                                aria-valuemin="0"
                                aria-valuemax="100"
                            >
                                <span
                                    class="sh-dash-fill sh-dash-fill--active"
                                    :style="{
                                        width: `${appointmentCompletionPercentage}%`
                                    }"
                                ></span>
                            </div>
                        </div>
                    </article>

                    <!-- OTHER ACTIVITY -->

                    <article class="sh-dash-panel">
                        <div class="sh-dash-panel-head">
                            <div>
                                <h3>Other activity</h3>

                                <p>
                                    Engagement and administration records
                                </p>
                            </div>
                        </div>

                        <div class="sh-dash-activity-list">
                            <div
                                v-for="item in platformActivity"
                                :key="item.key"
                                class="sh-dash-activity-row"
                            >
                                <span
                                    class="sh-dash-activity-icon"
                                    :class="`sh-dash-activity-icon--${item.key}`"
                                >
                                    <svg
                                        v-if="item.key === 'feedback'"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M4 5h16v12H9l-5 4V5zM8 9h8M8 13h5"
                                        />
                                    </svg>

                                    <svg
                                        v-else-if="item.key === 'promo'"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="4"
                                            y="8"
                                            width="16"
                                            height="12"
                                        />

                                        <path
                                            d="M12 8v12M12 8S5 8 5 5.5a2.5 2.5 0 0 1 5 0L12 8zm0 0s7 0 7-2.5a2.5 2.5 0 0 0-5 0L12 8z"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 3h9l3 3v15H6V3zM14 3v4h4M9 11h6M9 15h6"
                                        />
                                    </svg>
                                </span>

                                <div class="sh-dash-activity-copy">
                                    <strong>
                                        {{ item.title }}
                                    </strong>

                                    <span>
                                        {{ item.subtitle }}
                                    </span>
                                </div>

                                <strong class="sh-dash-activity-count">
                                    {{ formatNumber(item.count) }}
                                </strong>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </template>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| MAIN DASHBOARD LAYOUT
|--------------------------------------------------------------------------
*/

.sh-dashboard {
    display: flex;
    flex-direction: column;
    gap: 27px;
    color: var(--sh-text);
}

/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.sh-dash-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
}

.sh-dash-heading {
    min-width: 0;
}

.sh-dash-eyebrow {
    margin: 0 0 8px;
    color: var(--sh-accent);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
}

.sh-dash-header h1 {
    margin: 0;
    color: var(--sh-text);
    font-size: clamp(27px, 2.5vw, 33px);
    font-weight: 750;
    line-height: 1.2;
    letter-spacing: -0.9px;
}

.sh-dash-subtitle {
    margin: 8px 0 0;
    color: var(--sh-text-3);
    font-size: 13px;
    line-height: 1.55;
}

.sh-dash-toolbar {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 0 0 auto;
}

.sh-dash-updated {
    color: var(--sh-text-3);
    font-size: 12px;
    white-space: nowrap;
}

.sh-dash-refresh,
.sh-dash-error button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 39px;
    padding: 0 15px;
    border: 1px solid var(--sh-border-strong);
    border-radius: 10px;
    background: var(--sh-surface);
    color: var(--sh-text-2);
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition:
        border-color 0.15s ease,
        background 0.15s ease;
}

.sh-dash-refresh:hover:not(:disabled),
.sh-dash-error button:hover:not(:disabled) {
    border-color: var(--sh-accent);
    background: var(--sh-accent-soft);
}

.sh-dash-refresh:focus-visible,
.sh-dash-error button:focus-visible {
    outline: 2px solid var(--sh-accent);
    outline-offset: 2px;
}

.sh-dash-refresh:disabled,
.sh-dash-error button:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

.sh-dash-refresh svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/*
|--------------------------------------------------------------------------
| SECTION HEADINGS
|--------------------------------------------------------------------------
*/

.sh-dash-section {
    display: flex;
    flex-direction: column;
    gap: 14px;
    min-width: 0;
}

.sh-dash-section-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
}

.sh-dash-section-heading h2 {
    margin: 0;
    color: var(--sh-text);
    font-size: 16px;
    line-height: 1.4;
    font-weight: 750;
    letter-spacing: -0.3px;
}

.sh-dash-section-heading > span {
    color: var(--sh-text-3);
    font-size: 11px;
}

/*
|--------------------------------------------------------------------------
| STATISTIC CARDS
|--------------------------------------------------------------------------
*/

.sh-dash-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.sh-dash-metric,
.sh-dash-panel {
    min-width: 0;
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-lg, 16px);
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.sh-dash-metric {
    padding: 20px 21px 19px;
    min-height: 155px;
}

.sh-dash-metric-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.sh-dash-metric-label {
    padding-top: 4px;
    color: var(--sh-text-2);
    font-size: 13px;
    font-weight: 650;
}

.sh-dash-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 11px;
}

.sh-dash-icon svg,
.sh-dash-activity-icon svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sh-dash-icon--customers {
    background: var(--sh-info-bg);
    color: var(--sh-info);
}

.sh-dash-icon--owners {
    background: var(--sh-purple-bg);
    color: var(--sh-purple);
}

.sh-dash-icon--shops {
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.sh-dash-icon--appointments {
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sh-dash-metric-value {
    display: block;
    margin-top: 1px;
    color: var(--sh-text);
    font-size: clamp(25px, 2.6vw, 32px);
    font-weight: 780;
    line-height: 1.2;
    letter-spacing: -0.8px;
}

.sh-dash-metric-detail {
    margin: 7px 0 0;
    color: var(--sh-text-3);
    font-size: 12px;
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| DETAIL PANELS
|--------------------------------------------------------------------------
*/

.sh-dash-panels {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    align-items: stretch;
}

.sh-dash-panel {
    display: flex;
    flex-direction: column;
    padding: 23px;
}

.sh-dash-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    padding-bottom: 19px;
    border-bottom: 1px solid var(--sh-border);
}

.sh-dash-panel-head h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 17px;
    font-weight: 750;
    line-height: 1.35;
    letter-spacing: -0.3px;
}

.sh-dash-panel-head p {
    margin: 5px 0 0;
    color: var(--sh-text-3);
    font-size: 12px;
    line-height: 1.5;
}

.sh-dash-badge {
    display: inline-flex;
    align-items: center;
    min-height: 27px;
    flex: 0 0 auto;
    padding: 0 10px;
    border: 1px solid var(--sh-border);
    border-radius: 8px;
    background: var(--sh-surface-muted);
    color: var(--sh-text-2);
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

/*
|--------------------------------------------------------------------------
| USER ACCOUNTS
|--------------------------------------------------------------------------
*/

.sh-dash-main-total {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 10px;
    margin: 21px 0 13px;
}

.sh-dash-field-label {
    color: var(--sh-text-3);
    font-size: 12px;
}

.sh-dash-main-number {
    margin-top: 4px;
    color: var(--sh-text);
    font-size: 30px;
    font-weight: 780;
    letter-spacing: -0.8px;
    line-height: 1.1;
}

.sh-dash-percent {
    padding-bottom: 3px;
    color: var(--sh-success);
    font-size: 12px;
    font-weight: 750;
    white-space: nowrap;
}

.sh-dash-track {
    height: 8px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--sh-border);
}

.sh-dash-track--small {
    height: 6px;
    margin-top: 12px;
}

.sh-dash-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
}

.sh-dash-fill--active,
.sh-dash-fill--approved {
    background: var(--sh-success);
}

.sh-dash-fill--pending {
    background: var(--sh-warning);
}

.sh-dash-fill--rejected {
    background: var(--sh-danger);
}

.sh-dash-account-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px;
    margin-top: 22px;
}

.sh-dash-account-row {
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--sh-border);
    border-radius: 12px;
    background: var(--sh-surface-muted);
}

.sh-dash-account-title,
.sh-dash-account-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.sh-dash-account-title strong {
    color: var(--sh-text);
    font-size: 12px;
    font-weight: 750;
}

.sh-dash-account-title strong:last-child {
    font-size: 16px;
}

.sh-dash-account-meta {
    justify-content: flex-start;
    flex-wrap: wrap;
    margin-top: 14px;
    gap: 8px 13px;
}

.sh-dash-account-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--sh-text-3);
    font-size: 11px;
    white-space: nowrap;
}

.sh-dash-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
    border-radius: 50%;
    background: var(--sh-text-3);
}

.sh-dash-dot--active,
.sh-dash-dot--approved {
    background: var(--sh-success);
}

.sh-dash-dot--suspended,
.sh-dash-dot--rejected {
    background: var(--sh-danger);
}

.sh-dash-dot--pending {
    background: var(--sh-warning);
}

.sh-dash-panel-foot {
    margin: auto 0 0;
    padding-top: 18px;
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| SHOP APPROVALS
|--------------------------------------------------------------------------
*/

.sh-dash-shop-list {
    display: flex;
    flex-direction: column;
    gap: 22px;
    margin-top: 23px;
}

.sh-dash-shop-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sh-dash-shop-info > div:nth-child(2) {
    display: flex;
    flex-direction: column;
    min-width: 0;
    gap: 3px;
}

.sh-dash-shop-info strong {
    color: var(--sh-text-2);
    font-size: 12px;
    font-weight: 700;
}

.sh-dash-shop-info small {
    color: var(--sh-text-3);
    font-size: 11px;
}

.sh-dash-shop-values {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-left: auto;
}

.sh-dash-shop-values strong {
    color: var(--sh-text);
    font-size: 15px;
}

.sh-dash-shop-values span {
    min-width: 33px;
    color: var(--sh-text-3);
    font-size: 11px;
    text-align: right;
}

.sh-dash-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: auto;
    padding: 20px 0 0;
}

.sh-dash-notice-symbol {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 23px;
    height: 23px;
    flex: 0 0 23px;
    border-radius: 50%;
    background: var(--sh-warning-bg);
    color: var(--sh-warning);
    font-size: 13px;
    font-weight: 800;
}

.sh-dash-notice p {
    margin: 0;
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 1.5;
}

.sh-dash-notice strong {
    color: var(--sh-warning);
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| APPOINTMENT ACTIVITY
|--------------------------------------------------------------------------
*/

.sh-dash-booking-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 11px;
    margin-top: 22px;
}

.sh-dash-booking-item {
    display: grid;
    grid-template-columns: 8px minmax(0, 1fr);
    align-content: center;
    align-items: center;
    column-gap: 8px;
    min-height: 91px;
    padding: 14px;
    border: 1px solid var(--sh-border);
    border-radius: 12px;
    background: var(--sh-surface-muted);
}

.sh-dash-booking-item > span:nth-child(2) {
    overflow: hidden;
    color: var(--sh-text-3);
    font-size: 11px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sh-dash-booking-item strong {
    grid-column: 1 / -1;
    margin-top: 9px;
    color: var(--sh-text);
    font-size: 21px;
    font-weight: 780;
    line-height: 1.1;
}

.sh-dash-booking-dot {
    display: block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--sh-text-3);
}

.sh-dash-booking-dot--pending,
.sh-dash-booking-dot--no_show {
    background: var(--sh-warning);
}

.sh-dash-booking-dot--confirmed {
    background: var(--sh-info);
}

.sh-dash-booking-dot--completed {
    background: var(--sh-success);
}

.sh-dash-booking-dot--cancelled,
.sh-dash-booking-dot--declined {
    background: var(--sh-danger);
}

.sh-dash-completion {
    margin-top: auto;
    padding-top: 21px;
}

.sh-dash-completion-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 11px;
}

.sh-dash-completion-head span {
    color: var(--sh-text-2);
    font-size: 12px;
}

.sh-dash-completion-head strong {
    color: var(--sh-success);
    font-size: 12px;
}

/*
|--------------------------------------------------------------------------
| OTHER ACTIVITY
|--------------------------------------------------------------------------
*/

.sh-dash-activity-list {
    display: flex;
    flex-direction: column;
    margin-top: 6px;
}

.sh-dash-activity-row {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 93px;
    padding: 16px 0;
    border-bottom: 1px solid var(--sh-border);
}

.sh-dash-activity-row:last-child {
    border-bottom: none;
}

.sh-dash-activity-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    border-radius: 11px;
}

.sh-dash-activity-icon--feedback {
    background: var(--sh-info-bg);
    color: var(--sh-info);
}

.sh-dash-activity-icon--promo {
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.sh-dash-activity-icon--audit {
    background: var(--sh-purple-bg);
    color: var(--sh-purple);
}

.sh-dash-activity-copy {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.sh-dash-activity-copy strong {
    color: var(--sh-text-2);
    font-size: 13px;
    font-weight: 700;
}

.sh-dash-activity-copy span {
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 1.45;
}

.sh-dash-activity-count {
    margin-left: auto;
    color: var(--sh-text);
    font-size: 21px;
    font-weight: 780;
}

/*
|--------------------------------------------------------------------------
| ERROR STATE
|--------------------------------------------------------------------------
*/

.sh-dash-error {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    padding: 15px 18px;
    border: 1px solid var(--sh-danger);
    border-radius: 12px;
    background: var(--sh-danger-bg);
}

.sh-dash-error strong {
    color: var(--sh-danger);
    font-size: 13px;
}

.sh-dash-error p {
    margin: 4px 0 0;
    color: var(--sh-text-2);
    font-size: 12px;
    line-height: 1.5;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE DESIGN
|--------------------------------------------------------------------------
*/

@media (max-width: 1250px) {
    .sh-dash-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-dash-account-list {
        grid-template-columns: 1fr;
    }

    .sh-dash-booking-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 900px) {
    .sh-dash-panels {
        grid-template-columns: 1fr;
    }

    .sh-dash-account-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-dash-booking-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 620px) {
    .sh-dashboard {
        gap: 24px;
    }

    .sh-dash-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .sh-dash-toolbar {
        width: 100%;
        justify-content: space-between;
    }

    .sh-dash-metrics {
        gap: 11px;
    }

    .sh-dash-metric {
        min-height: 142px;
        padding: 15px;
    }

    .sh-dash-icon {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
    }

    .sh-dash-icon svg {
        width: 17px;
        height: 17px;
    }

    .sh-dash-metric-label {
        font-size: 12px;
    }

    .sh-dash-metric-value {
        font-size: 25px;
    }

    .sh-dash-metric-detail {
        font-size: 11px;
    }

    .sh-dash-panel {
        padding: 18px;
    }

    .sh-dash-account-list {
        grid-template-columns: 1fr;
    }

    .sh-dash-booking-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sh-dash-section-heading > span {
        display: none;
    }

    .sh-dash-error {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 375px) {
    .sh-dash-metrics {
        grid-template-columns: 1fr;
    }

    .sh-dash-metric {
        min-height: 128px;
    }

    .sh-dash-badge {
        font-size: 10px;
    }
}
</style>