import {
    createRouter,
    createWebHistory,
} from 'vue-router';

import { pinia } from '../pinia';
import { useAuthStore } from '../stores/auth';

import AdminLayout from '../layouts/AdminLayout.vue';

import DashboardView from '../views/DashboardView.vue';
import LoginView from '../views/LoginView.vue';

import ShopsView from '../views/ShopsView.vue';
import ShopDetailView from '../views/ShopDetailView.vue';

import UsersView from '../views/UsersView.vue';
import UserDetailView from '../views/UserDetailView.vue';

import AppointmentsView from '../views/AppointmentsView.vue';
import AppointmentDetailView from '../views/AppointmentDetailView.vue';


import FeedbackView from '../views/FeedbackView.vue';
import FeedbackDetailView from '../views/FeedbackDetailView.vue';

import ReportsView from '../views/ReportsView.vue';

import SmsMonitoringView from '../views/SmsMonitoringView.vue';
import SmsNotificationDetailView from '../views/SmsNotificationDetailView.vue';

import SystemSettingsView from '../views/SystemSettingsView.vue';
import AccountSettingsView from '../views/AccountSettingsView.vue';

import AuditLogsView from '../views/AuditLogsView.vue';
import AuditLogDetailView from '../views/AuditLogDetailView.vue';

const router = createRouter({
    history: createWebHistory(),

    routes: [
        {
            path: '/admin/login',
            name: 'admin.login',
            component: LoginView,

            meta: {
                guestOnly: true,
                title: 'Sign In',
            },
        },

        {
            path: '/admin',
            component: AdminLayout,

            meta: {
                requiresAuth: true,
            },

            children: [
                {
                    path: '',

                    redirect: {
                        name:
                            'admin.dashboard',
                    },
                },

                {
                    path: 'dashboard',
                    name: 'admin.dashboard',
                    component: DashboardView,

                    meta: {
                        requiresAuth: true,
                        title: 'Dashboard',
                    },
                },

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

                {
                    path: 'reports',
                    name: 'admin.reports',
                    component: ReportsView,

                    meta: {
                        requiresAuth: true,
                        title: 'Reports',
                    },
                },

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