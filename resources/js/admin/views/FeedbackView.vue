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
    {
        value: 5,
        label: '5 Stars',
    },
    {
        value: 4,
        label: '4 Stars',
    },
    {
        value: 3,
        label: '3 Stars',
    },
    {
        value: 2,
        label: '2 Stars',
    },
    {
        value: 1,
        label: '1 Star',
    },
];

const firstResult = computed(() => {
    if (
        pagination.value.total === 0
    ) {
        return 0;
    }

    return (
        (
            pagination.value.current_page - 1
        )
        * pagination.value.per_page
    ) + 1;
});

const lastResult = computed(() =>
    Math.min(
        pagination.value.total,
        pagination.value.current_page
            * pagination.value.per_page,
    )
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
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZone: 'Asia/Manila',
        },
    ).format(date);
}

function truncateComment(
    value,
    length = 80,
) {
    if (!value) {
        return 'No comment provided.';
    }

    const text =
        String(value);

    if (
        text.length <= length
    ) {
        return text;
    }

    return `${text.slice(0, length)}…`;
}

function buildQuery(
    page,
) {
    const query =
        new URLSearchParams();

    const search =
        filters.search.trim();

    if (search) {
        query.set(
            'search',
            search,
        );
    }

    if (filters.rating) {
        query.set(
            'rating',
            String(filters.rating),
        );
    }

    if (filters.shop_id) {
        query.set(
            'shop_id',
            String(filters.shop_id),
        );
    }

    if (filters.customer_id) {
        query.set(
            'customer_id',
            String(filters.customer_id),
        );
    }

    if (filters.date_from) {
        query.set(
            'date_from',
            filters.date_from,
        );
    }

    if (filters.date_to) {
        query.set(
            'date_to',
            filters.date_to,
        );
    }

    query.set(
        'page',
        String(page),
    );

    query.set(
        'per_page',
        String(filters.per_page),
    );

    return query.toString();
}

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

async function loadFeedback(
    page = 1,
) {
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
                `/admin/feedback?${buildQuery(page)}`,
                {
                    token:
                        auth.token,
                },
            );

        feedbackRecords.value =
            Array.isArray(
                response?.data?.feedback,
            )
                ? response.data.feedback
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
            || error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        errorMessage.value =
            error?.data?.message
            ?? error?.message
            ?? 'Unable to load feedback.';
    } finally {
        isLoading.value =
            false;
    }
}

async function applyFilters() {
    if (
        filters.date_from
        && filters.date_to
        && filters.date_to
            < filters.date_from
    ) {
        errorMessage.value =
            'The end date must be on or after the start date.';

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

async function changePage(
    page,
) {
    if (
        page < 1
        || page
            > pagination.value.last_page
        || page
            === pagination.value.current_page
        || isLoading.value
    ) {
        return;
    }

    await loadFeedback(page);
}

async function openFeedback(
    feedback,
) {
    await router.push({
        name:
            'admin.feedback.detail',

        params: {
            feedbackId:
                feedback.id,
        },
    });
}

onMounted(() => {
    loadFeedback();
});
</script>

<template>
    <div class="feedback-page">
        <section class="page-heading">
            <div>
                <p class="eyebrow">
                    FEEDBACK MONITORING
                </p>

                <h2>
                    Customer Feedback
                </h2>

                <p>
                    Review ratings and comments submitted
                    for completed StyleHub appointments.
                </p>
            </div>
        </section>

        <section class="information-banner">
            <div class="information-icon">
                i
            </div>

            <div>
                <strong>
                    Read-only feedback monitoring
                </strong>

                <p>
                    Administrators can review feedback,
                    but cannot edit or delete customer
                    reviews from this module.
                </p>
            </div>
        </section>

        <section class="filter-card">
            <form
                class="filters"
                @submit.prevent="
                    applyFilters
                "
            >
                <div class="search-field">
                    <label for="feedback-search">
                        Search
                    </label>

                    <div class="input-with-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="11"
                                cy="11"
                                r="7"
                            />

                            <path
                                d="M20 20l-4-4"
                            />
                        </svg>

                        <input
                            id="feedback-search"
                            v-model="
                                filters.search
                            "
                            type="search"
                            maxlength="100"
                            placeholder="Comment, customer or shop"
                        >
                    </div>
                </div>

                <div class="filter-field">
                    <label for="rating">
                        Rating
                    </label>

                    <select
                        id="rating"
                        v-model="
                            filters.rating
                        "
                    >
                        <option value="">
                            All ratings
                        </option>

                        <option
                            v-for="option in ratingOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="feedback-shop">
                        Shop ID
                    </label>

                    <input
                        id="feedback-shop"
                        v-model="
                            filters.shop_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>

                <div class="filter-field">
                    <label for="feedback-customer">
                        Customer ID
                    </label>

                    <input
                        id="feedback-customer"
                        v-model="
                            filters.customer_id
                        "
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                    >
                </div>

                <div class="filter-field">
                    <label for="feedback-from">
                        Date From
                    </label>

                    <input
                        id="feedback-from"
                        v-model="
                            filters.date_from
                        "
                        type="date"
                    >
                </div>

                <div class="filter-field">
                    <label for="feedback-to">
                        Date To
                    </label>

                    <input
                        id="feedback-to"
                        v-model="
                            filters.date_to
                        "
                        type="date"
                    >
                </div>

                <div class="filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="
                            isLoading
                        "
                    >
                        Apply
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="
                            isLoading
                        "
                        @click="
                            resetFilters
                        "
                    >
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <div
            v-if="
                errorMessage
            "
            class="error-message"
            role="alert"
        >
            <div>
                <strong>
                    Unable to load feedback
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                @click="
                    loadFeedback(
                        pagination.current_page
                    )
                "
            >
                Try again
            </button>
        </div>

        <section class="feedback-card">
            <div class="table-header">
                <div>
                    <h3>
                        Feedback Records
                    </h3>

                    <p>
                        {{ pagination.total }}
                        reviews
                    </p>
                </div>

                <div class="per-page">
                    <label for="feedback-per-page">
                        Show
                    </label>

                    <select
                        id="feedback-per-page"
                        v-model.number="
                            filters.per_page
                        "
                        :disabled="
                            isLoading
                        "
                        @change="
                            loadFeedback(1)
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
                </div>
            </div>

            <div
                v-if="
                    isLoading
                    && feedbackRecords.length === 0
                "
                class="loading-state"
            >
                <div class="spinner"></div>

                <p>
                    Loading feedback...
                </p>
            </div>

            <div
                v-else-if="
                    feedbackRecords.length === 0
                "
                class="empty-state"
            >
                <div class="empty-icon">
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

                <h3>
                    No feedback found
                </h3>

                <p>
                    No feedback records match the
                    current filters.
                </p>
            </div>

            <div
                v-else
                class="table-wrapper"
            >
                <table>
                    <thead>
                        <tr>
                            <th>
                                Rating
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Shop
                            </th>

                            <th>
                                Comment
                            </th>

                            <th>
                                Appointment
                            </th>

                            <th>
                                Submitted
                            </th>

                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="feedback in feedbackRecords"
                            :key="feedback.id"
                        >
                            <td>
                                <div class="rating-cell">
                                    <div class="stars">
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
                                        {{ feedback.rating }}/5
                                    </strong>
                                </div>
                            </td>

                            <td>
                                <div class="person-cell">
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
                                            || '—'
                                        }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="shop-cell">
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
                                            || '—'
                                        }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="comment-cell">
                                    {{
                                        truncateComment(
                                            feedback.comment
                                        )
                                    }}
                                </div>
                            </td>

                            <td>
                                <div class="appointment-cell">
                                    <strong>
                                        <template
                                            v-if="
                                                feedback.appointment
                                                    ?.id
                                            "
                                        >
                                            #{{
                                                feedback.appointment.id
                                            }}
                                        </template>

                                        <template v-else>
                                            —
                                        </template>
                                    </strong>

                                    <span>
                                        {{
                                            feedback.appointment
                                                ?.appointment_date
                                            || '—'
                                        }}
                                    </span>
                                </div>
                            </td>

                            <td>
                                {{
                                    formatDateTime(
                                        feedback.created_at
                                    )
                                }}
                            </td>

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button"
                                    @click="
                                        openFeedback(
                                            feedback
                                        )
                                    "
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    pagination.total > 0
                "
                class="pagination"
            >
                <p>
                    Showing

                    <strong>
                        {{ firstResult }}
                    </strong>

                    –

                    <strong>
                        {{ lastResult }}
                    </strong>

                    of

                    <strong>
                        {{ pagination.total }}
                    </strong>
                </p>

                <div class="pagination-buttons">
                    <button
                        type="button"
                        :disabled="
                            pagination.current_page <= 1
                            || isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page - 1
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        :class="{
                            active:
                                page
                                === pagination.current_page,
                        }"
                        :disabled="
                            isLoading
                        "
                        @click="
                            changePage(page)
                        "
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        :disabled="
                            pagination.current_page
                                >= pagination.last_page
                            || isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page + 1
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped src="./admin-page.css"></style>