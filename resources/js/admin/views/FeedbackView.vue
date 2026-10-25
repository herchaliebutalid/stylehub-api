
<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const feedbackRecords = ref([]);
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
    rating: '',
    shop_id: '',
    customer_id: '',
    date_from: '',
    date_to: '',
    per_page: 20,
});

const ratingOptions = [
    { value: 5, label: '5 Stars' },
    { value: 4, label: '4 Stars' },
    { value: 3, label: '3 Stars' },
    { value: 2, label: '2 Stars' },
    { value: 1, label: '1 Star' },
];

const numberFormatter = new Intl.NumberFormat('en-PH');

function formatNumber(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? numberFormatter.format(number) : '0';
}

const firstResult = computed(() =>
    pagination.value.total === 0
        ? 0
        : (pagination.value.current_page - 1) * pagination.value.per_page + 1,
);

const lastResult = computed(() =>
    Math.min(pagination.value.total, pagination.value.current_page * pagination.value.per_page),
);

const visiblePages = computed(() => {
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    const pages = [];
    for (let page = start; page <= end; page += 1) pages.push(page);
    return pages;
});

const activeFiltersCount = computed(() =>
    ['search', 'rating', 'shop_id', 'customer_id', 'date_from', 'date_to']
        .filter((key) => String(filters[key] ?? '').trim() !== '').length,
);

// These figures are calculated from records on the currently loaded page.
// The API's pagination.total is the total matching the current filters.
const pageSummary = computed(() => {
    const records = feedbackRecords.value;
    const scores = records
        .map((record) => Number(record.rating))
        .filter((rating) => Number.isFinite(rating) && rating >= 1 && rating <= 5);
    const average = scores.length
        ? (scores.reduce((sum, rating) => sum + rating, 0) / scores.length).toFixed(1)
        : '—';

    return {
        reviews: records.length,
        average,
        fiveStars: scores.filter((rating) => rating === 5).length,
        comments: records.filter((record) => String(record.comment ?? '').trim()).length,
    };
});

function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(date);
}

function truncateComment(value, length = 80) {
    if (!value) return 'No comment provided.';
    const text = String(value);
    return text.length <= length ? text : `${text.slice(0, length)}…`;
}

function buildQuery(page) {
    const query = new URLSearchParams();
    const search = filters.search.trim();

    if (search) query.set('search', search);
    if (filters.rating) query.set('rating', String(filters.rating));
    if (filters.shop_id) query.set('shop_id', String(filters.shop_id));
    if (filters.customer_id) query.set('customer_id', String(filters.customer_id));
    if (filters.date_from) query.set('date_from', filters.date_from);
    if (filters.date_to) query.set('date_to', filters.date_to);

    query.set('page', String(page));
    query.set('per_page', String(filters.per_page));
    return query.toString();
}

async function authenticationFailure() {
    auth.clearSession();
    await router.replace({ name: 'admin.login' });
}

async function loadFeedback(page = 1) {
    if (!auth.token) {
        await authenticationFailure();
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await apiRequest(`/admin/feedback?${buildQuery(page)}`, {
            token: auth.token,
        });

        feedbackRecords.value = Array.isArray(response?.data?.feedback)
            ? response.data.feedback
            : [];

        pagination.value = {
            current_page: Number(response?.data?.pagination?.current_page ?? 1),
            per_page: Number(response?.data?.pagination?.per_page ?? filters.per_page),
            total: Number(response?.data?.pagination?.total ?? 0),
            last_page: Number(response?.data?.pagination?.last_page ?? 1),
            has_more_pages: Boolean(response?.data?.pagination?.has_more_pages),
        };
    } catch (error) {
        if (error?.status === 401 || error?.status === 403) {
            await authenticationFailure();
            return;
        }
        errorMessage.value =
            error?.data?.message ?? error?.message ?? 'Unable to load feedback.';
    } finally {
        isLoading.value = false;
    }
}

async function applyFilters() {
    if (filters.date_from && filters.date_to && filters.date_to < filters.date_from) {
        errorMessage.value = 'The end date must be on or after the start date.';
        return;
    }
    await loadFeedback(1);
}

async function resetFilters() {
    filters.search = '';
    filters.rating = '';
    filters.shop_id = '';
    filters.customer_id = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.per_page = 20;
    await loadFeedback(1);
}

async function changePage(page) {
    if (
        page < 1 || page > pagination.value.last_page ||
        page === pagination.value.current_page || isLoading.value
    ) return;
    await loadFeedback(page);
}

async function openFeedback(feedback) {
    await router.push({
        name: 'admin.feedback.detail',
        params: { feedbackId: feedback.id },
    });
}

onMounted(() => {
    void loadFeedback();
});
</script>

<template>
    <div class="feedback-page">
        <!-- Same introduction card style used by the Shops page -->
        <section class="feedback-intro">
            <div class="feedback-intro-copy">
                
                <h2>Manage customer feedback</h2>
                <p>
                    Review ratings and comments submitted for completed StyleHub
                    appointments, and monitor feedback across the platform.
                </p>
            </div>
            <div class="feedback-intro-actions">
                <div class="feedback-total-card">
                    <span class="feedback-total-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 5h16v12H9l-5 4V5z" />
                            <path d="M8 9h8M8 13h5" />
                        </svg>
                    </span>
                    <div>
                        <span>Matching reviews</span>
                        <strong>{{ formatNumber(pagination.total) }}</strong>
                    </div>
                </div>
                <button
                    type="button"
                    class="secondary-button feedback-refresh-button"
                    :disabled="isLoading"
                    @click="loadFeedback(pagination.current_page)"
                >
                    <svg :class="{ spinning: isLoading }" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20 11a8 8 0 10-2.3 5.7" />
                        <path d="M20 5v6h-6" />
                    </svg>
                    {{ isLoading ? 'Refreshing' : 'Refresh' }}
                </button>
            </div>
        </section>

        <!-- Summary cards, with explicitly page-scoped values -->
        <section v-if="feedbackRecords.length > 0" class="feedback-summary-grid">
            <article class="feedback-summary-card">
                <div class="feedback-summary-icon reviews-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v12H9l-5 4V5z" />
                        <path d="M8 9h8M8 13h5" />
                    </svg>
                </div>
                <div class="feedback-summary-copy">
                    <span>Reviews</span>
                    <strong>{{ formatNumber(pageSummary.reviews) }}</strong>
                    <small>On this page</small>
                </div>
            </article>
            <article class="feedback-summary-card">
                <div class="feedback-summary-icon average-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3z" />
                    </svg>
                </div>
                <div class="feedback-summary-copy">
                    <span>Average rating</span>
                    <strong>{{ pageSummary.average }}</strong>
                    <small>On this page</small>
                </div>
            </article>
            <article class="feedback-summary-card">
                <div class="feedback-summary-icon five-star-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M5 12l4 4L19 6" />
                        <circle cx="12" cy="12" r="9" />
                    </svg>
                </div>
                <div class="feedback-summary-copy">
                    <span>5-star reviews</span>
                    <strong>{{ formatNumber(pageSummary.fiveStars) }}</strong>
                    <small>On this page</small>
                </div>
            </article>
            <article class="feedback-summary-card">
                <div class="feedback-summary-icon comments-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v12H9l-5 4V5z" />
                        <path d="M8 9h8M8 13h5" />
                    </svg>
                </div>
                <div class="feedback-summary-copy">
                    <span>With comments</span>
                    <strong>{{ formatNumber(pageSummary.comments) }}</strong>
                    <small>On this page</small>
                </div>
            </article>
        </section>

        <!-- Read-only requirement remains unchanged -->
        <section class="feedback-information">
            <span class="feedback-information-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 11v5M12 8h.01" />
                </svg>
            </span>
            <div>
                <strong>Read-only feedback monitoring</strong>
                <p>
                    Administrators can review feedback, but cannot edit or delete
                    customer reviews from this module.
                </p>
            </div>
        </section>

        <!-- Matching Shops filter card with a heading -->
        <section class="filter-card feedback-filter-card">
            <div class="feedback-filter-heading">
                <div class="feedback-filter-heading-left">
                    <span class="feedback-filter-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                    </span>
                    <div>
                        <h3>Filter feedback</h3>
                        <p>Find reviews using rating, customer, shop, or submission date.</p>
                    </div>
                </div>
                <span v-if="activeFiltersCount > 0" class="feedback-active-filters">
                    {{ activeFiltersCount }} active
                </span>
            </div>

            <form class="filters feedback-filters" @submit.prevent="applyFilters">
                <div class="search-field feedback-search-field">
                    <label for="feedback-search">Search</label>
                    <div class="input-with-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-4-4" />
                        </svg>
                        <input
                            id="feedback-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="100"
                            placeholder="Comment, customer or shop"
                        />
                    </div>
                </div>

                <div class="filter-field">
                    <label for="rating">Rating</label>
                    <select id="rating" v-model="filters.rating">
                        <option value="">All ratings</option>
                        <option
                            v-for="option in ratingOptions"
                            :key="option.value"
                            :value="option.value"
                        >{{ option.label }}</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="feedback-shop">Shop ID</label>
                    <input
                        id="feedback-shop"
                        v-model="filters.shop_id"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    />
                </div>

                <div class="filter-field">
                    <label for="feedback-customer">Customer ID</label>
                    <input
                        id="feedback-customer"
                        v-model="filters.customer_id"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    />
                </div>

                <div class="filter-field">
                    <label for="feedback-from">Date From</label>
                    <input id="feedback-from" v-model="filters.date_from" type="date" />
                </div>

                <div class="filter-field">
                    <label for="feedback-to">Date To</label>
                    <input id="feedback-to" v-model="filters.date_to" type="date" />
                </div>

                <div class="filter-actions feedback-filter-actions">
                    <button class="primary-button" type="submit" :disabled="isLoading">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 6h16M7 12h10M10 18h4" />
                        </svg>
                        <span>Apply filters</span>
                    </button>
                    <button
                        class="secondary-button"
                        type="button"
                        :disabled="isLoading || activeFiltersCount === 0"
                        @click="resetFilters"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 4v6h6" />
                            <path d="M5.5 15a8 8 0 1 0 2-8" />
                        </svg>
                        <span>Reset</span>
                    </button>
                </div>
            </form>
        </section>

        <div v-if="errorMessage" class="error-message feedback-error" role="alert">
            <div class="feedback-error-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v5M12 17h.01" />
                </svg>
            </div>
            <div class="feedback-error-copy">
                <strong>Unable to load feedback</strong>
                <p>{{ errorMessage }}</p>
            </div>
            <button
                class="retry-button"
                type="button"
                :disabled="isLoading"
                @click="loadFeedback(pagination.current_page)"
            >Try again</button>
        </div>

        <!-- Same table-card layout used by other admin pages -->
        <section class="feedback-card feedback-table-card">
            <div class="table-header feedback-table-header">
                <div class="feedback-table-title">
                    <span class="feedback-table-heading-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 5h16v12H9l-5 4V5z" />
                            <path d="M8 9h8M8 13h5" />
                        </svg>
                    </span>
                    <div>
                        <h3>Feedback records</h3>
                        <p>{{ formatNumber(pagination.total) }} matching reviews · Read only</p>
                    </div>
                </div>
                <div class="feedback-table-controls">
                    <span v-if="activeFiltersCount > 0" class="feedback-filter-indicator">
                        <span></span> Filtered
                    </span>
                    <div class="per-page">
                        <label for="feedback-per-page">Rows</label>
                        <select
                            id="feedback-per-page"
                            v-model.number="filters.per_page"
                            :disabled="isLoading"
                            @change="loadFeedback(1)"
                        >
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                            <option :value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div v-if="isLoading && feedbackRecords.length === 0" class="feedback-loading">
                <div class="spinner"></div>
                <h3>Loading feedback records</h3>
                <p>Retrieving customer reviews and appointment information.</p>
            </div>

            <div v-else-if="feedbackRecords.length === 0" class="empty-state feedback-empty">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v12H9l-5 4V5z" />
                        <path d="M8 9h8M8 13h5" />
                    </svg>
                </div>
                <h3>No feedback found</h3>
                <p>No feedback records match the current filters.</p>
                <button
                    v-if="activeFiltersCount > 0"
                    type="button"
                    class="secondary-button feedback-empty-reset"
                    :disabled="isLoading"
                    @click="resetFilters"
                >Clear filters</button>
            </div>

            <div v-else class="table-wrapper feedback-table-wrapper" :class="{ 'feedback-refreshing': isLoading }">
                <table>
                    <thead>
                        <tr>
                            <th>Rating</th>
                            <th>Customer</th>
                            <th>Shop</th>
                            <th>Comment</th>
                            <th>Appointment</th>
                            <th>Submitted</th>
                            <th class="feedback-action-heading">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="feedback in feedbackRecords" :key="feedback.id" class="feedback-table-row">
                            <td>
                                <div class="rating-cell">
                                    <div class="stars" aria-hidden="true">
                                        <span
                                            v-for="star in 5"
                                            :key="star"
                                            :class="{ filled: star <= feedback.rating }"
                                        >★</span>
                                    </div>
                                    <strong>{{ feedback.rating }}/5</strong>
                                </div>
                            </td>
                            <td>
                                <div class="person-cell">
                                    <strong>{{ feedback.customer?.full_name || 'Unavailable' }}</strong>
                                    <span>{{ feedback.customer?.email || '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="shop-cell">
                                    <strong>{{ feedback.shop?.name || 'Unavailable' }}</strong>
                                    <span>{{ feedback.shop?.city || '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="comment-cell">{{ truncateComment(feedback.comment) }}</div>
                            </td>
                            <td>
                                <div class="appointment-cell">
                                    <strong>{{ feedback.appointment?.id ? `#${feedback.appointment.id}` : '—' }}</strong>
                                    <span>{{ feedback.appointment?.appointment_date || '—' }}</span>
                                </div>
                            </td>
                            <td>{{ formatDateTime(feedback.created_at) }}</td>
                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button feedback-view-button"
                                    @click="openFeedback(feedback)"
                                >
                                    <span>View</span>
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M9 18l6-6-6-6" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination.total > 0" class="pagination feedback-pagination">
                <div class="feedback-pagination-summary">
                    Showing <strong>{{ firstResult }}</strong>–<strong>{{ lastResult }}</strong>
                    of <strong>{{ formatNumber(pagination.total) }}</strong> reviews
                </div>
                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button"
                        aria-label="Previous page"
                        :disabled="pagination.current_page <= 1 || isLoading"
                        @click="changePage(pagination.current_page - 1)"
                    >Previous</button>
                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        class="page-button"
                        :class="{ active: page === pagination.current_page }"
                        :disabled="isLoading"
                        @click="changePage(page)"
                    >{{ page }}</button>
                    <button
                        type="button"
                        class="page-button"
                        aria-label="Next page"
                        :disabled="pagination.current_page >= pagination.last_page || isLoading"
                        @click="changePage(pagination.current_page + 1)"
                    >Next</button>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped src="./admin-page.css"></style>

<style scoped>
.feedback-page{display:flex;flex-direction:column;gap:14px}
/* Same white-and-warm-gold introduction surface as Shops */
.feedback-intro{min-height:126px;display:flex;align-items:center;justify-content:space-between;gap:24px;padding:20px 22px;border:1px solid var(--sh-border);border-radius:var(--sh-radius-xl);background:radial-gradient(circle at 94% 10%,rgba(214,154,45,.07),transparent 26%),var(--sh-surface);box-shadow:var(--sh-shadow-sm)}
.feedback-intro-copy{min-width:0;flex:1}
.feedback-intro-label{width:fit-content;display:flex;align-items:center;gap:7px;margin-bottom:5px;color:var(--sh-accent);font-size:8.5px;font-weight:800;letter-spacing:1.1px;text-transform:uppercase}
.feedback-intro-dot{width:6px;height:6px;border-radius:999px;background:var(--sh-accent);box-shadow:0 0 0 4px rgba(214,154,45,.08)}
.feedback-intro h2{margin:0;color:var(--sh-text);font-size:22px;font-weight:800;line-height:29px;letter-spacing:-.55px}
.feedback-intro p{max-width:650px;margin:5px 0 0;color:var(--sh-text-3);font-size:10.5px;line-height:17px}
.feedback-intro-actions{flex:0 0 auto;display:flex;align-items:center;gap:10px}
.feedback-total-card{min-width:168px;display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--sh-border);border-radius:12px;background:var(--sh-surface-muted)}
.feedback-total-icon{width:38px;height:38px;flex:0 0 38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--sh-accent-soft);color:var(--sh-accent)}
.feedback-total-icon svg,.feedback-intro .feedback-refresh-button svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.feedback-total-card > div:last-child{display:flex;flex-direction:column;gap:1px}
.feedback-total-card span:not(.feedback-total-icon){color:var(--sh-text-3);font-size:8.5px;font-weight:600}
.feedback-total-card strong{color:var(--sh-text);font-size:17px;font-weight:800;line-height:21px}
.feedback-refresh-button{min-height:48px;display:inline-flex;align-items:center;justify-content:center;gap:8px}
/* Quick overview cards share the Shops card rhythm */
.feedback-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}
.feedback-summary-card{min-width:0;min-height:82px;display:flex;align-items:center;gap:11px;padding:13px 14px;border:1px solid var(--sh-border);border-radius:13px;background:var(--sh-surface);box-shadow:var(--sh-shadow-sm)}
.feedback-summary-icon{width:38px;height:38px;flex:0 0 38px;display:flex;align-items:center;justify-content:center;border-radius:10px}
.feedback-summary-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.reviews-icon{background:var(--sh-info-bg);color:var(--sh-info)}
.average-icon{background:var(--sh-warning-bg);color:var(--sh-warning)}
.five-star-icon{background:var(--sh-success-bg);color:var(--sh-success)}
.comments-icon{background:var(--sh-purple-bg);color:var(--sh-purple)}
.feedback-summary-copy{min-width:0;flex:1;display:grid;grid-template-columns:1fr auto;align-items:center;column-gap:8px}
.feedback-summary-copy span{grid-column:1;color:var(--sh-text-3);font-size:9px;font-weight:700}
.feedback-summary-copy strong{grid-column:2;grid-row:1 / 3;color:var(--sh-text);font-size:18px;font-weight:800}
.feedback-summary-copy small{grid-column:1;color:#a1a8b2;font-size:8px}
/* Read-only notice */
.feedback-information{display:flex;align-items:flex-start;gap:11px;padding:14px 16px;border:1px solid #dce7f5;border-radius:13px;background:#f4f8fe}
.feedback-information-icon{width:36px;height:36px;flex:0 0 36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:#fff;color:var(--sh-info)}
.feedback-information-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.feedback-information > div{min-width:0;flex:1}
.feedback-information strong{color:var(--sh-text-2);font-size:10px;font-weight:800}
.feedback-information p{margin:4px 0 0;color:var(--sh-text-3);font-size:9px;line-height:16px}
/* Filters */
.feedback-filter-card{padding:0}
.feedback-filter-heading{min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 16px;border-bottom:1px solid var(--sh-border)}
.feedback-filter-heading-left{min-width:0;display:flex;align-items:center;gap:10px}
.feedback-filter-icon{width:36px;height:36px;flex:0 0 36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--sh-surface-muted);color:var(--sh-text-2)}
.feedback-filter-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.feedback-filter-heading h3{margin:0;color:var(--sh-text);font-size:12px;font-weight:800}
.feedback-filter-heading p{margin:2px 0 0;color:var(--sh-text-3);font-size:8.5px}
.feedback-active-filters{min-height:25px;display:inline-flex;align-items:center;padding:0 8px;border:1px solid #eadfbf;border-radius:999px;background:var(--sh-accent-soft);color:#845d1d;font-size:8px;font-weight:800;white-space:nowrap}
.feedback-filters{display:grid;grid-template-columns:minmax(230px,1.8fr) repeat(2,minmax(135px,1fr));align-items:end;gap:11px;padding:15px 16px 16px}
.feedback-search-field{flex:none}
.feedback-filter-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;grid-column:1 / -1}
.feedback-filter-actions .primary-button,.feedback-filter-actions .secondary-button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:7px}
.feedback-filter-actions svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
/* Error */
.feedback-error{display:flex;align-items:center;gap:10px;margin:0}
.feedback-error-icon{width:38px;height:38px;flex:0 0 38px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:rgba(169,79,79,.08)}
.feedback-error-icon svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.feedback-error-copy{min-width:0;flex:1}
.feedback-error-copy strong{display:block;font-size:10.5px}
.feedback-error-copy p{margin:2px 0 0}
/* Table */
.feedback-table-card{overflow:hidden}
.feedback-table-header{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 16px}
.feedback-table-title{display:flex;align-items:center;gap:10px;min-width:0}
.feedback-table-heading-icon{width:36px;height:36px;flex:0 0 36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--sh-accent-soft);color:var(--sh-accent)}
.feedback-table-heading-icon svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.feedback-table-title h3{margin:0;color:var(--sh-text);font-size:12px;font-weight:800}
.feedback-table-title p{margin:2px 0 0;color:var(--sh-text-3);font-size:8.5px}
.feedback-table-controls{display:flex;align-items:center;gap:10px}
.feedback-filter-indicator{display:inline-flex;align-items:center;gap:6px;color:var(--sh-text-3);font-size:8.5px;font-weight:700}
.feedback-filter-indicator span{width:6px;height:6px;border-radius:999px;background:var(--sh-accent)}
.feedback-table-controls .per-page{display:flex;align-items:center;gap:6px}
.feedback-table-controls .per-page label{color:var(--sh-text-3);font-size:8.5px;font-weight:700}
.feedback-table-controls .per-page select{width:69px;min-height:34px;padding:5px 8px;font-size:9px}
.feedback-table-wrapper{transition:opacity .16s ease}
.feedback-refreshing{opacity:.56;pointer-events:none}
.feedback-table-wrapper table{min-width:980px}
.feedback-table-row{cursor:default}
.feedback-action-heading{text-align:right!important}
.feedback-view-button{min-height:34px;display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:0 10px}
.feedback-view-button svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.feedback-loading{min-height:280px;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
.feedback-loading .spinner{margin:0 0 11px}
.feedback-loading h3{margin:0;color:var(--sh-text);font-size:12px;font-weight:800}
.feedback-loading p{max-width:330px;margin:4px 0 0;color:var(--sh-text-3);font-size:9px;line-height:15px}
.feedback-empty{min-height:280px}
.feedback-empty-reset{margin-top:13px}
.feedback-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#fbfcfd}
.feedback-pagination-summary{display:flex;align-items:center;flex-wrap:wrap;gap:4px;color:var(--sh-text-3);font-size:9px}
.feedback-pagination-summary strong{color:var(--sh-text-2);font-weight:800}
.spinning{animation:feedback-spin .8s linear infinite}
@keyframes feedback-spin{to{transform:rotate(360deg)}}
@media(max-width:1150px){.feedback-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.feedback-intro{flex-direction:column;align-items:flex-start}.feedback-intro-actions{width:100%}.feedback-total-card{flex:1}}
@media(max-width:850px){.feedback-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.feedback-search-field{grid-column:1 / -1}}
@media(max-width:680px){.feedback-intro{padding:17px}.feedback-intro h2{font-size:19px}.feedback-intro-actions{flex-direction:column;align-items:stretch}.feedback-total-card,.feedback-refresh-button{width:100%}.feedback-summary-grid{grid-template-columns:1fr}.feedback-filters{display:flex;flex-direction:column;align-items:stretch;padding:14px}.feedback-filter-actions{width:100%}.feedback-filter-actions button{flex:1}.feedback-filter-heading{align-items:flex-start}.feedback-filter-heading-left{align-items:flex-start}.feedback-table-header{align-items:flex-start;flex-direction:column}.feedback-table-controls{width:100%;justify-content:space-between}.feedback-pagination{flex-direction:column;align-items:stretch}}
</style>
