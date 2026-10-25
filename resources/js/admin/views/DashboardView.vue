
<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const dashboard = ref(null);
const isLoading = ref(true);
const errorMessage = ref('');

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

    if (maximum <= 0 || current <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((current / maximum) * 100),
    );
}

/* ==========================================================
   DASHBOARD SUMMARIES
   ========================================================== */

const customerSummary = computed(() =>
    dashboard.value?.users?.customers ?? {
        total: 0,
        active: 0,
        suspended: 0,
    },
);

const shopOwnerSummary = computed(() =>
    dashboard.value?.users?.shop_owners ?? {
        total: 0,
        active: 0,
        suspended: 0,
    },
);

const shopSummary = computed(() =>
    dashboard.value?.shops ?? {
        total: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
    },
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
    },
);

const otherSummary = computed(() =>
    dashboard.value?.other ?? {
        feedback: 0,
        active_promos: 0,
        audit_logs: 0,
    },
);

const totalAccounts = computed(
    () =>
        Number(customerSummary.value.total ?? 0) +
        Number(shopOwnerSummary.value.total ?? 0),
);

const activeAccounts = computed(
    () =>
        Number(customerSummary.value.active ?? 0) +
        Number(shopOwnerSummary.value.active ?? 0),
);

const suspendedAccounts = computed(
    () =>
        Number(customerSummary.value.suspended ?? 0) +
        Number(shopOwnerSummary.value.suspended ?? 0),
);

const accountActivePercentage = computed(() =>
    percentage(
        activeAccounts.value,
        totalAccounts.value,
    ),
);

/* ==========================================================
   OVERVIEW STATISTICS
   ========================================================== */

const overviewCards = computed(() => {
    if (!dashboard.value) {
        return [];
    }

    return [
        {
            title: 'Customers',
            value: customerSummary.value.total,
            description:
                `${formatNumber(customerSummary.value.active)} active accounts`,
            type: 'customers',
        },
        {
            title: 'Shop Owners',
            value: shopOwnerSummary.value.total,
            description:
                `${formatNumber(shopOwnerSummary.value.active)} active accounts`,
            type: 'owners',
        },
        {
            title: 'Registered Shops',
            value: shopSummary.value.total,
            description:
                `${formatNumber(shopSummary.value.pending)} awaiting review`,
            type: 'shops',
        },
        {
            title: 'Appointments',
            value: appointmentSummary.value.total,
            description:
                `${formatNumber(appointmentSummary.value.pending)} currently pending`,
            type: 'appointments',
        },
    ];
});

const accountGroups = computed(() => [
    {
        key: 'customers',
        title: 'Customers',
        subtitle: 'Customer accounts',
        info: customerSummary.value,
    },
    {
        key: 'owners',
        title: 'Shop Owners',
        subtitle: 'Business accounts',
        info: shopOwnerSummary.value,
    },
]);

const shopStatuses = computed(() => [
    {
        key: 'approved',
        title: 'Approved',
        subtitle: 'Visible to customers',
        count: shopSummary.value.approved,
    },
    {
        key: 'pending',
        title: 'Pending Review',
        subtitle: 'Needs administrator action',
        count: shopSummary.value.pending,
    },
    {
        key: 'rejected',
        title: 'Rejected',
        subtitle: 'Registration not approved',
        count: shopSummary.value.rejected,
    },
]);

/* ==========================================================
   ALL SIX APPOINTMENT STATUSES
   ========================================================== */

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
        title: 'Customer Feedback',
        subtitle: 'Reviews submitted by customers',
        count: otherSummary.value.feedback,
    },
    {
        key: 'promo',
        title: 'Active Promos',
        subtitle: 'Promotions currently active',
        count: otherSummary.value.active_promos,
    },
    {
        key: 'audit',
        title: 'Audit Logs',
        subtitle: 'Recorded administrator activity',
        count: otherSummary.value.audit_logs,
    },
]);

/* ==========================================================
   LOAD DASHBOARD FROM LARAVEL
   ========================================================== */

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
            },
        );

        if (
            !response?.data ||
            typeof response.data !== 'object'
        ) {
            throw new Error(
                'The dashboard response was invalid.',
            );
        }

        dashboard.value = response.data;
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

onMounted(() => {
    void loadDashboard();
});
</script>

<template>
    <div class="dashboard">

        <!-- LIGHT WELCOME CARD -->

        <section class="welcome-section dashboard-hero">
            <div class="dashboard-hero-content">
                <h2>Welcome to StyleHub Admin</h2>

                <p class="welcome-description">
                    Monitor users, shops, appointments, reviews,
                    promotions, and administrative activity
                    from one central workspace.
                </p>

                <div
                    v-if="dashboard"
                    class="hero-summary"
                >
                    <div class="hero-summary-item">
                        <span>Accounts</span>
                        <strong>
                            {{ formatNumber(totalAccounts) }}
                        </strong>
                    </div>

                    <div class="hero-summary-divider"></div>

                    <div class="hero-summary-item">
                        <span>Shops</span>
                        <strong>
                            {{ formatNumber(shopSummary.total) }}
                        </strong>
                    </div>

                    <div class="hero-summary-divider"></div>

                    <div class="hero-summary-item">
                        <span>Appointments</span>
                        <strong>
                            {{ formatNumber(appointmentSummary.total) }}
                        </strong>
                    </div>
                </div>
            </div>

            <div class="dashboard-hero-actions">
                <button
                    type="button"
                    class="refresh-button hero-refresh"
                    :disabled="isLoading"
                    @click="loadDashboard"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        :class="{ spinning: isLoading }"
                    >
                        <path d="M20 11a8 8 0 10-2.3 5.7" />
                        <path d="M20 5v6h-6" />
                    </svg>

                    <span>
                        {{
                            isLoading
                                ? 'Refreshing...'
                                : 'Refresh data'
                        }}
                    </span>
                </button>
            </div>
        </section>

        <!-- ERROR MESSAGE -->

        <div
            v-if="errorMessage"
            class="error-panel dashboard-error"
            role="alert"
        >
            <div class="error-panel-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v5M12 17h.01" />
                </svg>
            </div>

            <div class="error-panel-copy">
                <strong>Dashboard unavailable</strong>
                <p>{{ errorMessage }}</p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="loadDashboard"
            >
                Try again
            </button>
        </div>

        <!-- LOADING SKELETON -->

        <template v-if="isLoading && !dashboard">
            <section class="overview-grid">
                <div
                    v-for="index in 4"
                    :key="index"
                    class="overview-card dashboard-skeleton-card"
                >
                    <div class="skeleton skeleton-icon"></div>

                    <div class="dashboard-skeleton-copy">
                        <div class="skeleton skeleton-label"></div>
                        <div class="skeleton skeleton-number"></div>
                        <div class="skeleton skeleton-description"></div>
                    </div>
                </div>
            </section>

            <section class="detail-grid dashboard-detail-grid">
                <div class="panel skeleton-panel"></div>
                <div class="panel skeleton-panel"></div>
            </section>

            <section class="bottom-grid">
                <div class="panel skeleton-panel"></div>
                <div class="panel skeleton-panel"></div>
            </section>
        </template>

        <!-- DASHBOARD CONTENT -->

        <template v-else-if="dashboard">

            <!-- STATISTIC CARDS -->

            <section class="overview-grid">
                <article
                    v-for="card in overviewCards"
                    :key="card.type"
                    class="overview-card dashboard-stat-card"
                >
                    <div
                        class="overview-icon"
                        :class="card.type"
                    >
                        <svg
                            v-if="card.type === 'customers'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="8" r="3" />
                            <path d="M3 20c0-4 2-7 6-7s6 3 6 7" />
                            <circle cx="17" cy="9" r="2.5" />
                            <path d="M15 14c4 0 6 2 6 6" />
                        </svg>

                        <svg
                            v-else-if="card.type === 'owners'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="8" r="3.5" />
                            <path d="M5 21c0-5 2.5-8 7-8s7 3 7 8" />
                            <path d="M18 4v4M16 6h4" />
                        </svg>

                        <svg
                            v-else-if="card.type === 'shops'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 10v10h16V10" />
                            <path d="M3 10l2-6h14l2 6" />
                            <path d="M8 20v-6h8v6" />
                            <path
                                d="M3 10c0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0 0 2 3 2 3 0"
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
                            <path d="M8 3v4M16 3v4M3 10h18" />
                            <path d="M8 14h3M14 14h2M8 17h2" />
                        </svg>
                    </div>

                    <div class="overview-copy">
                        <span class="stat-label">
                            {{ card.title }}
                        </span>

                        <strong>
                            {{ formatNumber(card.value) }}
                        </strong>

                        <div class="stat-description">
                            <span class="stat-description-dot"></span>
                            {{ card.description }}
                        </div>
                    </div>

                    <div class="stat-arrow">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </div>
                </article>
            </section>

            <!-- USER ACCOUNTS AND SHOP APPROVALS -->

            <section class="detail-grid dashboard-detail-grid">

                <!-- USER ACCOUNTS -->

                <article class="panel dashboard-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="panel-eyebrow">ACCOUNTS</p>

                            <h2>User accounts</h2>

                            <p>
                                Customer and shop owner account
                                activity across StyleHub.
                            </p>
                        </div>

                        <span class="panel-badge">
                            {{ formatNumber(totalAccounts) }} total
                        </span>
                    </div>

                    <div class="account-overview">
                        <div class="account-overview-main">
                            <div class="account-overview-number">
                                {{ formatNumber(activeAccounts) }}
                            </div>

                            <div class="account-overview-copy">
                                <strong>Active accounts</strong>

                                <span>
                                    {{ accountActivePercentage }}%
                                    of all registered accounts
                                </span>
                            </div>
                        </div>

                        <span class="account-health">
                            Healthy
                        </span>
                    </div>

                    <div class="overall-progress">
                        <div
                            class="overall-progress-fill"
                            :style="{
                                width: `${accountActivePercentage}%`
                            }"
                        ></div>
                    </div>

                    <!-- CUSTOMER AND SHOP OWNER GROUPS -->

                    <div class="account-groups dashboard-account-groups">
                        <div
                            v-for="group in accountGroups"
                            :key="group.key"
                            class="account-group dashboard-account-card"
                        >
                            <div class="account-title">
                                <div class="account-title-left">
                                    <span
                                        class="account-icon"
                                        :class="group.key"
                                    >
                                        <svg
                                            v-if="group.key === 'customers'"
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <circle cx="9" cy="8" r="3" />
                                            <path
                                                d="M3 20c0-4 2-7 6-7s6 3 6 7"
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
                                        </svg>
                                    </span>

                                    <div>
                                        <strong>{{ group.title }}</strong>
                                        <span>{{ group.subtitle }}</span>
                                    </div>
                                </div>

                                <b>
                                    {{ formatNumber(group.info.total) }}
                                </b>
                            </div>

                            <div class="progress-track">
                                <div
                                    class="progress-fill active"
                                    :style="{
                                        width: `${percentage(
                                            group.info.active,
                                            group.info.total
                                        )}%`
                                    }"
                                ></div>
                            </div>

                            <div class="breakdown">
                                <span>
                                    <i class="dot active"></i>
                                    <span>Active</span>
                                    <strong>
                                        {{ formatNumber(group.info.active) }}
                                    </strong>
                                </span>

                                <span>
                                    <i class="dot suspended"></i>
                                    <span>Suspended</span>
                                    <strong>
                                        {{ formatNumber(group.info.suspended) }}
                                    </strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="panel-footer-note">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 11v5M12 8h.01" />
                        </svg>

                        <span>
                            {{ formatNumber(suspendedAccounts) }}
                            account{{ suspendedAccounts === 1 ? '' : 's' }}
                            currently suspended.
                        </span>
                    </div>
                </article>

                <!-- SHOP APPROVALS -->

                <article class="panel dashboard-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="panel-eyebrow">
                                SHOP MANAGEMENT
                            </p>

                            <h2>Shop approvals</h2>

                            <p>
                                Current registration and approval
                                status of StyleHub shops.
                            </p>
                        </div>

                        <span class="panel-badge">
                            {{ formatNumber(shopSummary.total) }}
                            shops
                        </span>
                    </div>

                    <div class="shop-status-summary">
                        <div
                            v-for="status in shopStatuses"
                            :key="status.key"
                            class="shop-status-block"
                        >
                            <div class="status-row">
                                <div class="status-name">
                                    <span
                                        class="status-marker"
                                        :class="status.key"
                                    ></span>

                                    <div>
                                        <strong>
                                            {{ status.title }}
                                        </strong>

                                        <small>
                                            {{ status.subtitle }}
                                        </small>
                                    </div>
                                </div>

                                <div class="status-value">
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

                            <div class="status-progress">
                                <div
                                    class="status-progress-fill"
                                    :class="status.key"
                                    :style="{
                                        width: `${percentage(
                                            status.count,
                                            shopSummary.total
                                        )}%`
                                    }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="shopSummary.pending > 0"
                        class="review-notice"
                    >
                        <div class="review-notice-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                />
                                <path d="M12 8v5M12 16h.01" />
                            </svg>
                        </div>

                        <div>
                            <strong>Pending shop reviews</strong>

                            <span>
                                {{ formatNumber(shopSummary.pending) }}
                                shop{{ shopSummary.pending === 1 ? '' : 's' }}
                                currently require administrator review.
                            </span>
                        </div>
                    </div>
                </article>
            </section>

            <!-- APPOINTMENT AND PLATFORM ACTIVITY -->

            <section class="bottom-grid">

                <!-- APPOINTMENT ACTIVITY -->

                <article class="panel dashboard-panel appointments-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="panel-eyebrow">BOOKINGS</p>

                            <h2>Appointment activity</h2>

                            <p>
                                Current booking status across
                                all registered shops.
                            </p>
                        </div>

                        <span class="panel-badge">
                            {{ formatNumber(appointmentSummary.total) }}
                            total
                        </span>
                    </div>

                    <div class="appointment-grid dashboard-appointment-grid">
                        <div
                            v-for="item in bookingStatuses"
                            :key="item.key"
                            class="appointment-status-card"
                            :class="`${item.key}-card`"
                        >
                            <div class="appointment-status-icon">
                                <svg
                                    v-if="item.key === 'pending'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M12 7v5l3 2" />
                                </svg>

                                <svg
                                    v-else-if="item.key === 'confirmed'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M8 12l3 3 5-6" />
                                </svg>

                                <svg
                                    v-else-if="item.key === 'completed'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M5 12l4 4L19 6" />
                                </svg>

                                <!-- NO-SHOW ICON -->

                                <svg
                                    v-else-if="item.key === 'no_show'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle cx="10" cy="8" r="3" />
                                    <path
                                        d="M3 20c0-4 2.5-7 7-7 1.8 0 3.2.4 4.3 1.2"
                                    />
                                    <path d="M17 16l5 5M22 16l-5 5" />
                                </svg>

                                <svg
                                    v-else-if="item.key === 'cancelled'"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <circle cx="12" cy="12" r="9" />
                                    <path d="M9 9l6 6M15 9l-6 6" />
                                </svg>

                                <svg
                                    v-else
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M7 7l10 10M17 7L7 17" />
                                </svg>
                            </div>

                            <div>
                                <span>{{ item.title }}</span>
                                <strong>
                                    {{ formatNumber(item.count) }}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="booking-progress">
                        <div class="booking-progress-heading">
                            <span>Completed appointments</span>

                            <strong>
                                {{
                                    percentage(
                                        appointmentSummary.completed,
                                        appointmentSummary.total
                                    )
                                }}%
                            </strong>
                        </div>

                        <div class="progress-track">
                            <div
                                class="booking-progress-fill"
                                :style="{
                                    width: `${percentage(
                                        appointmentSummary.completed,
                                        appointmentSummary.total
                                    )}%`
                                }"
                            ></div>
                        </div>
                    </div>
                </article>

                <!-- OTHER ACTIVITY -->

                <article class="panel dashboard-panel platform-panel">
                    <div class="panel-heading">
                        <div>
                            <p class="panel-eyebrow">
                                PLATFORM
                            </p>

                            <h2>Other activity</h2>

                            <p>
                                Additional activity and records
                                generated across StyleHub.
                            </p>
                        </div>
                    </div>

                    <div class="platform-list dashboard-platform-list">
                        <div
                            v-for="item in platformActivity"
                            :key="item.key"
                            class="platform-row"
                        >
                            <div class="platform-row-left">
                                <div
                                    class="platform-icon"
                                    :class="item.key"
                                >
                                    <svg
                                        v-if="item.key === 'feedback'"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M4 5h16v12H9l-5 4V5z" />
                                        <path d="M8 9h8M8 13h5" />
                                    </svg>

                                    <svg
                                        v-else-if="item.key === 'promo'"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M4 8h16v12H4z" />
                                        <path
                                            d="M8 8a2.5 2.5 0 110-5c3 0 4 5 4 5"
                                        />
                                        <path
                                            d="M16 8a2.5 2.5 0 100-5c-3 0-4 5-4 5"
                                        />
                                        <path d="M12 8v12" />
                                    </svg>

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M6 3h9l3 3v15H6V3z" />
                                        <path d="M14 3v4h4" />
                                        <path d="M9 11h6M9 15h6" />
                                    </svg>
                                </div>

                                <div class="platform-row-copy">
                                    <strong>
                                        {{ item.title }}
                                    </strong>

                                    <span>
                                        {{ item.subtitle }}
                                    </span>
                                </div>
                            </div>

                            <div class="platform-value">
                                {{ formatNumber(item.count) }}
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </template>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
.dashboard{display:flex;flex-direction:column}
.dashboard.dashboard .dashboard-hero{min-height:220px;display:flex;align-items:stretch;justify-content:space-between;gap:30px;padding:30px 32px;margin-bottom:16px;border:1px solid var(--sh-border);border-radius:var(--sh-radius-xl);background:radial-gradient(circle at 94% 10%,rgba(214,154,45,0.07),transparent 26%),var(--sh-surface);box-shadow:var(--sh-shadow-sm)}
.dashboard-hero-content{min-width:0;flex:1;display:flex;flex-direction:column;justify-content:center}
.dashboard .dashboard-hero h2{max-width:640px;margin:0;color:var(--sh-text);font-size:clamp(26px,3vw,34px);font-weight:800;line-height:1.15;letter-spacing:-1px}
.dashboard .dashboard-hero .welcome-description{max-width:650px;margin-top:10px;color:var(--sh-text-3);font-size:11.5px;line-height:18px}
.hero-summary{width:fit-content;display:flex;align-items:center;gap:18px;margin-top:19px;padding:11px 14px;border:1px solid var(--sh-border);border-radius:12px;background:var(--sh-surface-muted)}
.hero-summary-item{display:flex;flex-direction:column;gap:2px}
.dashboard .dashboard-hero .hero-summary-item span{color:var(--sh-text-3);font-size:8.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px}
.dashboard .dashboard-hero .hero-summary-item strong{color:var(--sh-text);font-size:14px;font-weight:800}
.hero-summary-divider{width:1px;height:28px;background:var(--sh-border)}
.dashboard-hero-actions{width:230px;flex:0 0 230px;display:flex;flex-direction:column;justify-content:space-between;padding:5px 0}
.welcome-section .hero-refresh{width:100%;border-color:var(--sh-border);background:var(--sh-surface);color:var(--sh-text-2);box-shadow:none}
.welcome-section .hero-refresh:hover:not(:disabled){border-color:rgba(214,154,45,0.40);background:var(--sh-accent-soft);color:var(--sh-text)}
.dashboard-error{align-items:center}
.error-panel-icon{width:38px;height:38px;flex:0 0 38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:rgba(169,79,79,0.09)}
.error-panel-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.error-panel-copy{min-width:0;flex:1}
.error-panel-copy strong{display:block;font-size:10.5px}
.error-panel-copy p{margin:3px 0 0}
.dashboard-stat-card{min-height:132px;display:flex;align-items:center;gap:14px;transition:transform 0.16s ease,box-shadow 0.16s ease,border-color 0.16s ease}
.dashboard-stat-card:hover{transform:translateY(-2px);border-color:#d9dee5;box-shadow:var(--sh-shadow-card)}
.dashboard-stat-card .overview-icon{width:45px;height:45px;flex:0 0 45px}
.dashboard-stat-card .overview-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.65;stroke-linecap:round;stroke-linejoin:round}
.overview-icon.customers{background:var(--sh-info-bg);color:var(--sh-info)}
.overview-icon.owners{background:var(--sh-purple-bg);color:var(--sh-purple)}
.overview-icon.shops{background:var(--sh-success-bg);color:var(--sh-success)}
.overview-icon.appointments{background:var(--sh-accent-soft);color:var(--sh-accent)}
.overview-copy{min-width:0;flex:1}
.stat-label{display:block;color:var(--sh-text-3);font-size:9.5px;font-weight:700}
.dashboard-stat-card .overview-copy strong{margin-top:4px;font-size:24px;line-height:29px}
.stat-description{display:flex;align-items:center;gap:5px;margin-top:5px;color:var(--sh-text-3);font-size:9px}
.stat-description-dot{width:5px;height:5px;flex:0 0 5px;border-radius:999px;background:var(--sh-success)}
.stat-arrow{width:27px;height:27px;flex:0 0 27px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:#f6f8fa;color:#a1a8b2}
.stat-arrow svg{width:13px;height:13px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.dashboard-detail-grid{margin-top:14px}
.dashboard-panel{overflow:hidden;padding:19px}
.dashboard-panel .panel-heading{padding-bottom:15px;border-bottom:1px solid var(--sh-border)}
.dashboard-panel .panel-heading h2{font-size:15px;line-height:21px}
.dashboard-panel .panel-heading p:not(.panel-eyebrow){max-width:400px}
.account-overview{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-top:17px}
.account-overview-main{min-width:0;display:flex;align-items:center;gap:11px}
.account-overview-number{color:var(--sh-text);font-size:27px;font-weight:800;letter-spacing:-0.7px}
.account-overview-copy{display:flex;flex-direction:column;gap:2px}
.account-overview-copy strong{color:var(--sh-text-2);font-size:10px}
.account-overview-copy span{color:var(--sh-text-3);font-size:9px}
.account-health{min-height:25px;display:inline-flex;align-items:center;padding:0 8px;border:1px solid #d4ebdf;border-radius:999px;background:var(--sh-success-bg);color:var(--sh-success);font-size:8px;font-weight:800;text-transform:uppercase}
.overall-progress{height:6px;margin-top:11px;overflow:hidden;border-radius:999px;background:#edf0f4}
.overall-progress-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--sh-success),#51b889)}
.dashboard-account-groups{margin-top:17px}
.dashboard-account-card{display:flex;flex-direction:column;gap:11px;padding:13px;border:1px solid var(--sh-border);border-radius:12px;background:#fbfcfd}
.account-title{min-width:0}
.account-title-left{min-width:0;display:flex;align-items:center;gap:9px}
.account-title-left > div{min-width:0;display:flex;flex-direction:column;gap:2px}
.account-title-left span{color:var(--sh-text-3);font-size:8.5px}
.account-title b{color:var(--sh-text);font-size:16px;font-weight:800}
.account-icon{width:34px;height:34px;flex:0 0 34px;display:flex;align-items:center;justify-content:center;border-radius:9px}
.account-icon.customers{background:var(--sh-info-bg);color:var(--sh-info)}
.account-icon.owners{background:var(--sh-purple-bg);color:var(--sh-purple)}
.account-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.progress-fill.active{background:var(--sh-success)!important}
.breakdown{display:flex;align-items:center;justify-content:space-between;gap:9px}
.breakdown > span{display:flex;align-items:center;gap:5px;color:var(--sh-text-3);font-size:8.5px}
.breakdown strong{color:var(--sh-text-2);font-size:9px}
.dot.active{background:var(--sh-success)!important}
.dot.suspended{background:var(--sh-danger)!important}
.panel-footer-note{display:flex;align-items:center;gap:7px;margin-top:14px;padding-top:13px;border-top:1px solid var(--sh-border);color:var(--sh-text-3);font-size:8.5px}
.panel-footer-note svg{width:14px;height:14px;flex:0 0 14px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.shop-status-summary{display:flex;flex-direction:column;gap:17px;margin-top:18px}
.shop-status-block{display:flex;flex-direction:column;gap:8px}
.shop-status-block .status-row{min-height:0;padding:0;border:0}
.status-name{display:flex;align-items:center;gap:9px}
.status-name > div{display:flex;flex-direction:column;gap:1px}
.status-name strong{color:var(--sh-text-2);font-size:10px}
.status-name small{color:var(--sh-text-3);font-size:8.5px}
.status-marker{width:9px;height:9px;flex:0 0 9px;border:0!important}
.status-marker.approved{background:var(--sh-success)!important}
.status-marker.pending{background:var(--sh-warning)!important}
.status-marker.rejected{background:var(--sh-danger)!important}
.status-value{display:flex;align-items:baseline;gap:6px}
.status-value strong{color:var(--sh-text);font-size:13px;font-weight:800}
.status-value span{color:var(--sh-text-3);font-size:8px}
.status-progress-fill.approved{background:var(--sh-success)!important}
.status-progress-fill.pending{background:var(--sh-warning)!important}
.status-progress-fill.rejected{background:var(--sh-danger)!important}
.review-notice{display:flex;align-items:flex-start;gap:9px;margin-top:17px;padding:11px 12px;border:1px solid #f0dfb9;border-radius:11px;background:var(--sh-warning-bg)}
.review-notice-icon{width:32px;height:32px;flex:0 0 32px;display:flex;align-items:center;justify-content:center;border-radius:9px;background:rgba(154,103,31,0.08);color:var(--sh-warning)}
.review-notice-icon svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.review-notice > div:last-child{min-width:0;display:flex;flex-direction:column;gap:2px}
.review-notice strong{color:#80591f;font-size:9.5px}
.review-notice span{color:#99733b;font-size:8.5px;line-height:14px}
.dashboard-appointment-grid{grid-template-columns:repeat(3,minmax(0,1fr));margin-top:17px}
.appointment-status-card{min-width:0;min-height:72px;display:flex;align-items:center;gap:9px;padding:11px;border:1px solid var(--sh-border);border-radius:11px;background:#fbfcfd}
.appointment-status-icon{width:34px;height:34px;flex:0 0 34px;display:flex;align-items:center;justify-content:center;border-radius:9px;background:#f1f3f6;color:var(--sh-text-3)}
.appointment-status-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.appointment-status-card > div:last-child{min-width:0;display:flex;flex-direction:column;gap:2px}
.appointment-status-card span{color:var(--sh-text-3);font-size:8.5px;font-weight:700}
.appointment-status-card strong{color:var(--sh-text);font-size:15px;font-weight:800}
.pending-card .appointment-status-icon{background:var(--sh-warning-bg);color:var(--sh-warning)}
.confirmed-card .appointment-status-icon{background:var(--sh-info-bg);color:var(--sh-info)}
.completed-card .appointment-status-icon{background:var(--sh-success-bg);color:var(--sh-success)}
.no_show-card .appointment-status-icon{background:var(--sh-warning-bg);color:var(--sh-warning)}
.cancelled-card .appointment-status-icon,.declined-card .appointment-status-icon{background:var(--sh-danger-bg);color:var(--sh-danger)}
.booking-progress{margin-top:18px;padding-top:14px;border-top:1px solid var(--sh-border)}
.booking-progress-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px;color:var(--sh-text-3);font-size:8.5px}
.booking-progress-heading strong{color:var(--sh-success);font-size:9px}
.booking-progress-fill{height:100%;border-radius:inherit;background:var(--sh-success)}
.dashboard-platform-list{gap:0;margin-top:9px}
.dashboard-platform-list .platform-row{justify-content:space-between;min-height:69px;padding:11px 0}
.platform-row-left{min-width:0;display:flex;align-items:center;gap:10px}
.platform-icon{width:38px;height:38px;flex:0 0 38px}
.platform-icon.feedback{background:var(--sh-info-bg);color:var(--sh-info)}
.platform-icon.promo{background:var(--sh-accent-soft);color:var(--sh-accent)}
.platform-icon.audit{background:var(--sh-purple-bg);color:var(--sh-purple)}
.platform-icon svg{fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.platform-row-copy{min-width:0;display:flex;flex-direction:column;gap:2px}
.platform-row-copy strong{color:var(--sh-text-2);font-size:10px}
.platform-row-copy span{color:var(--sh-text-3);font-size:8.5px}
.platform-value{min-width:52px;color:var(--sh-text);font-size:16px;font-weight:800;text-align:right}
.dashboard-skeleton-card{display:flex;align-items:center;gap:14px}
.skeleton-icon{width:45px;height:45px;flex:0 0 45px;border-radius:13px}
.dashboard-skeleton-copy{min-width:0;flex:1;display:flex;flex-direction:column;gap:8px}
.skeleton-label{width:70px;height:8px}
.skeleton-number{width:95px;height:22px}
.skeleton-description{width:125px;height:8px}
.skeleton-panel{min-height:340px}
.spinning{animation:dashboard-spin 0.8s linear infinite}
@keyframes dashboard-spin{to{transform:rotate(360deg)}}
@media (max-width:1200px){
    .dashboard.dashboard .dashboard-hero{min-height:210px}
    .dashboard-appointment-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:900px){
    .dashboard.dashboard .dashboard-hero{min-height:0;align-items:flex-start;flex-direction:column;padding:21px}
    .dashboard-hero-actions{width:100%;flex-basis:auto;flex-direction:row;gap:10px}
    .hero-summary{width:100%;justify-content:space-between}
    .dashboard-account-groups{grid-template-columns:1fr}
}
@media (max-width:680px){
    .dashboard.dashboard .dashboard-hero{gap:20px;padding:18px}
    .dashboard-hero h2{font-size:24px}
    .hero-summary{align-items:stretch;flex-direction:column;gap:8px;padding:12px}
    .hero-summary-item{flex-direction:row;align-items:center;justify-content:space-between}
    .hero-summary-divider{width:100%;height:1px}
    .dashboard-hero-actions{flex-direction:column}
    .dashboard-stat-card{min-height:110px}
    .dashboard-appointment-grid{grid-template-columns:1fr}
    .account-overview{align-items:flex-start;flex-direction:column}
    .breakdown{align-items:flex-start;flex-direction:column}
}
</style>
