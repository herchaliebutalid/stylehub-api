
import {
    createRouter,
    createWebHistory,
} from 'vue-router';

import { pinia } from '../pinia';
import { useAuthStore } from '../stores/auth';

import AdminLayout from '../layouts/AdminLayout.vue';
import LoginView from '../views/LoginView.vue';

/*
|--------------------------------------------------------------------------
| Lazy-Loaded Admin Pages
|--------------------------------------------------------------------------
|
| Login and the shared admin layout are loaded immediately.
|
| Other admin pages are loaded only when their routes are visited.
| This reduces the amount of JavaScript downloaded initially.
|
| All existing route names, paths, guards, and metadata are preserved.
|
*/

const DashboardView = () =>
    import('../views/DashboardView.vue');

const ShopsView = () =>
    import('../views/ShopsView.vue');

const ShopDetailView = () =>
    import('../views/ShopDetailView.vue');

const UsersView = () =>
    import('../views/UsersView.vue');

const UserDetailView = () =>
    import('../views/UserDetailView.vue');

const AppointmentsView = () =>
    import('../views/AppointmentsView.vue');

const AppointmentDetailView = () =>
    import('../views/AppointmentDetailView.vue');

const FeedbackView = () =>
    import('../views/FeedbackView.vue');

const FeedbackDetailView = () =>
    import('../views/FeedbackDetailView.vue');

const ReportsView = () =>
    import('../views/ReportsView.vue');

const SmsMonitoringView = () =>
    import('../views/SmsMonitoringView.vue');

const SmsNotificationDetailView = () =>
    import('../views/SmsNotificationDetailView.vue');

const SystemSettingsView = () =>
    import('../views/SystemSettingsView.vue');

const AccountSettingsView = () =>
    import('../views/AccountSettingsView.vue');

const AuditLogsView = () =>
    import('../views/AuditLogsView.vue');

const AuditLogDetailView = () =>
    import('../views/AuditLogDetailView.vue');

/*
|--------------------------------------------------------------------------
| Admin Router
|--------------------------------------------------------------------------
*/

const router = createRouter({
    history: createWebHistory(),

    routes: [
        /*
        |--------------------------------------------------------------------------
        | Administrator Login
        |--------------------------------------------------------------------------
        */

        {
            path: '/admin/login',
            name: 'admin.login',
            component: LoginView,

            meta: {
                guestOnly: true,
                title: 'Sign In',
            },
        },

        /*
        |--------------------------------------------------------------------------
        | Protected Administrator Routes
        |--------------------------------------------------------------------------
        */

        {
            path: '/admin',
            component: AdminLayout,

            meta: {
                requiresAuth: true,
            },

            children: [
                /*
                |--------------------------------------------------------------------------
                | Default Redirect
                |--------------------------------------------------------------------------
                */

                {
                    path: '',

                    redirect: {
                        name:
                            'admin.dashboard',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Dashboard
                |--------------------------------------------------------------------------
                */

                {
                    path: 'dashboard',
                    name: 'admin.dashboard',
                    component: DashboardView,

                    meta: {
                        requiresAuth: true,
                        title: 'Dashboard',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Shops
                |--------------------------------------------------------------------------
                */

                {
                    path: 'shops',
                    name: 'admin.shops',
                    component: ShopsView,

                    meta: {
                        requiresAuth: true,
                        title: 'Shops',
                    },
                },

                {
                    path: 'shops/:shopId',
                    name: 'admin.shop.detail',
                    component: ShopDetailView,

                    meta: {
                        requiresAuth: true,
                        title: 'Shop Details',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Users
                |--------------------------------------------------------------------------
                */

                {
                    path: 'users',
                    name: 'admin.users',
                    component: UsersView,

                    meta: {
                        requiresAuth: true,
                        title: 'Users',
                    },
                },

                {
                    path: 'users/:userId',
                    name: 'admin.user.detail',
                    component: UserDetailView,

                    meta: {
                        requiresAuth: true,
                        title: 'User Details',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Appointments
                |--------------------------------------------------------------------------
                */

                {
                    path: 'appointments',
                    name: 'admin.appointments',
                    component: AppointmentsView,

                    meta: {
                        requiresAuth: true,
                        title: 'Appointments',
                    },
                },

                {
                    path:
                        'appointments/:appointmentId',

                    name:
                        'admin.appointment.detail',

                    component:
                        AppointmentDetailView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'Appointment Details',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Feedback
                |--------------------------------------------------------------------------
                */

                {
                    path: 'feedback',
                    name: 'admin.feedback',
                    component: FeedbackView,

                    meta: {
                        requiresAuth: true,
                        title: 'Feedback',
                    },
                },

                {
                    path:
                        'feedback/:feedbackId',

                    name:
                        'admin.feedback.detail',

                    component:
                        FeedbackDetailView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'Feedback Details',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Reports
                |--------------------------------------------------------------------------
                */

                {
                    path: 'reports',
                    name: 'admin.reports',
                    component: ReportsView,

                    meta: {
                        requiresAuth: true,
                        title: 'Reports',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | SMS Monitoring
                |--------------------------------------------------------------------------
                */

                {
                    path:
                        'sms-monitoring',

                    name:
                        'admin.sms-monitoring',

                    component:
                        SmsMonitoringView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'SMS Monitoring',
                    },
                },

                {
                    path:
                        'sms-monitoring/:smsNotificationId',

                    name:
                        'admin.sms-notification.detail',

                    component:
                        SmsNotificationDetailView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'SMS Details',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | System Settings
                |--------------------------------------------------------------------------
                */

                {
                    path:
                        'system-settings',

                    name:
                        'admin.system-settings',

                    component:
                        SystemSettingsView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'System Settings',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Account Settings
                |--------------------------------------------------------------------------
                */

                {
                    path:
                        'account-settings',

                    name:
                        'admin.account-settings',

                    component:
                        AccountSettingsView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'Account Settings',
                    },
                },

                /*
                |--------------------------------------------------------------------------
                | Audit Logs
                |--------------------------------------------------------------------------
                */

                {
                    path:
                        'audit-logs',

                    name:
                        'admin.audit-logs',

                    component:
                        AuditLogsView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'Audit Logs',
                    },
                },

                {
                    path:
                        'audit-logs/:auditLogId',

                    name:
                        'admin.audit-log.detail',

                    component:
                        AuditLogDetailView,

                    meta: {
                        requiresAuth: true,

                        title:
                            'Audit Log Details',
                    },
                },
            ],
        },

        /*
        |--------------------------------------------------------------------------
        | Unknown Administrator Routes
        |--------------------------------------------------------------------------
        */

        {
            path:
                '/admin/:pathMatch(.*)*',

            name:
                'admin.not-found',

            redirect: () => {
                const auth =
                    useAuthStore(
                        pinia,
                    );

                if (
                    auth.isAuthenticated
                ) {
                    return {
                        name:
                            'admin.dashboard',
                    };
                }

                return {
                    name:
                        'admin.login',
                };
            },
        },
    ],
});

/*
|--------------------------------------------------------------------------
| Administrator Authentication Guards
|--------------------------------------------------------------------------
|
| Prevent unauthenticated access to protected admin pages.
| Redirect already authenticated administrators away from login.
|
*/

router.beforeEach(
    (to) => {
        const auth =
            useAuthStore(
                pinia,
            );

        const requiresAuth =
            to.matched.some(
                (record) =>
                    record.meta
                        .requiresAuth,
            );

        if (
            requiresAuth
            &&
            !auth.isAuthenticated
        ) {
            return {
                name:
                    'admin.login',
            };
        }

        if (
            to.meta.guestOnly
            &&
            auth.isAuthenticated
        ) {
            return {
                name:
                    'admin.dashboard',
            };
        }

        return true;
    },
);

export default router;
