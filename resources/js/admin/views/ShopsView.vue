<script setup>
import { computed, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const shops = ref([]);
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
    approval_status: '',
    administrative_status: '',
    type: '',
    availability_status: '',
    city: '',
    province: '',
    per_page: 20,
});

const approvalOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const administrativeStatusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
];

const numberFormatter = new Intl.NumberFormat('en-PH');

function formatNumber(value) {
    const number = Number(value ?? 0);

    return Number.isFinite(number)
        ? numberFormatter.format(number)
        : '0';
}

function humanize(value) {
    if (!value) return '—';

    return String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, character => character.toUpperCase());
}

function buildDynamicOptions(field, selectedValue) {
    const values = new Set();

    if (selectedValue) {
        values.add(selectedValue);
    }

    for (const shop of shops.value) {
        const value = shop?.[field];

        if (
            typeof value === 'string' &&
            value.trim() !== ''
        ) {
            values.add(value);
        }
    }

    return Array.from(values)
        .sort()
        .map(value => ({
            value,
            label: humanize(value),
        }));
}

function statusClass(status) {
    return String(status ?? '')
        .toLowerCase()
        .replaceAll('_', '-');
}

function formatDate(date) {
    if (!date) return '—';

    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'Asia/Manila',
    }).format(parsed);
}

function shopInitial(shop) {
    const name = String(shop?.name ?? '').trim();

    return name
        ? name.charAt(0).toUpperCase()
        : 'S';
}

/*
|--------------------------------------------------------------------------
| COMPUTED FILTER OPTIONS
|--------------------------------------------------------------------------
*/

const typeOptions = computed(() =>
    buildDynamicOptions('type', filters.type)
);

const availabilityOptions = computed(() =>
    buildDynamicOptions(
        'availability_status',
        filters.availability_status
    )
);

const activeFiltersCount = computed(() => {
    let count = 0;

    if (filters.search.trim()) count++;
    if (filters.approval_status) count++;
    if (filters.administrative_status) count++;
    if (filters.type) count++;
    if (filters.availability_status) count++;
    if (filters.city.trim()) count++;
    if (filters.province.trim()) count++;

    return count;
});

/*
|--------------------------------------------------------------------------
| SHOP STATISTICS
|--------------------------------------------------------------------------
*/

const currentPageSummary = computed(() => {
    const summary = {
        approved: 0,
        pending: 0,
        rejected: 0,
        suspended: 0,
    };

    for (const shop of shops.value) {
        if (shop.approval_status === 'approved') {
            summary.approved++;
        }

        if (shop.approval_status === 'pending') {
            summary.pending++;
        }

        if (shop.approval_status === 'rejected') {
            summary.rejected++;
        }

        if (shop.administrative_status === 'suspended') {
            summary.suspended++;
        }
    }

    return summary;
});

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const firstResult = computed(() =>
    pagination.value.total === 0
        ? 0
        : (
            (pagination.value.current_page - 1) *
            pagination.value.per_page
        ) + 1
);

const lastResult = computed(() =>
    Math.min(
        pagination.value.total,
        pagination.value.current_page *
        pagination.value.per_page
    )
);

const visiblePages = computed(() => {
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;

    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);

    const pages = [];

    for (let page = start; page <= end; page++) {
        pages.push(page);
    }

    return pages;
});

/*
|--------------------------------------------------------------------------
| BUILD API QUERY
|--------------------------------------------------------------------------
*/

function buildQuery(page) {
    const query = new URLSearchParams();

    const search = filters.search.trim();
    const city = filters.city.trim();
    const province = filters.province.trim();

    if (search) {
        query.set('search', search);
    }

    if (filters.approval_status) {
        query.set(
            'approval_status',
            filters.approval_status
        );
    }

    if (filters.administrative_status) {
        query.set(
            'administrative_status',
            filters.administrative_status
        );
    }

    if (filters.type) {
        query.set('type', filters.type);
    }

    if (filters.availability_status) {
        query.set(
            'availability_status',
            filters.availability_status
        );
    }

    if (city) {
        query.set('city', city);
    }

    if (province) {
        query.set('province', province);
    }

    query.set('page', String(page));
    query.set('per_page', String(filters.per_page));

    return query.toString();
}

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

async function handleAuthenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

/*
|--------------------------------------------------------------------------
| FETCH SHOP DATA
|--------------------------------------------------------------------------
*/

async function loadShops(page = 1) {
    if (!auth.token) {
        await handleAuthenticationFailure();
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(
            `/admin/shops?${buildQuery(page)}`,
            {
                token: auth.token,
            }
        );

        shops.value = Array.isArray(response?.data?.shops)
            ? response.data.shops
            : [];

        pagination.value = {
            current_page: Number(
                response?.data?.pagination?.current_page ?? 1
            ),

            per_page: Number(
                response?.data?.pagination?.per_page ??
                filters.per_page
            ),

            total: Number(
                response?.data?.pagination?.total ?? 0
            ),

            last_page: Number(
                response?.data?.pagination?.last_page ?? 1
            ),

            has_more_pages: Boolean(
                response?.data?.pagination?.has_more_pages
            ),
        };

    } catch (error) {
        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            await handleAuthenticationFailure();
            return;
        }

        errorMessage.value =
            error?.data?.message ??
            error?.message ??
            'Unable to load shops.';

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
    await loadShops(1);
}

async function resetFilters() {
    filters.search = '';
    filters.approval_status = '';
    filters.administrative_status = '';
    filters.type = '';
    filters.availability_status = '';
    filters.city = '';
    filters.province = '';
    filters.per_page = 20;

    await loadShops(1);
}

/*
|--------------------------------------------------------------------------
| PAGINATION ACTION
|--------------------------------------------------------------------------
*/

async function changePage(page) {
    if (
        page < 1 ||
        page > pagination.value.last_page ||
        page === pagination.value.current_page ||
        isLoading.value
    ) {
        return;
    }

    await loadShops(page);
}

/*
|--------------------------------------------------------------------------
| OPEN SHOP DETAILS
|--------------------------------------------------------------------------
*/

async function openShop(shop) {
    await router.push({
        name: 'admin.shop.detail',
        params: {
            shopId: shop.id,
        },
    });
}

/*
|--------------------------------------------------------------------------
| INITIAL DATA LOADING
|--------------------------------------------------------------------------
|
| Wait for the initial shop data before showing this page.
| AdminLayout.vue uses Suspense to keep the previous page
| visible while this initial request completes.
|
| No loading spinner or temporary statistics are displayed.
|
*/

await loadShops();
</script>

<template>
    <div class="shops-page">

        <!-- PAGE INTRODUCTION -->

        <section class="shops-intro">
            <div class="shops-intro-copy">
                <h2>Manage registered shops</h2>

                <p>
                    Review barber shops and beauty parlors,
                    monitor approval status, and manage shop
                    access across the StyleHub platform.
                </p>
            </div>

            <div class="shops-intro-actions">
                <div class="shops-total-card">
                    <div class="shops-total-icon">
                        <svg
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
                    </div>

                    <div>
                        <span>Registered shops</span>

                        <strong>
                            {{ formatNumber(pagination.total) }}
                        </strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button shops-refresh-button"
                    :disabled="isLoading"
                    @click="loadShops(pagination.current_page)"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M20 11a8 8 0 10-2.3 5.7"
                        />

                        <path d="M20 5v6h-6" />
                    </svg>

                    <span>Refresh</span>
                </button>
            </div>
        </section>

        <!-- SHOP SUMMARY -->

        <section class="shop-summary-grid">

            <article class="shop-summary-card">
                <div class="shop-summary-icon approved-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M8 12l3 3 5-6" />
                    </svg>
                </div>

                <div>
                    <span>Approved</span>

                    <strong>
                        {{ formatNumber(currentPageSummary.approved) }}
                    </strong>

                    <small>On this page</small>
                </div>
            </article>

            <article class="shop-summary-card">
                <div class="shop-summary-icon pending-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 7v5l3 2" />
                    </svg>
                </div>

                <div>
                    <span>Pending</span>

                    <strong>
                        {{ formatNumber(currentPageSummary.pending) }}
                    </strong>

                    <small>On this page</small>
                </div>
            </article>

            <article class="shop-summary-card">
                <div class="shop-summary-icon rejected-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path d="M9 9l6 6M15 9l-6 6" />
                    </svg>
                </div>

                <div>
                    <span>Rejected</span>

                    <strong>
                        {{ formatNumber(currentPageSummary.rejected) }}
                    </strong>

                    <small>On this page</small>
                </div>
            </article>

            <article class="shop-summary-card">
                <div class="shop-summary-icon suspended-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                        />

                        <path d="M9 9l6 6M15 9l-6 6" />
                    </svg>
                </div>

                <div>
                    <span>Suspended</span>

                    <strong>
                        {{ formatNumber(currentPageSummary.suspended) }}
                    </strong>

                    <small>On this page</small>
                </div>
            </article>

        </section>

        <!-- FILTER PANEL -->

        <section class="filter-card shops-filter-card">
            <div class="filter-card-heading">
                <div class="filter-heading-left">
                    <div class="filter-heading-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                    </div>

                    <div>
                        <h3>Filter shops</h3>

                        <p>
                            Find shops using account, approval,
                            business, or location information.
                        </p>
                    </div>
                </div>

                <span
                    v-if="activeFiltersCount > 0"
                    class="active-filter-count"
                >
                    {{ activeFiltersCount }} active
                </span>
            </div>

            <form
                class="filters shops-filters"
                @submit.prevent="applyFilters"
            >

                <!-- SEARCH -->

                <div class="search-field shops-search-field">
                    <label for="shop-search">
                        Search shops
                    </label>

                    <div class="input-with-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-4-4" />
                        </svg>

                        <input
                            id="shop-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="100"
                            placeholder="Shop name, owner email or location"
                        >
                    </div>
                </div>

                <!-- APPROVAL -->

                <div class="filter-field">
                    <label for="approval">
                        Approval
                    </label>

                    <select
                        id="approval"
                        v-model="filters.approval_status"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in approvalOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- ADMIN STATUS -->

                <div class="filter-field">
                    <label for="administrative-status">
                        Admin status
                    </label>

                    <select
                        id="administrative-status"
                        v-model="filters.administrative_status"
                    >
                        <option value="">
                            All states
                        </option>

                        <option
                            v-for="option in administrativeStatusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- SHOP TYPE -->

                <div class="filter-field">
                    <label for="shop-type">
                        Shop type
                    </label>

                    <select
                        id="shop-type"
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

                <!-- AVAILABILITY -->

                <div class="filter-field">
                    <label for="availability">
                        Availability
                    </label>

                    <select
                        id="availability"
                        v-model="filters.availability_status"
                    >
                        <option value="">
                            All availability
                        </option>

                        <option
                            v-for="option in availabilityOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <!-- CITY -->

                <div class="filter-field">
                    <label for="city">
                        City
                    </label>

                    <input
                        id="city"
                        v-model="filters.city"
                        type="text"
                        maxlength="100"
                        placeholder="City"
                    >
                </div>

                <!-- PROVINCE -->

                <div class="filter-field">
                    <label for="province">
                        Province
                    </label>

                    <input
                        id="province"
                        v-model="filters.province"
                        type="text"
                        maxlength="100"
                        placeholder="Province"
                    >
                </div>

                <!-- FILTER ACTIONS -->

                <div class="filter-actions shops-filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="isLoading"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 6h16M7 12h10M10 18h4" />
                        </svg>

                        <span>Apply filters</span>
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="isLoading || activeFiltersCount === 0"
                        @click="resetFilters"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 4v6h6" />
                            <path d="M5.5 15a8 8 0 102-8" />
                        </svg>

                        <span>Reset</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- ERROR MESSAGE -->

        <div
            v-if="errorMessage"
            class="error-message shops-error"
            role="alert"
        >
            <div class="shops-error-icon">
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v5" />
                    <path d="M12 17h.01" />
                </svg>
            </div>

            <div class="shops-error-copy">
                <strong>Unable to load shops</strong>
                <p>{{ errorMessage }}</p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="loadShops(pagination.current_page)"
            >
                Try again
            </button>
        </div>

        <!-- SHOP DIRECTORY -->

        <section class="shops-card">
            <div class="table-header shops-table-header">

                <div class="table-heading">
                    <div class="table-heading-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 10v10h16V10" />
                            <path d="M3 10l2-6h14l2 6" />
                            <path d="M8 20v-6h8v6" />
                        </svg>
                    </div>

                    <div>
                        <h3>Shop directory</h3>

                        <p>
                            {{ formatNumber(pagination.total) }}
                            registered shop{{ pagination.total === 1 ? '' : 's' }}
                        </p>
                    </div>
                </div>

                <div class="table-header-actions">
                    <div
                        v-if="activeFiltersCount > 0"
                        class="table-filter-status"
                    >
                        <span class="table-filter-dot" />
                        Filtered
                    </div>

                    <div class="per-page">
                        <label for="per-page">
                            Rows
                        </label>

                        <select
                            id="per-page"
                            v-model.number="filters.per_page"
                            :disabled="isLoading"
                            @change="loadShops(1)"
                        >
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- EMPTY STATE -->

            <div
                v-if="shops.length === 0"
                class="empty-state shops-empty-state"
            >
                <div class="empty-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path d="M4 10v10h16V10" />
                        <path d="M3 10l2-6h14l2 6" />
                        <path d="M8 20v-6h8v6" />
                    </svg>
                </div>

                <h3>No shops found</h3>

                <p>
                    No registered shops match the filters you selected.
                </p>

                <button
                    v-if="activeFiltersCount > 0"
                    type="button"
                    class="secondary-button empty-reset-button"
                    :disabled="isLoading"
                    @click="resetFilters"
                >
                    Clear filters
                </button>
            </div>

            <!-- SHOP TABLE -->

            <div
                v-else
                class="table-wrapper shops-table-wrapper"
            >
                <table>
                    <thead>
                        <tr>
                            <th>Shop</th>
                            <th>Owner</th>
                            <th>Location</th>
                            <th>Approval</th>
                            <th>Admin status</th>
                            <th>Availability</th>
                            <th>Registered</th>
                            <th class="table-actions-heading">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="shop in shops"
                            :key="shop.id"
                            class="shop-table-row"
                            @dblclick="openShop(shop)"
                        >

                            <!-- SHOP -->

                            <td>
                                <div class="shop-cell">
                                    <div class="shop-avatar">
                                        {{ shopInitial(shop) }}
                                    </div>

                                    <div class="shop-primary-copy">
                                        <strong>
                                            {{ shop.name || 'Unnamed shop' }}
                                        </strong>

                                        <div class="shop-type-line">
                                            <span>
                                                {{ humanize(shop.type) }}
                                            </span>

                                            <span
                                                v-if="shop.id"
                                                class="shop-id"
                                            >
                                                ID #{{ shop.id }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- OWNER -->

                            <td>
                                <div class="owner-cell">
                                    <strong>
                                        {{ shop.owner?.full_name || 'No owner' }}
                                    </strong>

                                    <span class="wrap-value">
                                        {{ shop.owner?.email || 'No email' }}
                                    </span>
                                </div>
                            </td>

                            <!-- LOCATION -->

                            <td>
                                <div class="location-cell">
                                    <div class="location-primary">
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

                                        <strong>
                                            {{ shop.city || 'Not specified' }}
                                        </strong>
                                    </div>

                                    <span>
                                        {{ shop.province || 'Province not specified' }}
                                    </span>
                                </div>
                            </td>

                            <!-- APPROVAL -->

                            <td>
                                <span
                                    class="status-badge shop-status-badge"
                                    :class="statusClass(shop.approval_status)"
                                >
                                    <span class="badge-dot" />

                                    {{ humanize(shop.approval_status) }}
                                </span>
                            </td>

                            <!-- ADMIN STATUS -->

                            <td>
                                <div class="administrative-state-cell">
                                    <span
                                        class="administrative-badge"
                                        :class="statusClass(shop.administrative_status)"
                                    >
                                        <span class="badge-dot" />

                                        {{ humanize(shop.administrative_status) }}
                                    </span>

                                    <span
                                        v-if="shop.suspension?.is_suspended"
                                        class="shop-suspension-warning"
                                        :title="shop.suspension?.reason || ''"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            aria-hidden="true"
                                        >
                                            <circle cx="12" cy="12" r="9" />
                                            <path d="M12 8v5M12 17h.01" />
                                        </svg>

                                        Suspension active
                                    </span>
                                </div>
                            </td>

                            <!-- AVAILABILITY -->

                            <td>
                                <span
                                    class="availability-badge"
                                    :class="statusClass(shop.availability_status)"
                                >
                                    {{ humanize(shop.availability_status) }}
                                </span>
                            </td>

                            <!-- REGISTRATION DATE -->

                            <td>
                                <div class="registered-cell">
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
                                        {{ formatDate(shop.created_at) }}
                                    </span>
                                </div>
                            </td>

                            <!-- VIEW ACTION -->

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button shop-view-button"
                                    @click="openShop(shop)"
                                >
                                    <span>View</span>

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M9 18l6-6-6-6" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->

            <div
                v-if="pagination.total > 0"
                class="pagination shops-pagination"
            >
                <div class="pagination-summary">
                    <span>Showing</span>
                    <strong>{{ firstResult }}</strong>
                    <span>–</span>
                    <strong>{{ lastResult }}</strong>
                    <span>of</span>
                    <strong>{{ formatNumber(pagination.total) }}</strong>
                    <span>shops</span>
                </div>

                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        aria-label="Previous page"
                        :disabled="pagination.current_page <= 1 || isLoading"
                        @click="changePage(pagination.current_page - 1)"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M15 18l-6-6 6-6" />
                        </svg>

                        <span>Previous</span>
                    </button>

                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        class="page-button"
                        :class="{
                            active: page === pagination.current_page
                        }"
                        :disabled="isLoading"
                        @click="changePage(page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        aria-label="Next page"
                        :disabled="pagination.current_page >= pagination.last_page || isLoading"
                        @click="changePage(pagination.current_page + 1)"
                    >
                        <span>Next</span>

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
.shops-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.shops-intro {
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
            rgba(214, 154, 45, 0.07),
            transparent 26%
        ),
        var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.shops-intro-copy {
    min-width: 0;
    flex: 1;
}

.shops-intro-label {
    width: fit-content;
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 5px;
    color: var(--sh-accent);
    font-size: 8.5px;
    font-weight: 800;
    letter-spacing: 1.1px;
    text-transform: uppercase;
}

.shops-intro-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--sh-accent);
    box-shadow: 0 0 0 4px rgba(214, 154, 45, 0.08);
}

.shops-intro h2 {
    margin: 0;
    color: var(--sh-text);
    font-size: 22px;
    font-weight: 800;
    line-height: 29px;
    letter-spacing: -0.55px;
}

.shops-intro p {
    max-width: 650px;
    margin: 5px 0 0;
    color: var(--sh-text-3);
    font-size: 10.5px;
    line-height: 17px;
}

.shops-intro-actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

.shops-total-card {
    min-width: 168px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--sh-border);
    border-radius: 12px;
    background: var(--sh-surface-muted);
}

.shops-total-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.shops-total-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.shops-total-card > div:last-child {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.shops-total-card span {
    color: var(--sh-text-3);
    font-size: 8.5px;
    font-weight: 600;
}

.shops-total-card strong {
    color: var(--sh-text);
    font-size: 17px;
    font-weight: 800;
    line-height: 21px;
}

.shops-refresh-button {
    min-height: 48px;
}

.shop-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 11px;
}

.shop-summary-card {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 82px;
    padding: 13px 14px;
    border: 1px solid var(--sh-border);
    border-radius: 13px;
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.shop-summary-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.shop-summary-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.approved-icon {
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.pending-icon {
    background: var(--sh-warning-bg);
    color: var(--sh-warning);
}

.rejected-icon,
.suspended-icon {
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
}

.shop-summary-card > div:last-child {
    min-width: 0;
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    column-gap: 8px;
}

.shop-summary-card span {
    grid-column: 1;
    color: var(--sh-text-3);
    font-size: 9px;
    font-weight: 700;
}

.shop-summary-card strong {
    grid-column: 2;
    grid-row: 1 / 3;
    color: var(--sh-text);
    font-size: 18px;
    font-weight: 800;
}

.shop-summary-card small {
    grid-column: 1;
    color: #a1a8b2;
    font-size: 8px;
}

.shops-filter-card {
    padding: 0;
}

.filter-card-heading {
    min-height: 66px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 13px 16px;
    border-bottom: 1px solid var(--sh-border);
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
    background: var(--sh-surface-muted);
    color: var(--sh-text-2);
}

.filter-heading-icon svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.filter-heading-left h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 12px;
    font-weight: 800;
}

.filter-heading-left p {
    margin: 2px 0 0;
    color: var(--sh-text-3);
    font-size: 8.5px;
}

.active-filter-count {
    min-height: 25px;
    display: inline-flex;
    align-items: center;
    padding: 0 8px;
    border: 1px solid #eadfbf;
    border-radius: 999px;
    background: var(--sh-accent-soft);
    color: #845d1d;
    font-size: 8px;
    font-weight: 800;
    white-space: nowrap;
}

.shops-filters {
    display: grid;
    grid-template-columns:
        minmax(230px, 1.8fr)
        repeat(3, minmax(135px, 1fr));
    align-items: end;
    gap: 11px;
    padding: 15px 16px 16px;
}

.shops-search-field {
    flex: none;
}

.shops-filter-actions {
    align-items: center;
    grid-column: span 2;
    align-self: end;
}

.shops-filter-actions .primary-button,
.shops-filter-actions .secondary-button {
    min-height: 42px;
}

.shops-error {
    align-items: center;
    margin: 0;
}

.shops-error-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: rgba(169, 79, 79, 0.08);
}

.shops-error-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.shops-error-copy {
    min-width: 0;
    flex: 1;
}

.shops-error-copy strong {
    display: block;
    font-size: 10.5px;
}

.shops-error-copy p {
    margin: 2px 0 0;
}

.shops-table-header {
    padding: 13px 16px;
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
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.table-heading-icon svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
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
    color: var(--sh-text-3);
    font-size: 8.5px;
    font-weight: 700;
}

.table-filter-dot {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: var(--sh-accent);
}

.per-page {
    display: flex;
    align-items: center;
    gap: 6px;
}

.per-page label {
    color: var(--sh-text-3);
    font-size: 8.5px;
    font-weight: 700;
}

.per-page select {
    width: 69px;
    min-height: 34px;
    padding: 5px 8px;
    font-size: 9px;
}

.shops-table-wrapper {
    transition: opacity 0.16s ease;
}

.shops-table-wrapper table {
    min-width: 1180px;
}

.shop-table-row {
    cursor: default;
}

.shop-primary-copy {
    min-width: 0;
}

.shop-type-line {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}

.shop-type-line > span {
    margin: 0;
}

.shop-id {
    padding: 1px 5px;
    border-radius: 5px;
    background: #f2f4f7;
    color: #969da7 !important;
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
    font-size: 7.5px !important;
}

.location-primary {
    display: flex;
    align-items: center;
    gap: 5px;
}

.location-primary svg {
    width: 13px;
    height: 13px;
    flex: 0 0 13px;
    fill: none;
    stroke: var(--sh-accent);
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.shop-status-badge,
.administrative-badge {
    gap: 5px;
}

.badge-dot {
    width: 5px;
    height: 5px;
    flex: 0 0 5px;
    border-radius: 999px;
    background: currentColor;
}

.administrative-state-cell {
    display: flex;
    align-items: flex-start;
    flex-direction: column;
    gap: 5px;
}

.shop-suspension-warning {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--sh-danger);
    font-size: 7.5px;
    font-weight: 700;
    white-space: nowrap;
}

.shop-suspension-warning svg {
    width: 11px;
    height: 11px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.availability-badge.open,
.availability-badge.available {
    border-color: #d4ebdf;
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.availability-badge.closed,
.availability-badge.unavailable {
    border-color: #e1e5ea;
    background: #f4f6f8;
    color: #737c87;
}

.registered-cell {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--sh-text-2);
    white-space: nowrap;
}

.registered-cell svg {
    width: 13px;
    height: 13px;
    flex: 0 0 13px;
    fill: none;
    stroke: var(--sh-text-3);
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.table-actions-heading {
    text-align: right !important;
}

.shop-view-button {
    min-height: 34px;
    padding: 0 10px;
}

.shop-view-button svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.shops-empty-state {
    min-height: 280px;
}

.empty-reset-button {
    margin-top: 13px;
}

.shops-pagination {
    background: #fbfcfd;
}

.pagination-summary {
    display: flex;
    align-items: center;
    gap: 4px;
    color: var(--sh-text-3);
    font-size: 9px;
}

.pagination-summary strong {
    color: var(--sh-text-2);
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
    stroke: currentColor;
    stroke-width: 1.9;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE DESIGN
|--------------------------------------------------------------------------
*/

@media (max-width: 1350px) {
    .shops-filters {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .shops-search-field {
        grid-column: span 2;
    }

    .shops-filter-actions {
        grid-column: span 1;
    }
}

@media (max-width: 1100px) {
    .shop-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .shops-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .shops-intro-actions {
        width: 100%;
    }

    .shops-total-card {
        flex: 1;
    }
}

@media (max-width: 850px) {
    .shops-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .shops-search-field {
        grid-column: 1 / -1;
    }

    .shops-filter-actions {
        grid-column: 1 / -1;
    }
}

@media (max-width: 680px) {
    .shops-intro {
        padding: 17px;
    }

    .shops-intro h2 {
        font-size: 19px;
    }

    .shops-intro-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .shops-total-card,
    .shops-refresh-button {
        width: 100%;
    }

    .shop-summary-grid {
        grid-template-columns: 1fr;
    }

    .shops-filters {
        display: flex;
        align-items: stretch;
        flex-direction: column;
        padding: 14px;
    }

    .filter-card-heading,
    .filter-heading-left {
        align-items: flex-start;
    }

    .shops-filter-actions {
        width: 100%;
    }

    .shops-filter-actions .primary-button,
    .shops-filter-actions .secondary-button {
        width: 100%;
    }

    .shops-table-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .table-header-actions {
        width: 100%;
        justify-content: space-between;
    }

    .shops-error {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .shops-error .retry-button {
        width: 100%;
    }

    .shops-pagination {
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