<script setup>
import { useRoute, useRouter } from 'vue-router';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const route = useRoute();
const router = useRouter();

const styleHubLogo = '/images/stylehub-logo.png';

/*
|--------------------------------------------------------------------------
| ADMINISTRATOR NAVIGATION
|--------------------------------------------------------------------------
*/

const navigationSections = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Dashboard',
                routeName: 'admin.dashboard',
                activeRoutes: ['admin.dashboard'],
                icon: 'dashboard',
            },
        ],
    },
    {
        label: 'Management',
        items: [
            {
                label: 'Shops',
                routeName: 'admin.shops',
                activeRoutes: [
                    'admin.shops',
                    'admin.shop.detail',
                ],
                icon: 'shop',
            },
            {
                label: 'Users',
                routeName: 'admin.users',
                activeRoutes: [
                    'admin.users',
                    'admin.user.detail',
                ],
                icon: 'users',
            },
            {
                label: 'Appointments',
                routeName: 'admin.appointments',
                activeRoutes: [
                    'admin.appointments',
                    'admin.appointment.detail',
                ],
                icon: 'calendar',
            },
            {
                label: 'Feedback',
                routeName: 'admin.feedback',
                activeRoutes: [
                    'admin.feedback',
                    'admin.feedback.detail',
                ],
                icon: 'feedback',
            },
        ],
    },
    {
        label: 'Operations',
        items: [
            {
                label: 'Reports',
                routeName: 'admin.reports',
                activeRoutes: ['admin.reports'],
                icon: 'reports',
            },
            {
                label: 'SMS Monitoring',
                routeName: 'admin.sms-monitoring',
                activeRoutes: [
                    'admin.sms-monitoring',
                    'admin.sms-notification.detail',
                ],
                icon: 'sms',
            },
            {
                label: 'Audit Logs',
                routeName: 'admin.audit-logs',
                activeRoutes: [
                    'admin.audit-logs',
                    'admin.audit-log.detail',
                ],
                icon: 'audit',
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                label: 'System Settings',
                routeName: 'admin.system-settings',
                activeRoutes: [
                    'admin.system-settings',
                ],
                icon: 'system',
            },
            {
                label: 'Account Settings',
                routeName: 'admin.account-settings',
                activeRoutes: [
                    'admin.account-settings',
                ],
                icon: 'settings',
            },
        ],
    },
];

function isActive(item) {
    return item.activeRoutes.includes(route.name);
}

async function navigate(item) {
    emit('close');

    if (route.name === item.routeName) {
        return;
    }

    await router.push({
        name: item.routeName,
    });
}
</script>

<template>
    <aside
        class="sidebar"
        :class="{
            'sidebar-open': props.open,
        }"
    >
        <!-- ===================================================
             BRAND HEADER
             =================================================== -->

        <div class="sidebar-header">
            <div class="brand">
                <div class="brand-logo-wrap">
                    <img
                        :src="styleHubLogo"
                        alt="StyleHub logo"
                        class="brand-logo"
                    />
                </div>

                <div class="brand-copy">
                    <span class="brand-name">
                        StyleHub
                    </span>

                    <span class="brand-subtitle">
                        Administration
                    </span>
                </div>
            </div>

            <button
                type="button"
                class="mobile-close"
                aria-label="Close navigation"
                @click="emit('close')"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>

        <!-- ===================================================
             NAVIGATION
             =================================================== -->

        <nav
            class="navigation"
            aria-label="Administrator navigation"
        >
            <div
                v-for="section in navigationSections"
                :key="section.label"
                class="navigation-section"
            >
                <div class="navigation-section-header">
                    <span class="navigation-section-label">
                        {{ section.label }}
                    </span>

                    <span class="navigation-section-line"></span>
                </div>

                <div class="navigation-list">
                    <button
                        v-for="item in section.items"
                        :key="item.label"
                        type="button"
                        class="navigation-item"
                        :class="{
                            active: isActive(item),
                        }"
                        :aria-current="
                            isActive(item) ? 'page' : undefined
                        "
                        @click="navigate(item)"
                    >
                        <span class="active-bar"></span>

                        <span class="navigation-icon">
                            <svg
                                v-if="item.icon === 'dashboard'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="3"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                />
                                <rect
                                    x="14"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'shop'"
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
                                v-else-if="item.icon === 'users'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle cx="9" cy="8" r="3" />
                                <path
                                    d="M3 20c0-4 2-7 6-7s6 3 6 7"
                                />
                                <circle cx="17" cy="9" r="2.5" />
                                <path
                                    d="M15 14c4 0 6 2 6 6"
                                />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'calendar'"
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

                            <svg
                                v-else-if="item.icon === 'feedback'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 5h16v12H9l-5 4V5z"
                                />
                                <path d="M8 9h8M8 13h5" />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'reports'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="M5 20V10M12 20V4M19 20v-7" />
                                <path d="M3 20h18" />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'sms'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="4"
                                    y="3"
                                    width="16"
                                    height="18"
                                    rx="3"
                                />
                                <path
                                    d="M8 7h8M8 11h8M8 15h5"
                                />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'system'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="M4 6h10M18 6h2" />
                                <path d="M4 12h3M11 12h9" />
                                <path d="M4 18h7M15 18h5" />
                                <circle cx="16" cy="6" r="2" />
                                <circle cx="9" cy="12" r="2" />
                                <circle cx="13" cy="18" r="2" />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'settings'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="3" />
                                <path
                                    d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.86 2.86-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.1A1.7 1.7 0 0 0 8.2 19.3a1.7 1.7 0 0 0-1.88.34l-.06.06-2.86-2.86.06-.06A1.7 1.7 0 0 0 3.8 15a1.7 1.7 0 0 0-.6-1 1.7 1.7 0 0 0-1.1-.4H2V9.6h.1A1.7 1.7 0 0 0 3.7 8.2a1.7 1.7 0 0 0-.34-1.88l-.06-.06L6.16 3.4l.06.06A1.7 1.7 0 0 0 8.1 3.8a1.7 1.7 0 0 0 1-.6A1.7 1.7 0 0 0 9.5 2.1V2h4v.1a1.7 1.7 0 0 0 1.4 1.6 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.86 2.86-.06.06A1.7 1.7 0 0 0 19.3 8.1a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.1v4H21a1.7 1.7 0 0 0-1.6 1.5z"
                                />
                            </svg>

                            <svg
                                v-else-if="item.icon === 'audit'"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path d="M6 3h9l3 3v15H6V3z" />
                                <path d="M14 3v4h4" />
                                <path d="M9 11h6M9 15h4" />
                                <circle
                                    cx="16.5"
                                    cy="17.5"
                                    r="3.5"
                                />
                                <path d="M19 20l2 2" />
                            </svg>
                        </span>

                        <span class="navigation-text">
                            {{ item.label }}
                        </span>

                        <span
                            v-if="isActive(item)"
                            class="active-dot"
                        ></span>
                    </button>
                </div>
            </div>
        </nav>

        <!-- ===================================================
             SIDEBAR FOOTER
             =================================================== -->

        
    </aside>
</template>

<style scoped>
.sidebar {
    position: fixed;
    z-index: 50;
    top: 0;
    bottom: 0;
    left: 0;

    width: 282px;

    display: flex;
    flex-direction: column;
    overflow: hidden;

    background:
        radial-gradient(
            circle at 4% 0%,
            rgba(214, 154, 45, 0.11),
            transparent 23%
        ),
        radial-gradient(
            circle at 100% 18%,
            rgba(255, 255, 255, 0.035),
            transparent 30%
        ),
        var(--sh-primary);

    border-right:
        1px solid rgba(255, 255, 255, 0.055);

    box-shadow:
        6px 0 30px rgba(15, 23, 42, 0.06);

    color: #ffffff;
}

.sidebar-header {
    min-height: 94px;

    flex: 0 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 16px 18px;

    border-bottom:
        1px solid rgba(255, 255, 255, 0.12);
}

.brand {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 12px;
}

.brand-logo-wrap {
    width: 60px;
    height: 60px;

    flex: 0 0 60px;

    display: flex;
    align-items: center;
    justify-content: center;

    overflow: hidden;

    border: 1px solid #ffffff;
    border-radius: 16px;

    background: #ffffff;

    box-shadow:
        0 5px 18px rgba(0, 0, 0, 0.18),
        inset 0 0 0 1px rgba(15, 23, 42, 0.035);
}

.brand-logo {
    width: 54px;
    height: 54px;

    display: block;
    object-fit: contain;
}

.brand-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 3px;
}

.brand-name {
    overflow: hidden;

    color: #ffffff;

    font-size: 19px;
    font-weight: 800;
    letter-spacing: -0.45px;
    line-height: 23px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.brand-subtitle {
    color: rgba(255, 255, 255, 0.56);

    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

.mobile-close {
    width: 38px;
    height: 38px;

    display: none;
    flex: 0 0 38px;

    align-items: center;
    justify-content: center;

    padding: 0;

    border:
        1px solid rgba(255, 255, 255, 0.08);

    border-radius: 10px;

    background:
        rgba(255, 255, 255, 0.035);

    color:
        rgba(255, 255, 255, 0.72);

    cursor: pointer;

    transition:
        background 0.16s ease,
        color 0.16s ease,
        border-color 0.16s ease;
}

.mobile-close:hover {
    border-color:
        rgba(255, 255, 255, 0.12);

    background:
        rgba(255, 255, 255, 0.08);

    color: #ffffff;
}

.mobile-close svg {
    width: 19px;
    height: 19px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.navigation {
    flex: 1;

    overflow-x: hidden;
    overflow-y: auto;

    padding: 10px 13px 16px;

    scrollbar-width: thin;
    scrollbar-color:
        rgba(255, 255, 255, 0.08)
        transparent;
}

.navigation::-webkit-scrollbar {
    width: 4px;
}

.navigation::-webkit-scrollbar-track {
    background: transparent;
}

.navigation::-webkit-scrollbar-thumb {
    border-radius: 999px;

    background:
        rgba(255, 255, 255, 0.08);
}

.navigation-section {
    margin-top: 20px;
}

.navigation-section:first-child {
    margin-top: 2px;
}

.navigation-section-header {
    height: 22px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding: 0 9px;
    margin-bottom: 6px;
}

.navigation-section-label {
    flex: 0 0 auto;

    color:
        rgba(255, 255, 255, 0.35);

    font-size: 8.5px;
    font-weight: 800;

    letter-spacing: 1.55px;
    text-transform: uppercase;
}

.navigation-section-line {
    height: 1px;
    flex: 1;

    background:
        rgba(255, 255, 255, 0.07);
}

.navigation-list {
    display: flex;
    flex-direction: column;

    gap: 3px;
}

.navigation-item {
    position: relative;

    width: 100%;
    min-height: 45px;

    display: flex;
    align-items: center;

    gap: 11px;

    overflow: hidden;

    padding: 0 11px;

    border:
        1px solid transparent;

    border-radius: 11px;

    background: transparent;

    color:
        rgba(255, 255, 255, 0.55);

    font: inherit;
    font-size: 12.5px;
    font-weight: 600;

    text-align: left;
    cursor: pointer;

    transition:
        color 0.16s ease,
        background 0.16s ease,
        border-color 0.16s ease,
        transform 0.16s ease;
}

.navigation-item:hover {
    background:
        rgba(255, 255, 255, 0.045);

    color:
        rgba(255, 255, 255, 0.91);
}

.navigation-item:active {
    transform: translateY(1px);
}

.navigation-item.active {
    border-color:
        rgba(214, 154, 45, 0.12);

    background:
        linear-gradient(
            90deg,
            rgba(214, 154, 45, 0.13),
            rgba(255, 255, 255, 0.04)
        );

    color: #ffffff;
}

.active-bar {
    position: absolute;

    top: 9px;
    bottom: 9px;
    left: 0;

    width: 3px;

    border-radius: 0 4px 4px 0;

    background: var(--sh-accent);

    opacity: 0;
    transform: scaleY(0.55);

    transition:
        opacity 0.16s ease,
        transform 0.16s ease;
}

.navigation-item.active .active-bar {
    opacity: 1;
    transform: scaleY(1);
}

.navigation-icon {
    width: 33px;
    height: 33px;

    flex: 0 0 33px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        rgba(255, 255, 255, 0.025);

    color:
        rgba(255, 255, 255, 0.47);

    transition:
        color 0.16s ease,
        background 0.16s ease;
}

.navigation-item:hover .navigation-icon {
    background:
        rgba(255, 255, 255, 0.045);

    color:
        rgba(255, 255, 255, 0.82);
}

.navigation-item.active .navigation-icon {
    background:
        rgba(214, 154, 45, 0.105);

    color: var(--sh-accent);
}

.navigation-icon svg {
    width: 18px;
    height: 18px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.65;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.navigation-text {
    min-width: 0;
    flex: 1;

    overflow: hidden;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.active-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

    border-radius: 999px;

    background: var(--sh-accent);

    box-shadow:
        0 0 0 4px rgba(214, 154, 45, 0.08);
}

.sidebar-footer {
    min-height: 69px;

    display: flex;
    align-items: center;

    gap: 10px;

    padding: 12px 17px;

    border-top:
        1px solid rgba(255, 255, 255, 0.06);

    background:
        rgba(0, 0, 0, 0.07);
}

.sidebar-footer-icon {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border:
        1px solid rgba(255, 255, 255, 0.06);

    border-radius: 10px;

    background:
        rgba(255, 255, 255, 0.035);

    color:
        rgba(255, 255, 255, 0.5);
}

.sidebar-footer-icon svg {
    width: 17px;
    height: 17px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.6;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.sidebar-footer-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.sidebar-footer-title {
    overflow: hidden;

    color:
        rgba(255, 255, 255, 0.68);

    font-size: 10px;
    font-weight: 700;

    text-overflow: ellipsis;
    white-space: nowrap;
}

.sidebar-footer-caption {
    overflow: hidden;

    color:
        rgba(255, 255, 255, 0.27);

    font-size: 8.5px;

    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 1024px) {
    .sidebar {
        width: 282px;

        transform: translateX(-100%);

        box-shadow:
            18px 0 55px rgba(15, 23, 42, 0.25);

        transition:
            transform 0.22s ease;
    }

    .sidebar.sidebar-open {
        transform: translateX(0);
    }

    .sidebar-header {
        min-height: 94px;
        padding: 15px 18px;
    }

    .mobile-close {
        display: flex;
    }
}

@media (max-width: 760px) {
    .sidebar-header {
        min-height: 78px;
        padding: 12px 15px;
    }

    .brand-logo-wrap {
        width: 54px;
        height: 54px;
        flex-basis: 54px;
    }

    .brand-logo {
        width: 48px;
        height: 48px;
    }
}

@media (max-width: 480px) {
    .sidebar {
        width: min(88vw, 282px);
    }

    .sidebar-header {
        min-height: 78px;
        padding: 12px 14px;
    }

    .brand-logo-wrap {
        width: 52px;
        height: 52px;
        flex-basis: 52px;
        border-radius: 14px;
    }

    .brand-logo {
        width: 46px;
        height: 46px;
    }

    .brand-name {
        font-size: 18px;
    }
}
</style>