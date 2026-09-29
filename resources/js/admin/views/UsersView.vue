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

const router =
    useRouter();

const auth =
    useAuthStore();

const users =
    ref([]);

const isLoading =
    ref(false);

const errorMessage =
    ref('');

const pagination =
    ref({
        current_page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
        has_more_pages: false,
    });

const filters =
    reactive({
        search: '',
        role: '',
        status: '',
        per_page: 20,
    });


/*
|--------------------------------------------------------------------------
| FILTER OPTIONS
|--------------------------------------------------------------------------
*/

const roleOptions = [
    {
        value:
            'customer',

        label:
            'Customer',
    },

    {
        value:
            'shop_owner',

        label:
            'Shop Owner',
    },
];

const statusOptions = [
    {
        value:
            'active',

        label:
            'Active',
    },

    {
        value:
            'suspended',

        label:
            'Suspended',
    },
];

const numberFormatter =
    new Intl.NumberFormat(
        'en-PH',
    );


/*
|--------------------------------------------------------------------------
| FORMATTING
|--------------------------------------------------------------------------
*/

function formatNumber(
    value,
) {
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
                character
                    .toUpperCase(),
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
            value,
        );

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return '—';
    }

    return new Intl
        .DateTimeFormat(
            'en-PH',
            {
                year:
                    'numeric',

                month:
                    'short',

                day:
                    'numeric',

                timeZone:
                    'Asia/Manila',
            },
        )
        .format(
            date,
        );
}

function userInitial(
    user,
) {
    const name =
        user?.first_name
        || user?.full_name
        || user?.email
        || 'U';

    return String(
        name,
    )
        .charAt(0)
        .toUpperCase();
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


/*
|--------------------------------------------------------------------------
| ACTIVE FILTER COUNT
|--------------------------------------------------------------------------
*/

const activeFiltersCount =
    computed(
        () => {
            let count = 0;

            if (
                filters.search
                    .trim()
            ) {
                count += 1;
            }

            if (
                filters.role
            ) {
                count += 1;
            }

            if (
                filters.status
            ) {
                count += 1;
            }

            return count;
        },
    );


/*
|--------------------------------------------------------------------------
| CURRENT PAGE SUMMARY
|--------------------------------------------------------------------------
|
| These numbers represent only the users loaded on the current page.
|
*/

const currentPageSummary =
    computed(
        () => {
            const summary = {
                customers: 0,
                shopOwners: 0,
                active: 0,
                suspended: 0,
                verified: 0,
            };

            for (
                const user
                of users.value
            ) {
                if (
                    user.role
                    === 'customer'
                ) {
                    summary.customers += 1;
                }

                if (
                    user.role
                    === 'shop_owner'
                ) {
                    summary.shopOwners += 1;
                }

                if (
                    user.status
                    === 'active'
                ) {
                    summary.active += 1;
                }

                if (
                    user.status
                    === 'suspended'
                ) {
                    summary.suspended += 1;
                }

                if (
                    user.email_verified
                ) {
                    summary.verified += 1;
                }
            }

            return summary;
        },
    );


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const firstResult =
    computed(
        () => {
            if (
                pagination.value.total
                === 0
            ) {
                return 0;
            }

            return (
                (
                    pagination.value
                        .current_page
                    - 1
                )
                *
                pagination.value
                    .per_page
            )
            + 1;
        },
    );

const lastResult =
    computed(
        () =>
            Math.min(
                pagination.value.total,

                pagination.value
                    .current_page
                *
                pagination.value
                    .per_page,
            ),
    );

const visiblePages =
    computed(
        () => {
            const current =
                pagination.value
                    .current_page;

            const last =
                pagination.value
                    .last_page;

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
                pages.push(
                    page,
                );
            }

            return pages;
        },
    );


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

function buildQuery(
    page,
) {
    const query =
        new URLSearchParams();

    const search =
        filters.search
            .trim();

    if (
        search
    ) {
        query.set(
            'search',
            search,
        );
    }

    if (
        filters.role
    ) {
        query.set(
            'role',
            filters.role,
        );
    }

    if (
        filters.status
    ) {
        query.set(
            'status',
            filters.status,
        );
    }

    query.set(
        'page',
        String(
            page,
        ),
    );

    query.set(
        'per_page',
        String(
            filters.per_page,
        ),
    );

    return query
        .toString();
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

async function handleAuthenticationFailure() {
    auth.clearSession();

    await router.replace({
        name:
            'admin.login',
    });
}


/*
|--------------------------------------------------------------------------
| LOAD USERS
|--------------------------------------------------------------------------
*/

async function loadUsers(
    page = 1,
) {
    if (
        !auth.token
    ) {
        await handleAuthenticationFailure();

        return;
    }

    isLoading.value =
        true;

    errorMessage.value =
        '';

    try {
        const query =
            buildQuery(
                page,
            );

        const response =
            await apiRequest(
                `/admin/users?${query}`,
                {
                    token:
                        auth.token,
                },
            );

        users.value =
            Array.isArray(
                response
                    ?.data
                    ?.users,
            )
                ? response
                    .data
                    .users
                : [];

        pagination.value = {
            current_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.current_page
                    ?? 1,
                ),

            per_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.per_page
                    ?? filters.per_page,
                ),

            total:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.total
                    ?? 0,
                ),

            last_page:
                Number(
                    response
                        ?.data
                        ?.pagination
                        ?.last_page
                    ?? 1,
                ),

            has_more_pages:
                Boolean(
                    response
                        ?.data
                        ?.pagination
                        ?.has_more_pages,
                ),
        };
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
            await handleAuthenticationFailure();

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
            'Unable to load users.';
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| FILTER ACTIONS
|--------------------------------------------------------------------------
*/

async function applyFilters() {
    await loadUsers(
        1,
    );
}

async function resetFilters() {
    filters.search =
        '';

    filters.role =
        '';

    filters.status =
        '';

    filters.per_page =
        20;

    await loadUsers(
        1,
    );
}


/*
|--------------------------------------------------------------------------
| PAGINATION ACTION
|--------------------------------------------------------------------------
*/

async function changePage(
    page,
) {
    if (
        page < 1
        ||
        page
            >
            pagination.value
                .last_page
        ||
        page
            ===
            pagination.value
                .current_page
        ||
        isLoading.value
    ) {
        return;
    }

    await loadUsers(
        page,
    );
}


/*
|--------------------------------------------------------------------------
| OPEN USER
|--------------------------------------------------------------------------
*/

async function openUser(
    user,
) {
    await router.push({
        name:
            'admin.user.detail',

        params: {
            userId:
                user.id,
        },
    });
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(
    () => {
        loadUsers();
    },
);
</script>


<template>
    <div class="users-page">
        <!-- ============================================================
             PAGE INTRODUCTION
             ============================================================ -->

        <section class="users-intro">
            <div class="users-intro-copy">
                <div class="users-intro-label">
                    <span class="users-intro-dot" />

                    Account directory
                </div>

                <h2>
                    Manage user accounts
                </h2>

                <p>
                    Review Customer and Shop Owner accounts,
                    monitor account status, verification,
                    connected shops, and platform activity.
                </p>
            </div>

            <div class="users-intro-actions">
                <div class="users-total-card">
                    <div class="users-total-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                            />

                            <path
                                d="M3 20c0-4 2-7 6-7s6 3 6 7"
                            />

                            <circle
                                cx="17"
                                cy="9"
                                r="2.5"
                            />

                            <path
                                d="M15 14c4 0 6 2 6 6"
                            />
                        </svg>
                    </div>

                    <div>
                        <span>
                            Managed users
                        </span>

                        <strong>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}
                        </strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button users-refresh-button"
                    :disabled="isLoading"
                    @click="
                        loadUsers(
                            pagination.current_page
                        )
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
                        {{
                            isLoading
                                ? 'Refreshing'
                                : 'Refresh'
                        }}
                    </span>
                </button>
            </div>
        </section>


        <!-- ============================================================
             CURRENT PAGE SUMMARY
             ============================================================ -->

        <section
            v-if="
                users.length > 0
            "
            class="user-summary-grid"
        >
            <!-- CUSTOMERS -->

            <article class="user-summary-card">
                <div class="user-summary-icon customer-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            cx="9"
                            cy="8"
                            r="3"
                        />

                        <path
                            d="M3 20c0-4 2-7 6-7s6 3 6 7"
                        />
                    </svg>
                </div>

                <div class="user-summary-content">
                    <span>
                        Customers
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.customers
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- SHOP OWNERS -->

            <article class="user-summary-card">
                <div class="user-summary-icon owner-icon">
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

                        <path
                            d="M18 4v4M16 6h4"
                        />
                    </svg>
                </div>

                <div class="user-summary-content">
                    <span>
                        Shop Owners
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.shopOwners
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- ACTIVE -->

            <article class="user-summary-card">
                <div class="user-summary-icon active-icon">
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

                <div class="user-summary-content">
                    <span>
                        Active
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.active
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- SUSPENDED -->

            <article class="user-summary-card">
                <div class="user-summary-icon suspended-icon">
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

                <div class="user-summary-content">
                    <span>
                        Suspended
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.suspended
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>


            <!-- VERIFIED -->

            <article class="user-summary-card verification-summary">
                <div class="user-summary-icon verified-icon">
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
                            d="M16 17l2 2 4-4"
                        />
                    </svg>
                </div>

                <div class="user-summary-content">
                    <span>
                        Email Verified
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                currentPageSummary.verified
                            )
                        }}
                    </strong>

                    <small>
                        On this page
                    </small>
                </div>
            </article>
        </section>


        <!-- ============================================================
             FILTERS
             ============================================================ -->

        <section class="filter-card users-filter-card">
            <div class="filter-card-heading">
                <div class="filter-heading-left">
                    <div class="filter-heading-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6h16M7 12h10M10 18h4"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3>
                            Filter accounts
                        </h3>

                        <p>
                            Search users and narrow the account
                            directory by role or account status.
                        </p>
                    </div>
                </div>

                <span
                    v-if="
                        activeFiltersCount > 0
                    "
                    class="active-filter-count"
                >
                    {{
                        activeFiltersCount
                    }}
                    active
                </span>
            </div>


            <form
                class="filters users-filters"
                @submit.prevent="
                    applyFilters
                "
            >
                <!-- SEARCH -->

                <div class="search-field">
                    <label for="user-search">
                        Search users
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
                            id="user-search"
                            v-model="
                                filters.search
                            "
                            type="search"
                            maxlength="100"
                            placeholder="Search by name, email or phone"
                        >
                    </div>
                </div>


                <!-- ROLE -->

                <div class="filter-field">
                    <label for="user-role">
                        Account role
                    </label>

                    <select
                        id="user-role"
                        v-model="
                            filters.role
                        "
                    >
                        <option value="">
                            All roles
                        </option>

                        <option
                            v-for="
                                option
                                in roleOptions
                            "
                            :key="
                                option.value
                            "
                            :value="
                                option.value
                            "
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>


                <!-- STATUS -->

                <div class="filter-field">
                    <label for="user-status">
                        Account status
                    </label>

                    <select
                        id="user-status"
                        v-model="
                            filters.status
                        "
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="
                                option
                                in statusOptions
                            "
                            :key="
                                option.value
                            "
                            :value="
                                option.value
                            "
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>


                <!-- ACTIONS -->

                <div class="filter-actions users-filter-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="
                            isLoading
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6h16M7 12h10M10 18h4"
                            />
                        </svg>

                        <span>
                            Apply filters
                        </span>
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        :disabled="
                            isLoading
                            ||
                            activeFiltersCount
                            === 0
                        "
                        @click="
                            resetFilters
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

                        <span>
                            Reset
                        </span>
                    </button>
                </div>
            </form>
        </section>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                errorMessage
            "
            class="error-message users-error"
            role="alert"
        >
            <div class="users-error-icon">
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

            <div class="users-error-copy">
                <strong>
                    Unable to load users
                </strong>

                <p>
                    {{ errorMessage }}
                </p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="
                    isLoading
                "
                @click="
                    loadUsers(
                        pagination.current_page
                    )
                "
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             USERS TABLE
             ============================================================ -->

        <section class="users-card">
            <!-- TABLE HEADER -->

            <div class="table-header users-table-header">
                <div class="table-heading">
                    <div class="table-heading-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                            />

                            <path
                                d="M3 20c0-4 2-7 6-7s6 3 6 7"
                            />

                            <circle
                                cx="17"
                                cy="9"
                                r="2.5"
                            />

                            <path
                                d="M15 14c4 0 6 2 6 6"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3>
                            Account directory
                        </h3>

                        <p>
                            {{
                                formatNumber(
                                    pagination.total
                                )
                            }}

                            managed user{{
                                pagination.total === 1
                                    ? ''
                                    : 's'
                            }}
                        </p>
                    </div>
                </div>


                <div class="table-header-actions">
                    <div
                        v-if="
                            activeFiltersCount > 0
                        "
                        class="table-filter-status"
                    >
                        <span class="table-filter-dot" />

                        Filtered
                    </div>

                    <div class="per-page">
                        <label for="users-per-page">
                            Rows
                        </label>

                        <select
                            id="users-per-page"
                            v-model.number="
                                filters.per_page
                            "
                            :disabled="
                                isLoading
                            "
                            @change="
                                loadUsers(
                                    1
                                )
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
            </div>


            <!-- LOADING -->

            <div
                v-if="
                    isLoading
                    &&
                    users.length === 0
                "
                class="users-loading-state"
            >
                <div class="users-loading-icon">
                    <div class="spinner" />
                </div>

                <h3>
                    Loading account directory
                </h3>

                <p>
                    Retrieving Customer and Shop Owner
                    account information.
                </p>
            </div>


            <!-- EMPTY -->

            <div
                v-else-if="
                    users.length === 0
                "
                class="empty-state users-empty-state"
            >
                <div class="empty-icon">
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <circle
                            cx="9"
                            cy="8"
                            r="3"
                        />

                        <path
                            d="M3 20c0-4 2-7 6-7s6 3 6 7"
                        />

                        <circle
                            cx="17"
                            cy="9"
                            r="2.5"
                        />

                        <path
                            d="M15 14c4 0 6 2 6 6"
                        />
                    </svg>
                </div>

                <h3>
                    No users found
                </h3>

                <p>
                    No Customer or Shop Owner accounts
                    match your current search or filters.
                </p>

                <button
                    v-if="
                        activeFiltersCount > 0
                    "
                    type="button"
                    class="secondary-button empty-reset-button"
                    :disabled="
                        isLoading
                    "
                    @click="
                        resetFilters
                    "
                >
                    Clear filters
                </button>
            </div>


            <!-- TABLE -->

            <div
                v-else
                class="table-wrapper users-table-wrapper"
                :class="{
                    'table-refreshing':
                        isLoading,
                }"
            >
                <table>
                    <thead>
                        <tr>
                            <th>
                                User
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Contact
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Shop
                            </th>

                            <th>
                                Appointments
                            </th>

                            <th>
                                Registered
                            </th>

                            <th class="table-actions-heading">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="
                                user
                                in users
                            "
                            :key="
                                user.id
                            "
                            class="user-table-row"
                            @dblclick="
                                openUser(
                                    user
                                )
                            "
                        >
                            <!-- USER -->

                            <td>
                                <div class="user-cell">
                                    <div
                                        class="user-avatar"
                                        :class="
                                            statusClass(
                                                user.role
                                            )
                                        "
                                    >
                                        {{
                                            userInitial(
                                                user
                                            )
                                        }}
                                    </div>

                                    <div class="user-primary-copy">
                                        <strong>
                                            {{
                                                user.full_name
                                                ||
                                                'Unnamed User'
                                            }}
                                        </strong>

                                        <div class="user-secondary-line">
                                            <span class="user-email">
                                                {{
                                                    user.email
                                                    ||
                                                    'No email'
                                                }}
                                            </span>

                                            <span
                                                v-if="
                                                    user.id
                                                "
                                                class="user-id"
                                            >
                                                ID #{{ user.id }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>


                            <!-- ROLE -->

                            <td>
                                <span
                                    class="role-badge user-role-badge"
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
                            </td>


                            <!-- CONTACT -->

                            <td>
                                <div class="contact-cell">
                                    <strong>
                                        {{
                                            user.phone_number
                                            ||
                                            'No phone'
                                        }}
                                    </strong>

                                    <span
                                        class="verification-state"
                                        :class="{
                                            verified:
                                                user.email_verified,

                                            unverified:
                                                !user.email_verified,
                                        }"
                                    >
                                        <svg
                                            v-if="
                                                user.email_verified
                                            "
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
                                                d="M12 8v5M12 17h.01"
                                            />
                                        </svg>

                                        {{
                                            user.email_verified
                                                ? 'Email verified'
                                                : 'Not verified'
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- STATUS -->

                            <td>
                                <span
                                    class="status-badge user-status-badge"
                                    :class="
                                        statusClass(
                                            user.status
                                        )
                                    "
                                >
                                    <span class="badge-dot" />

                                    {{
                                        humanize(
                                            user.status
                                        )
                                    }}
                                </span>
                            </td>


                            <!-- SHOP -->

                            <td>
                                <div class="shop-cell user-shop-cell">
                                    <template
                                        v-if="
                                            user.shop
                                        "
                                    >
                                        <div class="mini-shop-icon">
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
                                        </div>

                                        <div>
                                            <strong>
                                                {{
                                                    user.shop.name
                                                    ||
                                                    'Unnamed shop'
                                                }}
                                            </strong>

                                            <span
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
                                    </template>

                                    <template
                                        v-else
                                    >
                                        <span class="no-shop">
                                            —
                                        </span>
                                    </template>
                                </div>
                            </td>


                            <!-- APPOINTMENTS -->

                            <td>
                                <div class="appointment-count">
                                    <span class="appointment-count-icon">
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
                                    </span>

                                    <strong>
                                        {{
                                            formatNumber(
                                                user.appointments_count
                                                ?? 0
                                            )
                                        }}
                                    </strong>
                                </div>
                            </td>


                            <!-- REGISTERED -->

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
                                        {{
                                            formatDate(
                                                user.created_at
                                            )
                                        }}
                                    </span>
                                </div>
                            </td>


                            <!-- ACTION -->

                            <td class="actions-cell">
                                <button
                                    type="button"
                                    class="view-button user-view-button"
                                    @click="
                                        openUser(
                                            user
                                        )
                                    "
                                >
                                    <span>
                                        View
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>


            <!-- ========================================================
                 PAGINATION
                 ======================================================== -->

            <div
                v-if="
                    pagination.total > 0
                "
                class="pagination users-pagination"
            >
                <div class="pagination-summary">
                    <span>
                        Showing
                    </span>

                    <strong>
                        {{ firstResult }}
                    </strong>

                    <span>
                        –
                    </span>

                    <strong>
                        {{ lastResult }}
                    </strong>

                    <span>
                        of
                    </span>

                    <strong>
                        {{
                            formatNumber(
                                pagination.total
                            )
                        }}
                    </strong>

                    <span>
                        users
                    </span>
                </div>


                <div class="pagination-buttons">
                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        :disabled="
                            pagination.current_page
                            <= 1
                            ||
                            isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page
                                - 1
                            )
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

                        <span>
                            Previous
                        </span>
                    </button>


                    <button
                        v-for="
                            page
                            in visiblePages
                        "
                        :key="
                            page
                        "
                        type="button"
                        class="page-button"
                        :class="{
                            active:
                                page
                                ===
                                pagination.current_page,
                        }"
                        :disabled="
                            isLoading
                        "
                        @click="
                            changePage(
                                page
                            )
                        "
                    >
                        {{ page }}
                    </button>


                    <button
                        type="button"
                        class="page-button pagination-arrow"
                        :disabled="
                            pagination.current_page
                            >=
                            pagination.last_page
                            ||
                            isLoading
                        "
                        @click="
                            changePage(
                                pagination.current_page
                                + 1
                            )
                        "
                    >
                        <span>
                            Next
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
            </div>
        </section>
    </div>
</template>


<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.users-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRODUCTION
|--------------------------------------------------------------------------
*/

.users-intro {
    min-height: 126px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    padding:
        20px
        22px;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        var(--sh-radius-xl);

    background:
        radial-gradient(
            circle at 94% 10%,
            rgba(
                214,
                154,
                45,
                0.07
            ),
            transparent 26%
        ),
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.users-intro-copy {
    min-width: 0;

    flex: 1;
}

.users-intro-label {
    width: fit-content;

    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8.5px;
    font-weight: 800;

    letter-spacing: 1.1px;

    text-transform:
        uppercase;
}

.users-intro-dot {
    width: 6px;
    height: 6px;

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

.users-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 22px;
    font-weight: 800;

    line-height: 29px;

    letter-spacing:
        -0.55px;
}

.users-intro p {
    max-width: 670px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}

.users-intro-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| TOTAL USER CARD
|--------------------------------------------------------------------------
*/

.users-total-card {
    min-width: 168px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding:
        10px
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 12px;

    background:
        var(--sh-surface-muted);
}

.users-total-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.users-total-icon svg {
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

.users-total-card > div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.users-total-card span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 600;
}

.users-total-card strong {
    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;

    line-height: 21px;
}

.users-refresh-button {
    min-height: 48px;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.user-summary-grid {
    display: grid;

    grid-template-columns:
        repeat(
            5,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.user-summary-card {
    min-width: 0;

    min-height: 82px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding:
        12px
        13px;

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

.user-summary-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.user-summary-icon svg {
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

.customer-icon {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.owner-icon {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.active-icon,
.verified-icon {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.suspended-icon {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.user-summary-content {
    min-width: 0;

    flex: 1;

    display: grid;

    grid-template-columns:
        minmax(
            0,
            1fr
        )
        auto;

    align-items: center;

    column-gap: 8px;
}

.user-summary-content span {
    grid-column: 1;

    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.user-summary-content strong {
    grid-column: 2;
    grid-row:
        1
        / 3;

    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;
}

.user-summary-content small {
    grid-column: 1;

    color:
        #a2a9b3;

    font-size: 7.5px;
}


/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.users-filter-card {
    padding: 0;
}

.filter-card-heading {
    min-height: 66px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    padding:
        13px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);
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

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-2);
}

.filter-heading-icon svg {
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

.filter-heading-left h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.filter-heading-left p {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
}

.active-filter-count {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border:
        1px solid
        #eadfbf;

    border-radius:
        999px;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;

    font-size: 8px;
    font-weight: 800;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| FILTER FORM
|--------------------------------------------------------------------------
*/

.users-filters {
    display: grid;

    grid-template-columns:
        minmax(
            260px,
            2fr
        )
        minmax(
            150px,
            0.8fr
        )
        minmax(
            150px,
            0.8fr
        )
        auto;

    align-items: end;

    gap: 11px;

    padding:
        15px
        16px
        16px;
}

.users-filter-actions {
    align-items: center;

    flex-wrap: nowrap;
}

.users-filter-actions
.primary-button,
.users-filter-actions
.secondary-button {
    min-height: 42px;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.users-error {
    align-items: center;

    margin: 0;
}

.users-error-icon {
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

.users-error-icon svg {
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

.users-error-copy {
    min-width: 0;

    flex: 1;
}

.users-error-copy strong {
    display: block;

    font-size: 10.5px;
}

.users-error-copy p {
    margin:
        2px
        0
        0;
}


/*
|--------------------------------------------------------------------------
| TABLE HEADER
|--------------------------------------------------------------------------
*/

.users-table-header {
    padding:
        13px
        16px;
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

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.table-heading-icon svg {
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

.table-header-actions {
    display: flex;
    align-items: center;

    gap: 10px;
}

.table-filter-status {
    display: inline-flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.table-filter-dot {
    width: 6px;
    height: 6px;

    border-radius: 999px;

    background:
        var(--sh-accent);
}

.per-page {
    display: flex;
    align-items: center;

    gap: 6px;
}

.per-page label {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.per-page select {
    width: 69px;

    min-height: 34px;

    padding:
        5px
        8px;

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.users-table-wrapper {
    transition:
        opacity
        0.16s ease;
}

.table-refreshing {
    opacity: 0.55;

    pointer-events: none;
}

.users-table-wrapper table {
    min-width: 1180px;
}

.user-table-row {
    cursor: default;
}


/*
|--------------------------------------------------------------------------
| USER CELL
|--------------------------------------------------------------------------
*/

.user-primary-copy {
    min-width: 0;
}

.user-secondary-line {
    max-width: 240px;

    display: flex;
    align-items: center;

    gap: 6px;

    margin-top: 2px;
}

.user-email {
    min-width: 0;

    overflow: hidden;

    margin: 0 !important;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.user-id {
    flex: 0 0 auto;

    margin: 0 !important;

    padding:
        1px
        5px;

    border-radius: 5px;

    background:
        #f2f4f7;

    color:
        #969da7 !important;

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size:
        7.5px !important;
}


/*
|--------------------------------------------------------------------------
| USER AVATAR
|--------------------------------------------------------------------------
*/

.user-avatar.customer {
    border-color:
        #dce6f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.user-avatar.shop-owner {
    border-color:
        #e5def8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}


/*
|--------------------------------------------------------------------------
| ROLE BADGE
|--------------------------------------------------------------------------
*/

.user-role-badge {
    gap: 6px;
}

.user-role-badge svg {
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

.user-role-badge.customer {
    border-color:
        #dce6f8;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.user-role-badge.shop-owner {
    border-color:
        #e4ddf8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}


/*
|--------------------------------------------------------------------------
| CONTACT / VERIFICATION
|--------------------------------------------------------------------------
*/

.verification-state {
    width: fit-content;

    display: inline-flex !important;
    align-items: center;

    gap: 4px;

    margin-top: 4px !important;

    font-size:
        7.8px !important;

    font-weight: 700;
}

.verification-state svg {
    width: 11px;
    height: 11px;

    flex: 0 0 11px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.verification-state.verified {
    color:
        var(--sh-success) !important;
}

.verification-state.unverified {
    color:
        var(--sh-warning) !important;
}


/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

.user-status-badge {
    gap: 5px;
}

.badge-dot {
    width: 5px;
    height: 5px;

    flex: 0 0 5px;

    border-radius: 999px;

    background:
        currentColor;
}


/*
|--------------------------------------------------------------------------
| SHOP CELL
|--------------------------------------------------------------------------
*/

.user-shop-cell {
    display: flex;
    align-items: center;

    gap: 8px;
}

.user-shop-cell > div:last-child {
    min-width: 0;
}

.mini-shop-icon {
    width: 31px;
    height: 31px;

    flex: 0 0 31px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.mini-shop-icon svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.user-shop-cell
.approved {
    color:
        var(--sh-success) !important;
}

.user-shop-cell
.pending {
    color:
        var(--sh-warning) !important;
}

.user-shop-cell
.rejected {
    color:
        var(--sh-danger) !important;
}

.no-shop {
    color:
        #b0b6be !important;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT COUNT
|--------------------------------------------------------------------------
*/

.appointment-count {
    display: inline-flex;
    align-items: center;

    gap: 7px;
}

.appointment-count-icon {
    width: 28px;
    height: 28px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.appointment-count-icon svg {
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

.appointment-count strong {
    color:
        var(--sh-text);

    font-size: 10px;
    font-weight: 800;
}


/*
|--------------------------------------------------------------------------
| REGISTERED
|--------------------------------------------------------------------------
*/

.registered-cell {
    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-2);

    white-space: nowrap;
}

.registered-cell svg {
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


/*
|--------------------------------------------------------------------------
| VIEW BUTTON
|--------------------------------------------------------------------------
*/

.table-actions-heading {
    text-align:
        right !important;
}

.user-view-button {
    min-height: 34px;

    padding:
        0
        10px;
}

.user-view-button svg {
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
| LOADING
|--------------------------------------------------------------------------
*/

.users-loading-state {
    min-height: 280px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;
}

.users-loading-icon {
    width: 52px;
    height: 52px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 11px;

    border-radius: 15px;

    background:
        var(--sh-surface-muted);
}

.users-loading-icon
.spinner {
    margin: 0;
}

.users-loading-state h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.users-loading-state p {
    max-width: 340px;

    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.users-empty-state {
    min-height: 280px;
}

.empty-reset-button {
    margin-top: 13px;
}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

.users-pagination {
    background:
        #fbfcfd;
}

.pagination-summary {
    display: flex;
    align-items: center;

    gap: 4px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.pagination-summary strong {
    color:
        var(--sh-text-2);

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
| REFRESH ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        users-spin
        0.8s
        linear
        infinite;
}

@keyframes users-spin {
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

@media (max-width: 1350px) {
    .user-summary-grid {
        grid-template-columns:
            repeat(
                3,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .users-filters {
        grid-template-columns:
            minmax(
                230px,
                1.8fr
            )
            repeat(
                2,
                minmax(
                    140px,
                    1fr
                )
            );

        row-gap: 12px;
    }

    .users-filter-actions {
        grid-column:
            1
            / -1;
    }
}


@media (max-width: 1000px) {
    .users-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .users-intro-actions {
        width: 100%;
    }

    .users-total-card {
        flex: 1;
    }

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

    .verification-summary {
        grid-column:
            span 2;
    }
}


@media (max-width: 760px) {
    .users-filters {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .users-filters
    .search-field {
        grid-column:
            1
            / -1;
    }

    .users-filter-actions {
        grid-column:
            1
            / -1;
    }

    .users-table-header {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .table-header-actions {
        width: 100%;

        justify-content:
            space-between;
    }
}


@media (max-width: 680px) {
    .users-intro {
        padding: 17px;
    }

    .users-intro h2 {
        font-size: 19px;
    }

    .users-intro-actions {
        align-items: stretch;

        flex-direction: column;
    }

    .users-total-card,
    .users-refresh-button {
        width: 100%;
    }

    .user-summary-grid {
        grid-template-columns:
            1fr;
    }

    .verification-summary {
        grid-column: auto;
    }

    .filter-card-heading {
        align-items:
            flex-start;
    }

    .filter-heading-left {
        align-items:
            flex-start;
    }

    .users-filters {
        display: flex;

        align-items: stretch;
        flex-direction: column;

        padding: 14px;
    }

    .users-filter-actions {
        width: 100%;

        align-items: stretch;
        flex-direction: column;
    }

    .users-filter-actions
    .primary-button,
    .users-filter-actions
    .secondary-button {
        width: 100%;
    }

    .users-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .users-error
    .retry-button {
        width: 100%;
    }

    .users-pagination {
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