<script setup>
import {
    computed,
    ref,
} from 'vue';

import {
    useRoute,
    useRouter,
} from 'vue-router';

import { useAuthStore } from '../stores/auth';

const props = defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const emit = defineEmits([
    'toggle-sidebar',
]);

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const isLoggingOut = ref(false);

const administratorEmail =
    computed(
        () =>
            auth.administratorEmail
            || 'Administrator',
    );

const administratorName =
    computed(
        () =>
            auth.administratorName
            || 'Administrator',
    );

const administratorInitials =
    computed(
        () =>
            auth.administratorInitials
            || 'A',
    );

const sectionLabel =
    computed(() => {
        const name =
            String(
                route.name
                ?? '',
            );

        if (
            name.includes(
                'shop',
            )
            ||
            name.includes(
                'user',
            )
            ||
            name.includes(
                'appointment',
            )
            ||
            name.includes(
                'feedback',
            )
        ) {
            return 'Management';
        }

        if (
            name.includes(
                'report',
            )
            ||
            name.includes(
                'sms',
            )
            ||
            name.includes(
                'audit',
            )
        ) {
            return 'Operations';
        }

        if (
            name.includes(
                'settings',
            )
        ) {
            return 'System';
        }

        return 'Overview';
    });

const pageDescription =
    computed(() => {
        const name =
            route.name;

        switch (name) {
            case 'admin.dashboard':
                return 'Monitor StyleHub activity, platform performance, and administrator operations.';

            case 'admin.shops':
                return 'Review and manage registered shops across the StyleHub platform.';

            case 'admin.shop.detail':
                return 'Review detailed shop information, status, and platform activity.';

            case 'admin.users':
                return 'Manage customers, shop owners, and administrator account activity.';

            case 'admin.user.detail':
                return 'Review account information, status, and user activity.';

            case 'admin.appointments':
                return 'Monitor customer bookings and appointment activity across all shops.';

            case 'admin.appointment.detail':
                return 'Review complete appointment information and booking status.';

            case 'admin.feedback':
                return 'Monitor customer reviews and feedback submitted across StyleHub.';

            case 'admin.feedback.detail':
                return 'Review the complete details of this customer feedback entry.';

            case 'admin.reports':
                return 'Analyze platform activity and operational performance through reports.';

            case 'admin.sms-monitoring':
                return 'Monitor SMS delivery activity, statuses, and notification processing.';

            case 'admin.sms-notification.detail':
                return 'Review the complete details and delivery status of this SMS notification.';

            case 'admin.audit-logs':
                return 'Track administrator actions and important activity across the platform.';

            case 'admin.audit-log.detail':
                return 'Review the complete details of this administrator audit event.';

            case 'admin.system-settings':
                return 'Configure platform-wide administrative and operational settings.';

            case 'admin.account-settings':
                return 'Manage your administrator profile, security, and account preferences.';

            default:
                return 'StyleHub administrator management console.';
        }
    });

async function openAccountSettings() {
    if (
        route.name
        === 'admin.account-settings'
    ) {
        return;
    }

    await router.push({
        name:
            'admin.account-settings',
    });
}

async function logout() {
    if (
        isLoggingOut.value
    ) {
        return;
    }

    isLoggingOut.value =
        true;

    try {
        await auth.logout();
    } catch {
        /*
         * The authentication store clears the
         * local session even if the API request
         * itself fails.
         */
    } finally {
        isLoggingOut.value =
            false;

        await router.replace({
            name:
                'admin.login',
        });
    }
}
</script>

<template>
    <header class="admin-header">
        <!-- ============================================================
             LEFT SIDE
             ============================================================ -->

        <div class="header-left">
            <button
                type="button"
                class="menu-button"
                aria-label="Open navigation"
                @click="
                    emit(
                        'toggle-sidebar'
                    )
                "
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M4 7h16M4 12h16M4 17h16"
                    />
                </svg>
            </button>

            <div class="page-heading">
                <div class="breadcrumb-row">
                    <span class="breadcrumb-home">
                        Admin
                    </span>

                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        class="breadcrumb-arrow"
                    >
                        <path
                            d="M9 18l6-6-6-6"
                        />
                    </svg>

                    <span class="breadcrumb-section">
                        {{ sectionLabel }}
                    </span>
                </div>

                <div class="title-row">
                    <h1>
                        {{ props.title }}
                    </h1>

                    <span class="page-indicator">
                        Live
                    </span>
                </div>

                <p class="page-description">
                    {{ pageDescription }}
                </p>
            </div>
        </div>


        <!-- ============================================================
             RIGHT SIDE
             ============================================================ -->

        <div class="header-actions">
            <!-- SYSTEM STATUS -->

            <div class="system-status">
                <span class="status-dot" />

                <div class="status-copy">
                    <span class="status-label">
                        System
                    </span>

                    <strong>
                        Operational
                    </strong>
                </div>
            </div>


            <!-- DIVIDER -->

            <div class="header-divider" />


            <!-- ADMIN PROFILE -->

            <button
                type="button"
                class="administrator"
                title="Open Account Settings"
                @click="
                    openAccountSettings
                "
            >
                <div class="administrator-avatar">
                    {{
                        administratorInitials
                    }}
                </div>

                <div class="administrator-copy">
                    <strong>
                        {{
                            administratorName
                        }}
                    </strong>

                    <span>
                        {{
                            administratorEmail
                        }}
                    </span>
                </div>

                <svg
                    class="profile-chevron"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M9 18l6-6-6-6"
                    />
                </svg>
            </button>


            <!-- SIGN OUT -->

            <button
                type="button"
                class="logout-button"
                :disabled="
                    isLoggingOut
                "
                @click="
                    logout
                "
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        d="M10 5H5v14h5"
                    />

                    <path
                        d="M14 8l4 4-4 4M18 12H9"
                    />
                </svg>

                <span>
                    {{
                        isLoggingOut
                            ? 'Signing out...'
                            : 'Sign out'
                    }}
                </span>
            </button>
        </div>
    </header>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.admin-header {
    position: sticky;

    z-index: 30;

    top: 0;

    min-height: 94px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    padding:
        16px
        28px;

    border-bottom:
        1px solid
        var(--sh-border);

    background:
        rgba(
            255,
            255,
            255,
            0.94
        );

    backdrop-filter:
        blur(
            18px
        );

    -webkit-backdrop-filter:
        blur(
            18px
        );

    box-shadow:
        0 1px 0
        rgba(
            15,
            23,
            42,
            0.015
        );
}


/*
|--------------------------------------------------------------------------
| LEFT AREA
|--------------------------------------------------------------------------
*/

.header-left {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}


/*
|--------------------------------------------------------------------------
| MOBILE MENU BUTTON
|--------------------------------------------------------------------------
*/

.menu-button {
    width: 42px;
    height: 42px;

    display: none;

    flex: 0 0 42px;

    align-items: center;
    justify-content: center;

    padding: 0;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        var(--sh-radius-md);

    background:
        var(--sh-surface);

    color:
        var(--sh-primary);

    cursor: pointer;

    transition:
        background 0.16s ease,
        border-color 0.16s ease,
        box-shadow 0.16s ease;
}

.menu-button:hover {
    border-color:
        var(--sh-border-strong);

    background:
        var(--sh-surface-muted);

    box-shadow:
        var(--sh-shadow-sm);
}

.menu-button svg {
    width: 20px;
    height: 20px;

    fill: none;

    stroke:
        currentColor;

    stroke-width:
        1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| PAGE HEADING
|--------------------------------------------------------------------------
*/

.page-heading {
    min-width: 0;
}

.breadcrumb-row {
    display: flex;
    align-items: center;

    gap: 5px;

    margin-bottom: 3px;
}

.breadcrumb-home {
    color:
        var(--sh-text-3);

    font-size: 9px;
    font-weight: 700;

    letter-spacing: 0.6px;

    text-transform:
        uppercase;
}

.breadcrumb-arrow {
    width: 11px;
    height: 11px;

    fill: none;

    stroke:
        #a6adb7;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.breadcrumb-section {
    color:
        var(--sh-accent);

    font-size: 9px;
    font-weight: 800;

    letter-spacing: 0.6px;

    text-transform:
        uppercase;
}


/*
|--------------------------------------------------------------------------
| TITLE
|--------------------------------------------------------------------------
*/

.title-row {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 9px;
}

.title-row h1 {
    min-width: 0;

    overflow: hidden;

    margin: 0;

    color:
        var(--sh-text);

    font-size: 22px;
    font-weight: 800;

    line-height: 28px;

    letter-spacing:
        -0.55px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.page-indicator {
    min-height: 21px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        7px;

    border:
        1px solid
        #dceee5;

    border-radius:
        999px;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);

    font-size: 8px;
    font-weight: 800;

    letter-spacing:
        0.4px;

    text-transform:
        uppercase;
}

.page-description {
    max-width: 680px;

    overflow: hidden;

    margin:
        3px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 15px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}


/*
|--------------------------------------------------------------------------
| RIGHT ACTIONS
|--------------------------------------------------------------------------
*/

.header-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 11px;
}


/*
|--------------------------------------------------------------------------
| SYSTEM STATUS
|--------------------------------------------------------------------------
*/

.system-status {
    min-height: 41px;

    display: flex;
    align-items: center;

    gap: 8px;

    padding:
        0
        8px;
}

.status-dot {
    width: 8px;
    height: 8px;

    flex: 0 0 8px;

    border-radius:
        999px;

    background:
        #3ea879;

    box-shadow:
        0 0 0 4px
        rgba(
            62,
            168,
            121,
            0.1
        );
}

.status-copy {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.status-copy span {
    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 600;

    line-height: 11px;

    text-transform:
        uppercase;

    letter-spacing:
        0.5px;
}

.status-copy strong {
    color:
        var(--sh-text-2);

    font-size: 10px;
    font-weight: 700;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| HEADER DIVIDER
|--------------------------------------------------------------------------
*/

.header-divider {
    width: 1px;
    height: 31px;

    background:
        var(--sh-border);
}


/*
|--------------------------------------------------------------------------
| ADMIN PROFILE
|--------------------------------------------------------------------------
*/

.administrator {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 9px;

    padding:
        4px
        7px
        4px
        5px;

    border:
        1px solid
        transparent;

    border-radius:
        13px;

    background:
        transparent;

    color:
        inherit;

    font: inherit;

    text-align:
        left;

    cursor: pointer;

    transition:
        border-color 0.16s ease,
        background 0.16s ease;
}

.administrator:hover {
    border-color:
        var(--sh-border);

    background:
        var(--sh-surface-muted);
}

.administrator-avatar {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius:
        11px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary-2),
            var(--sh-primary)
        );

    color:
        #ffffff;

    font-size: 11px;
    font-weight: 800;

    letter-spacing:
        0.4px;

    box-shadow:
        inset 0 0 0 1px
        rgba(
            255,
            255,
            255,
            0.06
        );
}

.administrator-copy {
    max-width: 180px;

    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.administrator-copy strong {
    overflow: hidden;

    color:
        var(--sh-text);

    font-size: 10.5px;
    font-weight: 700;

    line-height: 14px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.administrator-copy span {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 12px;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;
}

.profile-chevron {
    width: 14px;
    height: 14px;

    flex: 0 0 14px;

    fill: none;

    stroke:
        #a8afb8;

    stroke-width:
        1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| LOGOUT BUTTON
|--------------------------------------------------------------------------
*/

.logout-button {
    min-height: 40px;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 7px;

    padding:
        0
        12px;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        10px;

    background:
        #ffffff;

    color:
        var(--sh-text-2);

    font: inherit;

    font-size: 9.5px;
    font-weight: 700;

    cursor: pointer;

    transition:
        color 0.16s ease,
        border-color 0.16s ease,
        background 0.16s ease;
}

.logout-button:hover:not(:disabled) {
    border-color:
        #edd6d6;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.logout-button:disabled {
    opacity: 0.55;

    cursor:
        not-allowed;
}

.logout-button svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width:
        1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {
    .system-status {
        display: none;
    }

    .header-divider {
        display: none;
    }

    .administrator-copy {
        max-width: 145px;
    }
}


@media (max-width: 1024px) {
    .menu-button {
        display: flex;
    }

    .admin-header {
        padding:
            15px
            20px;
    }
}


@media (max-width: 760px) {
    .admin-header {
        min-height: 78px;

        gap: 14px;

        padding:
            12px
            15px;
    }

    .page-description {
        display: none;
    }

    .breadcrumb-row {
        display: none;
    }

    .title-row h1 {
        font-size: 18px;

        line-height: 23px;
    }

    .page-indicator {
        display: none;
    }

    .administrator {
        display: none;
    }

    .logout-button {
        width: 40px;

        padding: 0;
    }

    .logout-button span {
        display: none;
    }
}


@media (max-width: 480px) {
    .admin-header {
        padding:
            11px
            12px;
    }

    .header-left {
        gap: 10px;
    }

    .menu-button {
        width: 40px;
        height: 40px;

        flex-basis: 40px;
    }

    .title-row h1 {
        max-width: 180px;
    }
}
</style>