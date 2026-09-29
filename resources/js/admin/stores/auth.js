import { defineStore } from 'pinia';

import { apiRequest } from '../services/api';

export const useAuthStore = defineStore('admin-auth', {
    state: () => ({
        token: null,
        expiresAt: null,
        user: null,
        isLoggingIn: false,
    }),

    getters: {
        isAuthenticated(state) {
            return Boolean(
                state.token
                && state.user
                && state.user.role === 'admin'
                && state.user.status === 'active'
            );
        },

        administratorEmail(state) {
            return state.user?.email ?? '';
        },

        administratorName(state) {
            const firstName =
                state.user?.first_name
                ?? '';

            const lastName =
                state.user?.last_name
                ?? '';

            const fullName =
                `${firstName} ${lastName}`
                    .trim();

            return fullName
                || 'Administrator';
        },

        administratorInitials(state) {
            const firstName =
                state.user?.first_name
                ?? '';

            const lastName =
                state.user?.last_name
                ?? '';

            const firstInitial =
                firstName.charAt(0);

            const lastInitial =
                lastName.charAt(0);

            const initials =
                `${firstInitial}${lastInitial}`
                    .trim()
                    .toUpperCase();

            return initials
                || 'A';
        },
    },

    actions: {
        async login({
            email,
            password,
        }) {
            this.isLoggingIn = true;

            try {
                const response =
                    await apiRequest(
                        '/admin/auth/login',
                        {
                            method:
                                'POST',

                            body: {
                                email,
                                password,

                                device_name:
                                    'stylehub-admin-web',
                            },
                        },
                    );

                const data =
                    response?.data;

                if (
                    !data?.token
                    ||
                    !data?.user
                    ||
                    data.user.role
                        !== 'admin'
                ) {
                    this.clearSession();

                    throw new Error(
                        'The server returned an invalid Administrator session.',
                    );
                }

                this.token =
                    data.token;

                this.expiresAt =
                    data.expires_at
                    ?? null;

                this.user =
                    data.user;

                return response;
            } catch (error) {
                this.clearSession();

                throw error;
            } finally {
                this.isLoggingIn =
                    false;
            }
        },

        setUser(user) {
            if (
                !user
                ||
                user.role !== 'admin'
                ||
                user.status !== 'active'
            ) {
                this.clearSession();

                return;
            }

            this.user =
                user;
        },

        async refreshUser() {
            if (!this.token) {
                this.clearSession();

                return null;
            }

            const response =
                await apiRequest(
                    '/admin/account',
                    {
                        token:
                            this.token,
                    },
                );

            const user =
                response?.data?.user;

            if (
                !user
                ||
                user.role !== 'admin'
                ||
                user.status !== 'active'
            ) {
                this.clearSession();

                throw new Error(
                    'The server returned an invalid Administrator account.',
                );
            }

            this.user =
                user;

            return user;
        },

        async logout() {
            const activeToken =
                this.token;

            try {
                if (activeToken) {
                    await apiRequest(
                        '/admin/auth/logout',
                        {
                            method:
                                'POST',

                            token:
                                activeToken,
                        },
                    );
                }
            } finally {
                this.clearSession();
            }
        },

        clearSession() {
            this.token = null;
            this.expiresAt = null;
            this.user = null;
        },
    },
});