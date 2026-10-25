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

const account = ref(null);

const isLoading = ref(false);
const isSavingProfile = ref(false);
const isChangingPassword = ref(false);

const pageError = ref('');
const profileSuccess = ref('');
const passwordSuccess = ref('');

const profileErrors = ref({});
const passwordErrors = ref({});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const profile = reactive({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
});

const password = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});


/*
|--------------------------------------------------------------------------
| ACCOUNT PRESENTATION
|--------------------------------------------------------------------------
*/

const fullName = computed(() => {
    const parts = [
        account.value?.first_name,
        account.value?.middle_name,
        account.value?.last_name,
    ].filter(Boolean);

    return parts.join(' ')
        || 'Administrator';
});

const initials = computed(() => {
    const first =
        account.value
            ?.first_name
            ?.charAt(0)
        ?? '';

    const last =
        account.value
            ?.last_name
            ?.charAt(0)
        ?? '';

    return `${first}${last}`
        .toUpperCase()
        || 'A';
});

const profilePhoto = computed(
    () =>
        account.value
            ?.profile_photo_url
        || null,
);


/*
|--------------------------------------------------------------------------
| PROFILE CHANGE STATE
|--------------------------------------------------------------------------
*/

const profileDirty = computed(() => {
    if (!account.value) {
        return false;
    }

    const accountMiddle =
        account.value.middle_name
        ?? '';

    return (
        profile.first_name
            !== (
                account.value.first_name
                ?? ''
            )
        ||
        profile.middle_name
            !== accountMiddle
        ||
        profile.last_name
            !== (
                account.value.last_name
                ?? ''
            )
        ||
        profile.email
            !== (
                account.value.email
                ?? ''
            )
    );
});


/*
|--------------------------------------------------------------------------
| PASSWORD REQUIREMENTS
|--------------------------------------------------------------------------
*/

const passwordRequirements = computed(() => {
    const value =
        password.password
        ?? '';

    return {
        length:
            value.length >= 12
            &&
            value.length <= 64,

        uppercase:
            /[A-Z]/.test(value),

        lowercase:
            /[a-z]/.test(value),

        number:
            /\d/.test(value),

        symbol:
            /[^A-Za-z0-9]/.test(value),

        different:
            value.length > 0
            &&
            value
                !== password.current_password,

        confirmation:
            value.length > 0
            &&
            password.password_confirmation
                === value,
    };
});

const passwordStrengthCount = computed(
    () =>
        [
            passwordRequirements.value.length,
            passwordRequirements.value.uppercase,
            passwordRequirements.value.lowercase,
            passwordRequirements.value.number,
            passwordRequirements.value.symbol,
        ].filter(Boolean).length,
);

const passwordStrengthLabel = computed(() => {
    const count =
        passwordStrengthCount.value;

    if (
        password.password.length === 0
    ) {
        return 'Not entered';
    }

    if (count <= 2) {
        return 'Needs improvement';
    }

    if (count <= 4) {
        return 'Almost ready';
    }

    return 'Strong';
});

const passwordStrengthPercentage = computed(
    () =>
        (
            passwordStrengthCount.value
            / 5
        )
        * 100,
);


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function humanize(value) {
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
                character.toUpperCase(),
        );
}

function firstValidationMessage(
    errors,
    field,
) {
    const messages =
        errors?.[field];

    if (
        !Array.isArray(
            messages,
        )
    ) {
        return '';
    }

    return messages[0]
        ?? '';
}

function formatDateTime(value) {
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
            year:
                'numeric',

            month:
                'short',

            day:
                'numeric',

            hour:
                'numeric',

            minute:
                '2-digit',

            timeZone:
                'Asia/Manila',
        },
    ).format(date);
}

function fillProfile(user) {
    profile.first_name =
        user?.first_name
        ?? '';

    profile.middle_name =
        user?.middle_name
        ?? '';

    profile.last_name =
        user?.last_name
        ?? '';

    profile.email =
        user?.email
        ?? '';
}

function clearProfileFieldError(field) {
    if (
        profileErrors.value?.[field]
    ) {
        delete profileErrors.value[field];
    }

    profileSuccess.value =
        '';
}

function clearPasswordFieldError(field) {
    if (
        passwordErrors.value?.[field]
    ) {
        delete passwordErrors.value[field];
    }

    passwordSuccess.value =
        '';
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name:
            'admin.login',
    });
}


/*
|--------------------------------------------------------------------------
| LOAD ACCOUNT
|--------------------------------------------------------------------------
*/

async function loadAccount() {
    if (!auth.token) {
        await authenticationFailure();

        return;
    }

    isLoading.value =
        true;

    pageError.value =
        '';

    try {
        const response =
            await apiRequest(
                '/admin/account',
                {
                    token:
                        auth.token,
                },
            );

        const user =
            response
                ?.data
                ?.user;

        if (!user) {
            throw new Error(
                'The Administrator account response was invalid.',
            );
        }

        account.value =
            user;

        fillProfile(
            user,
        );

        auth.setUser(
            user,
        );
    } catch (
        error
    ) {
        if (
            error?.status === 401
            ||
            error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        pageError.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to load Administrator account settings.';
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| SAVE PROFILE
|--------------------------------------------------------------------------
*/

async function saveProfile() {
    if (
        isSavingProfile.value
    ) {
        return;
    }

    isSavingProfile.value =
        true;

    profileErrors.value =
        {};

    pageError.value =
        '';

    profileSuccess.value =
        '';

    try {
        const response =
            await apiRequest(
                '/admin/account/profile',
                {
                    method:
                        'PUT',

                    token:
                        auth.token,

                    body: {
                        first_name:
                            profile.first_name,

                        middle_name:
                            profile.middle_name
                                .trim()
                            || null,

                        last_name:
                            profile.last_name,

                        email:
                            profile.email,
                    },
                },
            );

        const user =
            response
                ?.data
                ?.user;

        if (!user) {
            throw new Error(
                'The updated Administrator profile response was invalid.',
            );
        }

        account.value =
            user;

        fillProfile(
            user,
        );

        auth.setUser(
            user,
        );

        profileSuccess.value =
            response?.message
            ??
            'Administrator profile updated successfully.';
    } catch (
        error
    ) {
        if (
            error?.status === 401
            ||
            error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status === 422
        ) {
            profileErrors.value =
                error?.data?.errors
                ?? {};

            return;
        }

        pageError.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to update Administrator profile.';
    } finally {
        isSavingProfile.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| RESET PROFILE
|--------------------------------------------------------------------------
*/

function resetProfile() {
    if (
        !account.value
        ||
        isSavingProfile.value
    ) {
        return;
    }

    fillProfile(
        account.value,
    );

    profileErrors.value =
        {};

    profileSuccess.value =
        '';
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

async function changePassword() {
    if (
        isChangingPassword.value
    ) {
        return;
    }

    isChangingPassword.value =
        true;

    passwordErrors.value =
        {};

    passwordSuccess.value =
        '';

    pageError.value =
        '';

    try {
        const response =
            await apiRequest(
                '/admin/account/password',
                {
                    method:
                        'POST',

                    token:
                        auth.token,

                    body: {
                        current_password:
                            password
                                .current_password,

                        password:
                            password
                                .password,

                        password_confirmation:
                            password
                                .password_confirmation,
                    },
                },
            );

        password.current_password =
            '';

        password.password =
            '';

        password.password_confirmation =
            '';

        showCurrentPassword.value =
            false;

        showNewPassword.value =
            false;

        showConfirmPassword.value =
            false;

        passwordSuccess.value =
            response?.message
            ??
            'Password changed successfully.';
    } catch (
        error
    ) {
        if (
            error?.status === 401
            ||
            error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        if (
            error?.status === 422
        ) {
            passwordErrors.value =
                error?.data?.errors
                ?? {};

            return;
        }

        pageError.value =
            error?.data?.message
            ??
            error?.message
            ??
            'Unable to change Administrator password.';
    } finally {
        isChangingPassword.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadAccount();
});
</script>


<template>
    <div class="account-settings-page">
        <!-- ============================================================
             INTRODUCTION
             ============================================================ -->

        <section class="account-intro">
            <div class="account-intro-copy">
                
                <h2>
                    Account settings
                </h2>

                <p>
                    Manage your Administrator profile,
                    review protected account information
                    and update your account password.
                </p>
            </div>


            <button
                type="button"
                class="secondary-button account-refresh-button"
                :disabled="
                    isLoading
                    ||
                    isSavingProfile
                    ||
                    isChangingPassword
                "
                @click="
                    loadAccount
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
        </section>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                pageError
            "
            class="error-alert account-error"
            role="alert"
        >
            <div class="account-error-icon">
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

            <div class="account-error-copy">
                <strong>
                    Unable to complete request
                </strong>

                <p>
                    {{ pageError }}
                </p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="
                    isLoading
                "
                @click="
                    loadAccount
                "
            >
                Try again
            </button>
        </div>


        <!-- ============================================================
             LOADING
             ============================================================ -->

        <section
            v-if="
                isLoading
                &&
                !account
            "
            class="loading-card account-loading-card"
        >
            <div class="account-loading-icon">
                <div class="spinner" />
            </div>

            <h3>
                Loading account settings
            </h3>

            <p>
                Retrieving your Administrator profile
                and account security information.
            </p>
        </section>


        <!-- ============================================================
             ACCOUNT CONTENT
             ============================================================ -->

        <template
            v-else-if="
                account
            "
        >
            <!-- ========================================================
                 IDENTITY HERO
                 ======================================================== -->

            <section class="administrator-hero">
                <div class="administrator-main">
                    <div class="administrator-avatar">
                        <img
                            v-if="
                                profilePhoto
                            "
                            :src="
                                profilePhoto
                            "
                            :alt="
                                fullName
                            "
                        >

                        <span v-else>
                            {{ initials }}
                        </span>
                    </div>


                    <div class="administrator-copy">
                        <div class="administrator-eyebrow">
                            <span class="administrator-eyebrow-dot" />

                            Signed-in Administrator

                            <span class="administrator-id">
                                #{{ account.id }}
                            </span>
                        </div>

                        <h2>
                            {{ fullName }}
                        </h2>

                        <div class="administrator-meta">
                            <div class="administrator-meta-item">
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
                                </svg>

                                <span>
                                    {{ account.email }}
                                </span>
                            </div>

                            <span class="administrator-meta-divider" />

                            <div class="administrator-meta-item">
                                <svg
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

                                <span>
                                    {{
                                        account.role_label
                                        ||
                                        humanize(
                                            account.role
                                        )
                                    }}
                                </span>
                            </div>

                            <span class="administrator-meta-divider" />

                            <div class="administrator-meta-item">
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
                                        d="M12 7v5l3 2"
                                    />
                                </svg>

                                <span>
                                    Last login:
                                    {{
                                        formatDateTime(
                                            account.last_login_at
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="administrator-statuses">
                    <span
                        class="role-badge administrator-role"
                        :class="
                            account.role
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M9 12l2 2 4-4"
                            />
                        </svg>

                        {{
                            account.role_label
                            ||
                            humanize(
                                account.role
                            )
                        }}
                    </span>


                    <span
                        class="status-badge administrator-status"
                        :class="
                            account.status
                        "
                    >
                        <span class="status-indicator" />

                        {{
                            account.status_label
                            ||
                            humanize(
                                account.status
                            )
                        }}
                    </span>
                </div>
            </section>


            <!-- ========================================================
                 ACCOUNT STATUS CARDS
                 ======================================================== -->

            <section class="account-summary-grid">
                <!-- EMAIL VERIFICATION -->

                <article class="account-summary-card">
                    <div
                        class="account-summary-icon"
                        :class="
                            account.email_verified
                                ? 'success'
                                : 'warning'
                        "
                    >
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
                                v-if="
                                    account.email_verified
                                "
                                d="M15 17l2 2 4-4"
                            />
                        </svg>
                    </div>

                    <div class="account-summary-copy">
                        <span>
                            Email verification
                        </span>

                        <strong class="text-value">
                            {{
                                account.email_verified
                                    ? 'Verified'
                                    : 'Not verified'
                            }}
                        </strong>

                        <small>
                            Current email state
                        </small>
                    </div>
                </article>


                <!-- PHONE VERIFICATION -->

                <article class="account-summary-card">
                    <div
                        class="account-summary-icon"
                        :class="
                            account.phone_verified
                                ? 'success'
                                : 'warning'
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M7 3h4l2 5-3 2c1.4 3 3.7 5.3 6.7 6.7l2-3 5 2v4c0 1.1-.9 2-2 2C11.4 21.7 2.3 12.6 2.3 2.3 2.3 1.2 3.2.3 4.3.3"
                            />

                            <path
                                v-if="
                                    account.phone_verified
                                "
                                d="M15 17l2 2 4-4"
                            />
                        </svg>
                    </div>

                    <div class="account-summary-copy">
                        <span>
                            Phone verification
                        </span>

                        <strong class="text-value">
                            {{
                                account.phone_verified
                                    ? 'Verified'
                                    : 'Not verified'
                            }}
                        </strong>

                        <small>
                            Mobile number state
                        </small>
                    </div>
                </article>


                <!-- ACCOUNT STATUS -->

                <article class="account-summary-card">
                    <div class="account-summary-icon success">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M8 12l3 3 5-6"
                            />
                        </svg>
                    </div>

                    <div class="account-summary-copy">
                        <span>
                            Account status
                        </span>

                        <strong class="text-value">
                            {{
                                account.status_label
                                ||
                                humanize(
                                    account.status
                                )
                            }}
                        </strong>

                        <small>
                            Administrator access state
                        </small>
                    </div>
                </article>


                <!-- CREATED -->

                <article class="account-summary-card">
                    <div class="account-summary-icon account-created">
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
                    </div>

                    <div class="account-summary-copy">
                        <span>
                            Account created
                        </span>

                        <strong class="date-value">
                            {{
                                formatDateTime(
                                    account.created_at
                                )
                            }}
                        </strong>

                        <small>
                            Administrator record
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 PROFILE + PROTECTED ACCOUNT
                 ======================================================== -->

            <section class="account-settings-grid">
                <!-- ====================================================
                     PROFILE
                     ==================================================== -->

                <article class="detail-card account-panel">
                    <div class="account-panel-header">
                        <div class="account-panel-heading">
                            <div class="account-panel-icon profile">
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
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    PROFILE
                                </p>

                                <h3>
                                    Personal information
                                </h3>

                                <p>
                                    Update the name and email used by
                                    the Administrator interface.
                                </p>
                            </div>
                        </div>


                        <span
                            v-if="
                                profileDirty
                            "
                            class="unsaved-profile-badge"
                        >
                            Unsaved changes
                        </span>
                    </div>


                    <!-- PROFILE SUCCESS -->

                    <div
                        v-if="
                            profileSuccess
                        "
                        class="profile-message success"
                    >
                        <div class="profile-message-icon">
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

                        <div>
                            <strong>
                                Profile updated
                            </strong>

                            <span>
                                {{ profileSuccess }}
                            </span>
                        </div>
                    </div>


                    <form
                        class="profile-form"
                        @submit.prevent="
                            saveProfile
                        "
                    >
                        <!-- FIRST NAME -->

                        <label class="account-field">
                            <span>
                                First name
                            </span>

                            <div
                                class="account-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            profileErrors,
                                            'first_name'
                                        ),
                                }"
                            >
                                <svg
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

                                <input
                                    v-model="
                                        profile.first_name
                                    "
                                    type="text"
                                    maxlength="100"
                                    autocomplete="given-name"
                                    placeholder="First name"
                                    :disabled="
                                        isSavingProfile
                                    "
                                    @input="
                                        clearProfileFieldError(
                                            'first_name'
                                        )
                                    "
                                >
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        profileErrors,
                                        'first_name'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        profileErrors,
                                        'first_name'
                                    )
                                }}
                            </small>
                        </label>


                        <!-- MIDDLE NAME -->

                        <label class="account-field">
                            <span>
                                Middle name
                            </span>

                            <div
                                class="account-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            profileErrors,
                                            'middle_name'
                                        ),
                                }"
                            >
                                <svg
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

                                <input
                                    v-model="
                                        profile.middle_name
                                    "
                                    type="text"
                                    maxlength="100"
                                    autocomplete="additional-name"
                                    placeholder="Optional"
                                    :disabled="
                                        isSavingProfile
                                    "
                                    @input="
                                        clearProfileFieldError(
                                            'middle_name'
                                        )
                                    "
                                >
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        profileErrors,
                                        'middle_name'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        profileErrors,
                                        'middle_name'
                                    )
                                }}
                            </small>
                        </label>


                        <!-- LAST NAME -->

                        <label class="account-field">
                            <span>
                                Last name
                            </span>

                            <div
                                class="account-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            profileErrors,
                                            'last_name'
                                        ),
                                }"
                            >
                                <svg
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

                                <input
                                    v-model="
                                        profile.last_name
                                    "
                                    type="text"
                                    maxlength="100"
                                    autocomplete="family-name"
                                    placeholder="Last name"
                                    :disabled="
                                        isSavingProfile
                                    "
                                    @input="
                                        clearProfileFieldError(
                                            'last_name'
                                        )
                                    "
                                >
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        profileErrors,
                                        'last_name'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        profileErrors,
                                        'last_name'
                                    )
                                }}
                            </small>
                        </label>


                        <!-- EMAIL -->

                        <label class="account-field full-width">
                            <span>
                                Email address
                            </span>

                            <div
                                class="account-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            profileErrors,
                                            'email'
                                        ),
                                }"
                            >
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
                                </svg>

                                <input
                                    v-model="
                                        profile.email
                                    "
                                    type="email"
                                    maxlength="255"
                                    autocomplete="email"
                                    placeholder="Administrator email"
                                    :disabled="
                                        isSavingProfile
                                    "
                                    @input="
                                        clearProfileFieldError(
                                            'email'
                                        )
                                    "
                                >
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        profileErrors,
                                        'email'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        profileErrors,
                                        'email'
                                    )
                                }}
                            </small>

                            <div class="email-warning">
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
                                        d="M12 11v5M12 8h.01"
                                    />
                                </svg>

                                <span>
                                    Changing your email address resets
                                    its previous email-verification state.
                                </span>
                            </div>
                        </label>


                        <!-- ACTIONS -->

                        <div class="profile-actions full-width">
                            <div class="profile-change-state">
                                <span
                                    class="change-state-dot"
                                    :class="{
                                        changed:
                                            profileDirty,
                                    }"
                                />

                                <span>
                                    {{
                                        profileDirty
                                            ? 'You have unsaved profile changes.'
                                            : 'Your profile is up to date.'
                                    }}
                                </span>
                            </div>


                            <div class="profile-action-buttons">
                                <button
                                    type="button"
                                    class="secondary-button"
                                    :disabled="
                                        isSavingProfile
                                        ||
                                        !profileDirty
                                    "
                                    @click="
                                        resetProfile
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
                                        Undo changes
                                    </span>
                                </button>


                                <button
                                    type="submit"
                                    class="primary-button save-profile-button"
                                    :disabled="
                                        isSavingProfile
                                        ||
                                        !profileDirty
                                    "
                                >
                                    <span
                                        v-if="
                                            isSavingProfile
                                        "
                                        class="button-spinner"
                                    />

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M5 4h12l2 2v14H5V4z"
                                        />

                                        <path
                                            d="M8 4v6h8V4"
                                        />

                                        <path
                                            d="M8 15h8"
                                        />
                                    </svg>

                                    <span>
                                        {{
                                            isSavingProfile
                                                ? 'Saving...'
                                                : 'Save profile'
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                </article>


                <!-- ====================================================
                     PROTECTED INFORMATION
                     ==================================================== -->

                <article class="detail-card account-panel protected-panel">
                    <div class="account-panel-header">
                        <div class="account-panel-heading">
                            <div class="account-panel-icon protected">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                    />

                                    <path
                                        d="M9 12l2 2 4-4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    PROTECTED ACCOUNT
                                </p>

                                <h3>
                                    Security-controlled information
                                </h3>

                                <p>
                                    These values cannot be changed
                                    from this Account Settings page.
                                </p>
                            </div>
                        </div>

                        <span class="protected-badge">
                            Protected
                        </span>
                    </div>


                    


                    <dl class="protected-list">
                        <div>
                            <dt>
                                Administrator ID
                            </dt>

                            <dd class="monospace-value">
                                #{{ account.id }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Phone number
                            </dt>

                            <dd>
                                {{
                                    account.phone_number
                                    ||
                                    '—'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Phone verification
                            </dt>

                            <dd>
                                <span
                                    class="verification-state"
                                    :class="{
                                        verified:
                                            account.phone_verified,

                                        unverified:
                                            !account.phone_verified,
                                    }"
                                >
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
                                            v-if="
                                                account.phone_verified
                                            "
                                            d="M8 12l3 3 5-6"
                                        />

                                        <path
                                            v-else
                                            d="M12 8v5M12 17h.01"
                                        />
                                    </svg>

                                    {{
                                        account.phone_verified
                                            ? 'Verified'
                                            : 'Not verified'
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Role
                            </dt>

                            <dd>
                                <span
                                    class="role-badge"
                                    :class="
                                        account.role
                                    "
                                >
                                    {{
                                        account.role_label
                                        ||
                                        humanize(
                                            account.role
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Account status
                            </dt>

                            <dd>
                                <span
                                    class="status-badge"
                                    :class="
                                        account.status
                                    "
                                >
                                    <span class="status-indicator" />

                                    {{
                                        account.status_label
                                        ||
                                        humanize(
                                            account.status
                                        )
                                    }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Last login
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        account.last_login_at
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Account created
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        account.created_at
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>
                                Last updated
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        account.updated_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>


                    <div class="protected-information-note">
                        <div class="protected-note-icon">
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
                                    d="M12 11v5M12 8h.01"
                                />
                            </svg>
                        </div>

                        <div>
                            <strong>
                                Why are these fields locked?
                            </strong>

                            <p>
                                Administrator role and account status
                                are security-sensitive. Phone-number
                                changes also require a dedicated
                                verification flow, so they are
                                intentionally excluded from this form.
                            </p>
                        </div>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 PASSWORD SECURITY
                 ======================================================== -->

            <section class="detail-card password-panel">
                <div class="password-panel-header">
                    <div class="account-panel-heading">
                        <div class="account-panel-icon password">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <rect
                                    x="5"
                                    y="10"
                                    width="14"
                                    height="11"
                                    rx="2"
                                />

                                <path
                                    d="M8 10V7a4 4 0 018 0v3"
                                />

                                <circle
                                    cx="12"
                                    cy="15"
                                    r="1"
                                />
                            </svg>
                        </div>

                        <div>
                            <p class="eyebrow">
                                SECURITY
                            </p>

                            <h3>
                                Change password
                            </h3>

                            <p>
                                Protect Administrator access with a
                                strong password that is different from
                                your current password.
                            </p>
                        </div>
                    </div>


                    <div class="security-session-badge">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M8 12l3 3 5-6"
                            />
                        </svg>

                        <span>
                            Current session remains active
                        </span>
                    </div>
                </div>


                <!-- PASSWORD SUCCESS -->

                <div
                    v-if="
                        passwordSuccess
                    "
                    class="password-message success"
                >
                    <div class="password-message-icon">
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

                    <div>
                        <strong>
                            Password changed
                        </strong>

                        <span>
                            {{ passwordSuccess }}
                        </span>
                    </div>
                </div>


                <div class="password-security-notice">
                    <div class="password-security-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />

                            <path
                                d="M12 8v4M12 16h.01"
                            />
                        </svg>
                    </div>

                    <div>
                        <strong>
                            Other sessions will be revoked
                        </strong>

                        <p>
                            After a successful password change,
                            StyleHub keeps this current Administrator
                            session active and revokes other signed-in
                            sessions.
                        </p>
                    </div>
                </div>


                <div class="password-content-grid">
                    <!-- =================================================
                         FORM
                         ================================================= -->

                    <form
                        class="password-form"
                        @submit.prevent="
                            changePassword
                        "
                    >
                        <!-- CURRENT PASSWORD -->

                        <label class="account-field full-width">
                            <span>
                                Current password
                            </span>

                            <div
                                class="account-input password-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            passwordErrors,
                                            'current_password'
                                        ),
                                }"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="11"
                                        rx="2"
                                    />

                                    <path
                                        d="M8 10V7a4 4 0 018 0v3"
                                    />
                                </svg>

                                <input
                                    v-model="
                                        password.current_password
                                    "
                                    :type="
                                        showCurrentPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    maxlength="255"
                                    autocomplete="current-password"
                                    placeholder="Enter current password"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    @input="
                                        clearPasswordFieldError(
                                            'current_password'
                                        )
                                    "
                                >

                                <button
                                    type="button"
                                    class="password-visibility-button"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    :aria-label="
                                        showCurrentPassword
                                            ? 'Hide current password'
                                            : 'Show current password'
                                    "
                                    @click="
                                        showCurrentPassword =
                                            !showCurrentPassword
                                    "
                                >
                                    <svg
                                        v-if="
                                            !showCurrentPassword
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M3 3l18 18"
                                        />

                                        <path
                                            d="M10.5 6.2A9.8 9.8 0 0112 6c6.5 0 10 6 10 6a17.8 17.8 0 01-3 3.7"
                                        />

                                        <path
                                            d="M6.2 6.2C3.5 8 2 12 2 12s3.5 6 10 6a9.8 9.8 0 004.1-.9"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        passwordErrors,
                                        'current_password'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        passwordErrors,
                                        'current_password'
                                    )
                                }}
                            </small>
                        </label>


                        <!-- NEW PASSWORD -->

                        <label class="account-field full-width">
                            <span>
                                New password
                            </span>

                            <div
                                class="account-input password-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            passwordErrors,
                                            'password'
                                        ),
                                }"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="11"
                                        rx="2"
                                    />

                                    <path
                                        d="M8 10V7a4 4 0 018 0v3"
                                    />
                                </svg>

                                <input
                                    v-model="
                                        password.password
                                    "
                                    :type="
                                        showNewPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    minlength="12"
                                    maxlength="64"
                                    autocomplete="new-password"
                                    placeholder="Create new password"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    @input="
                                        clearPasswordFieldError(
                                            'password'
                                        )
                                    "
                                >

                                <button
                                    type="button"
                                    class="password-visibility-button"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    :aria-label="
                                        showNewPassword
                                            ? 'Hide new password'
                                            : 'Show new password'
                                    "
                                    @click="
                                        showNewPassword =
                                            !showNewPassword
                                    "
                                >
                                    <svg
                                        v-if="
                                            !showNewPassword
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M3 3l18 18"
                                        />

                                        <path
                                            d="M10.5 6.2A9.8 9.8 0 0112 6c6.5 0 10 6 10 6a17.8 17.8 0 01-3 3.7"
                                        />

                                        <path
                                            d="M6.2 6.2C3.5 8 2 12 2 12s3.5 6 10 6a9.8 9.8 0 004.1-.9"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        passwordErrors,
                                        'password'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        passwordErrors,
                                        'password'
                                    )
                                }}
                            </small>
                        </label>


                        <!-- CONFIRM PASSWORD -->

                        <label class="account-field full-width">
                            <span>
                                Confirm new password
                            </span>

                            <div
                                class="account-input password-input"
                                :class="{
                                    invalid:
                                        firstValidationMessage(
                                            passwordErrors,
                                            'password_confirmation'
                                        ),
                                }"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="11"
                                        rx="2"
                                    />

                                    <path
                                        d="M8 10V7a4 4 0 018 0v3"
                                    />

                                    <path
                                        d="M9 16l2 2 4-4"
                                    />
                                </svg>

                                <input
                                    v-model="
                                        password.password_confirmation
                                    "
                                    :type="
                                        showConfirmPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    minlength="12"
                                    maxlength="64"
                                    autocomplete="new-password"
                                    placeholder="Confirm new password"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    @input="
                                        clearPasswordFieldError(
                                            'password_confirmation'
                                        )
                                    "
                                >

                                <button
                                    type="button"
                                    class="password-visibility-button"
                                    :disabled="
                                        isChangingPassword
                                    "
                                    :aria-label="
                                        showConfirmPassword
                                            ? 'Hide password confirmation'
                                            : 'Show password confirmation'
                                    "
                                    @click="
                                        showConfirmPassword =
                                            !showConfirmPassword
                                    "
                                >
                                    <svg
                                        v-if="
                                            !showConfirmPassword
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"
                                        />

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="2.5"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M3 3l18 18"
                                        />

                                        <path
                                            d="M10.5 6.2A9.8 9.8 0 0112 6c6.5 0 10 6 10 6a17.8 17.8 0 01-3 3.7"
                                        />

                                        <path
                                            d="M6.2 6.2C3.5 8 2 12 2 12s3.5 6 10 6a9.8 9.8 0 004.1-.9"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <small
                                v-if="
                                    firstValidationMessage(
                                        passwordErrors,
                                        'password_confirmation'
                                    )
                                "
                                class="field-error"
                            >
                                {{
                                    firstValidationMessage(
                                        passwordErrors,
                                        'password_confirmation'
                                    )
                                }}
                            </small>
                        </label>


                        <div class="password-submit-area">
                            <button
                                type="submit"
                                class="primary-button password-submit-button"
                                :disabled="
                                    isChangingPassword
                                "
                            >
                                <span
                                    v-if="
                                        isChangingPassword
                                    "
                                    class="button-spinner"
                                />

                                <svg
                                    v-else
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                                    />

                                    <path
                                        d="M8 12l3 3 5-6"
                                    />
                                </svg>

                                <span>
                                    {{
                                        isChangingPassword
                                            ? 'Changing password...'
                                            : 'Change password'
                                    }}
                                </span>
                            </button>
                        </div>
                    </form>


                    <!-- =================================================
                         REQUIREMENTS
                         ================================================= -->

                    <aside class="password-requirements-card">
                        <div class="requirements-heading">
                            <div>
                                <span>
                                    PASSWORD STRENGTH
                                </span>

                                <strong>
                                    {{ passwordStrengthLabel }}
                                </strong>
                            </div>

                            <span class="strength-score">
                                {{ passwordStrengthCount }}/5
                            </span>
                        </div>


                        <div class="strength-track">
                            <div
                                class="strength-fill"
                                :style="{
                                    width:
                                        `${
                                            passwordStrengthPercentage
                                        }%`,
                                }"
                            />
                        </div>


                        <div class="requirements-list">
                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.length,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.length
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    12–64 characters
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.uppercase,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.uppercase
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    At least one uppercase letter
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.lowercase,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.lowercase
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    At least one lowercase letter
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.number,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.number
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    At least one number
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.symbol,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.symbol
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    At least one symbol
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.different,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.different
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    Different from current password
                                </span>
                            </div>


                            <div
                                :class="{
                                    complete:
                                        passwordRequirements.confirmation,
                                }"
                            >
                                <span class="requirement-check">
                                    <svg
                                        v-if="
                                            passwordRequirements.confirmation
                                        "
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M6 12l4 4 8-9"
                                        />
                                    </svg>
                                </span>

                                <span>
                                    Confirmation matches
                                </span>
                            </div>
                        </div>


                        <div class="password-audit-note">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M6 3h9l3 3v15H6V3z"
                                />

                                <path
                                    d="M14 3v4h4"
                                />

                                <path
                                    d="M9 12h6M9 16h4"
                                />
                            </svg>

                            <span>
                                Successful password changes are
                                recorded in the Administrator
                                audit log.
                            </span>
                        </div>
                    </aside>
                </div>
            </section>
        </template>
    </div>
</template>


<style scoped src="./admin-page.css"></style>

<style scoped>
/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.account-settings-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRODUCTION
|--------------------------------------------------------------------------
*/

.account-intro {
    min-height: 130px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 22px;

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
            circle at 94% 8%,
            rgba(
                214,
                154,
                45,
                0.08
            ),
            transparent 28%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.account-intro-copy {
    min-width: 0;

    flex: 1;
}

.account-intro-label {
    width: fit-content;

    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.1px;

    text-transform:
        uppercase;
}

.account-intro-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

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

.account-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 23px;
    font-weight: 800;

    line-height: 30px;

    letter-spacing:
        -0.65px;
}

.account-intro p {
    max-width: 700px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}

.account-refresh-button {
    min-height: 44px;
}

.account-refresh-button svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.account-error {
    align-items: center;

    margin: 0;
}

.account-error-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.account-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.account-error-copy {
    min-width: 0;

    flex: 1;
}

.account-error-copy strong {
    display: block;

    font-size: 10px;
}

.account-error-copy p {
    margin:
        2px
        0
        0;
}


/*
|--------------------------------------------------------------------------
| LOADING
|--------------------------------------------------------------------------
*/

.account-loading-card {
    min-height: 350px;
}

.account-loading-icon {
    width: 54px;
    height: 54px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 12px;

    border-radius: 16px;

    background:
        var(--sh-surface-muted);
}

.account-loading-icon
.spinner {
    margin: 0;
}


/*
|--------------------------------------------------------------------------
| ADMINISTRATOR HERO
|--------------------------------------------------------------------------
*/

.administrator-hero {
    position: relative;

    overflow: hidden;

    min-height: 165px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 24px;

    padding:
        21px
        22px;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        var(--sh-radius-xl);

    background:
        radial-gradient(
            circle at 94% 4%,
            rgba(
                214,
                154,
                45,
                0.09
            ),
            transparent 30%
        ),
        linear-gradient(
            145deg,
            #ffffff,
            #fbfcfd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.administrator-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.administrator-avatar {
    width: 68px;
    height: 68px;

    flex: 0 0 68px;

    overflow: hidden;

    display: flex;
    align-items: center;
    justify-content: center;

    border:
        1px solid
        rgba(
            214,
            154,
            45,
            0.22
        );

    border-radius: 19px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary),
            var(--sh-primary-2)
        );

    color:
        var(--sh-accent);

    font-size: 20px;
    font-weight: 800;

    box-shadow:
        0 10px 24px
        rgba(
            23,
            32,
            42,
            0.13
        );
}

.administrator-avatar img {
    width: 100%;
    height: 100%;

    object-fit: cover;
}

.administrator-copy {
    min-width: 0;

    flex: 1;
}

.administrator-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.05px;

    text-transform:
        uppercase;
}

.administrator-eyebrow-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

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

.administrator-id {
    padding:
        2px
        5px;

    border-radius: 5px;

    background:
        #f1f3f6;

    color:
        var(--sh-text-3);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7px;

    letter-spacing: 0;
}

.administrator-copy h2 {
    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text);

    font-size: 25px;
    font-weight: 800;

    line-height: 31px;

    letter-spacing:
        -0.7px;
}


/*
|--------------------------------------------------------------------------
| HERO META
|--------------------------------------------------------------------------
*/

.administrator-meta {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.administrator-meta-item {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.administrator-meta-item span {
    overflow-wrap: anywhere;
}

.administrator-meta-item svg {
    width: 13px;
    height: 13px;

    flex: 0 0 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.administrator-meta-divider {
    width: 3px;
    height: 3px;

    flex: 0 0 3px;

    border-radius: 999px;

    background:
        #c9ced5;
}


/*
|--------------------------------------------------------------------------
| HERO STATUS
|--------------------------------------------------------------------------
*/

.administrator-statuses {
    flex: 0 0 auto;

    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 8px;

    flex-wrap: wrap;
}

.administrator-role {
    gap: 5px;

    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.administrator-role svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.administrator-status {
    gap: 5px;
}

.status-indicator {
    width: 5px;
    height: 5px;

    flex: 0 0 5px;

    border-radius: 999px;

    background:
        currentColor;
}

.status-badge.active {
    border-color:
        #d4ebdf !important;

    background:
        var(--sh-success-bg) !important;

    color:
        var(--sh-success) !important;
}

.status-badge.suspended {
    border-color:
        #eed3d3 !important;

    background:
        var(--sh-danger-bg) !important;

    color:
        var(--sh-danger) !important;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.account-summary-grid {
    display: grid;

    grid-template-columns:
        repeat(
            4,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.account-summary-card {
    min-width: 0;

    min-height: 104px;

    display: flex;
    align-items: center;

    gap: 12px;

    padding: 14px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 13px;

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);
}

.account-summary-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;
}

.account-summary-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.account-summary-icon.success {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.account-summary-icon.warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.account-summary-icon.account-created {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.account-summary-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.account-summary-copy > span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.account-summary-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 18px;
    font-weight: 800;

    line-height: 22px;
}

.account-summary-copy
.text-value {
    font-size: 13px;

    line-height: 18px;
}

.account-summary-copy
.date-value {
    font-size: 9.5px;

    line-height: 15px;
}

.account-summary-copy small {
    margin-top: 3px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| MAIN SETTINGS GRID
|--------------------------------------------------------------------------
*/

.account-settings-grid {
    display: grid;

    grid-template-columns:
        minmax(
            0,
            1.35fr
        )
        minmax(
            330px,
            0.65fr
        );

    gap: 14px;
}

.account-panel {
    min-width: 0;

    padding: 0;

    overflow: hidden;
}

.account-panel-header {
    min-height: 74px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 13px;

    padding:
        14px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.account-panel-heading {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.account-panel-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.account-panel-icon svg {
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

.account-panel-icon.profile {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.account-panel-icon.protected {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.account-panel-icon.password {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.account-panel-heading
.eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.account-panel-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.account-panel-heading
p:last-child {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 13px;
}

.unsaved-profile-badge,
.protected-badge {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border-radius: 999px;

    font-size: 7.5px;
    font-weight: 800;

    white-space: nowrap;
}

.unsaved-profile-badge {
    border:
        1px solid
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.protected-badge {
    border:
        1px solid
        #d4ebdf;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}


/*
|--------------------------------------------------------------------------
| PROFILE MESSAGE
|--------------------------------------------------------------------------
*/

.profile-message,
.password-message {
    display: flex;
    align-items: flex-start;

    gap: 8px;

    margin:
        13px
        16px
        0;

    padding:
        9px
        10px;

    border:
        1px solid
        #d4ebdf;

    border-radius: 9px;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.profile-message-icon,
.password-message-icon {
    width: 25px;
    height: 25px;

    flex: 0 0 25px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 7px;

    background:
        #ffffff;
}

.profile-message-icon svg,
.password-message-icon svg {
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

.profile-message
> div:last-child,
.password-message
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.profile-message strong,
.password-message strong {
    font-size: 8.5px;
}

.profile-message span,
.password-message span {
    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| PROFILE FORM
|--------------------------------------------------------------------------
*/

.profile-form {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 13px;

    padding:
        15px
        16px
        16px;
}

.account-field {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 6px;
}

.account-field.full-width {
    grid-column:
        1
        / -1;
}

.account-field
> span:first-child {
    color:
        var(--sh-text-2);

    font-size: 8.5px;
    font-weight: 750;
}

.account-input {
    min-height: 41px;

    display: flex;
    align-items: center;

    gap: 8px;

    padding:
        0
        10px;

    border:
        1px solid
        var(--sh-border-strong);

    border-radius: 9px;

    background:
        #ffffff;

    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.account-input:focus-within {
    border-color:
        rgba(
            214,
            154,
            45,
            0.7
        );

    box-shadow:
        0 0 0 3px
        rgba(
            214,
            154,
            45,
            0.08
        );
}

.account-input.invalid {
    border-color:
        #dfa9a9;
}

.account-input
> svg {
    width: 14px;
    height: 14px;

    flex: 0 0 14px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.account-input input {
    min-width: 0;
    width: 100%;

    height: 39px;

    padding: 0;

    border: 0;

    outline: 0;

    background:
        transparent;

    color:
        var(--sh-text);

    font: inherit;

    font-size: 9.5px;
}

.account-input input::placeholder {
    color:
        #b1b7bf;
}

.account-input:has(
    input:disabled
) {
    opacity: 0.65;

    background:
        #f8f9fa;
}

.field-error {
    color:
        var(--sh-danger);

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| EMAIL WARNING
|--------------------------------------------------------------------------
*/

.email-warning {
    display: flex;
    align-items: flex-start;

    gap: 5px;

    color:
        var(--sh-warning);

    font-size: 7.8px;

    line-height: 13px;
}

.email-warning svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    margin-top: 1px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| PROFILE ACTIONS
|--------------------------------------------------------------------------
*/

.profile-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding-top: 3px;
}

.profile-change-state {
    display: flex;
    align-items: center;

    gap: 6px;

    color:
        var(--sh-text-3);

    font-size: 8px;
}

.change-state-dot {
    width: 6px;
    height: 6px;

    flex: 0 0 6px;

    border-radius: 999px;

    background:
        #c2c7ce;
}

.change-state-dot.changed {
    background:
        var(--sh-accent);

    box-shadow:
        0 0 0 3px
        rgba(
            214,
            154,
            45,
            0.1
        );
}

.profile-action-buttons {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 8px;
}

.profile-action-buttons
button {
    min-height: 38px;
}

.profile-action-buttons svg {
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

.save-profile-button {
    min-width: 118px;
}


/*
|--------------------------------------------------------------------------
| PROTECTED
|--------------------------------------------------------------------------
*/

.protected-status-card {
    display: flex;
    align-items: center;

    gap: 10px;

    margin:
        13px
        15px
        5px;

    padding:
        11px
        12px;

    border:
        1px solid
        #d4ebdf;

    border-radius: 11px;

    background:
        var(--sh-success-bg);
}

.protected-status-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;

    color:
        var(--sh-success);
}

.protected-status-icon svg {
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

.protected-status-card
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.protected-status-card span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.protected-status-card strong {
    color:
        var(--sh-success);

    font-size: 10px;
}

.protected-status-card small {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}


/*
|--------------------------------------------------------------------------
| PROTECTED LIST
|--------------------------------------------------------------------------
*/

.protected-list {
    margin: 0;

    padding:
        4px
        15px;
}

.protected-list > div {
    min-height: 47px;

    display: grid;

    grid-template-columns:
        minmax(
            120px,
            0.8fr
        )
        minmax(
            0,
            1.2fr
        );

    align-items: center;

    gap: 12px;

    padding:
        8px
        0;

    border-bottom:
        1px solid
        #edf0f3;
}

.protected-list
> div:last-child {
    border-bottom: 0;
}

.protected-list dt {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 650;
}

.protected-list dd {
    min-width: 0;

    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 9px;
    font-weight: 650;

    text-align: right;
}

.monospace-value {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
}

.verification-state {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    font-size: 8px;
    font-weight: 750;
}

.verification-state svg {
    width: 12px;
    height: 12px;

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
        var(--sh-success);
}

.verification-state.unverified {
    color:
        var(--sh-warning);
}

.role-badge.admin {
    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}


/*
|--------------------------------------------------------------------------
| PROTECTED NOTE
|--------------------------------------------------------------------------
*/

.protected-information-note {
    display: flex;
    align-items: flex-start;

    gap: 8px;

    margin:
        5px
        15px
        15px;

    padding:
        10px
        11px;

    border-radius: 10px;

    background:
        var(--sh-surface-muted);
}

.protected-note-icon {
    width: 28px;
    height: 28px;

    flex: 0 0 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-text-3);
}

.protected-note-icon svg {
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

.protected-information-note
> div:last-child {
    min-width: 0;
}

.protected-information-note strong {
    color:
        var(--sh-text-2);

    font-size: 8px;
}

.protected-information-note p {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| PASSWORD PANEL
|--------------------------------------------------------------------------
*/

.password-panel {
    padding: 0;

    overflow: hidden;
}

.password-panel-header {
    min-height: 77px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    padding:
        14px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.security-session-badge {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    min-height: 27px;

    padding:
        0
        9px;

    border:
        1px solid
        #d4ebdf;

    border-radius: 999px;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);

    font-size: 7.5px;
    font-weight: 750;

    white-space: nowrap;
}

.security-session-badge svg {
    width: 12px;
    height: 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| PASSWORD NOTICE
|--------------------------------------------------------------------------
*/

.password-security-notice {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin:
        14px
        16px
        0;

    padding:
        10px
        11px;

    border:
        1px solid
        #d7e2f7;

    border-radius: 10px;

    background:
        var(--sh-info-bg);
}

.password-security-icon {
    width: 29px;
    height: 29px;

    flex: 0 0 29px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-info);
}

.password-security-icon svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.password-security-notice strong {
    color:
        #365a9c;

    font-size: 8.5px;
}

.password-security-notice p {
    margin:
        2px
        0
        0;

    color:
        #607ca9;

    font-size: 8px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| PASSWORD CONTENT
|--------------------------------------------------------------------------
*/

.password-content-grid {
    display: grid;

    grid-template-columns:
        minmax(
            0,
            1.2fr
        )
        minmax(
            280px,
            0.8fr
        );

    gap: 16px;

    padding:
        16px;
}

.password-form {
    display: flex;
    flex-direction: column;

    gap: 13px;
}

.password-input {
    padding-right: 5px;
}

.password-visibility-button {
    width: 31px;
    height: 31px;

    flex: 0 0 31px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 0;

    border-radius: 7px;

    background:
        transparent;

    color:
        var(--sh-text-3);

    cursor: pointer;
}

.password-visibility-button:hover:not(:disabled) {
    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-2);
}

.password-visibility-button:disabled {
    cursor: not-allowed;

    opacity: 0.5;
}

.password-visibility-button svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.password-submit-area {
    display: flex;
    justify-content: flex-end;

    padding-top: 3px;
}

.password-submit-button {
    min-width: 148px;

    min-height: 39px;
}

.password-submit-button svg {
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


/*
|--------------------------------------------------------------------------
| PASSWORD REQUIREMENTS
|--------------------------------------------------------------------------
*/

.password-requirements-card {
    align-self: start;

    padding:
        14px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 12px;

    background:
        var(--sh-surface-muted);
}

.requirements-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 10px;
}

.requirements-heading
> div:first-child
> span {
    display: block;

    color:
        var(--sh-text-3);

    font-size: 7px;
    font-weight: 800;

    letter-spacing: 0.8px;
}

.requirements-heading strong {
    display: block;

    margin-top: 2px;

    color:
        var(--sh-text);

    font-size: 11px;
}

.strength-score {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        7px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-text-3);

    font-size: 7.5px;
    font-weight: 750;
}

.strength-track {
    width: 100%;
    height: 5px;

    overflow: hidden;

    margin-top: 10px;

    border-radius: 999px;

    background:
        #e5e9ed;
}

.strength-fill {
    height: 100%;

    border-radius: inherit;

    background:
        var(--sh-accent);

    transition:
        width 0.18s ease;
}

.requirements-list {
    display: flex;
    flex-direction: column;

    gap: 8px;

    margin-top: 13px;
}

.requirements-list > div {
    display: flex;
    align-items: center;

    gap: 7px;

    color:
        var(--sh-text-3);

    font-size: 8px;

    transition:
        color 0.15s ease;
}

.requirements-list
> div.complete {
    color:
        var(--sh-success);
}

.requirement-check {
    width: 17px;
    height: 17px;

    flex: 0 0 17px;

    display: flex;
    align-items: center;
    justify-content: center;

    border:
        1px solid
        #d9dee4;

    border-radius: 999px;

    background:
        #ffffff;
}

.requirements-list
> div.complete
.requirement-check {
    border-color:
        #cce5d8;

    background:
        var(--sh-success-bg);
}

.requirement-check svg {
    width: 9px;
    height: 9px;

    fill: none;

    stroke:
        var(--sh-success);

    stroke-width: 2.2;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| AUDIT NOTE
|--------------------------------------------------------------------------
*/

.password-audit-note {
    display: flex;
    align-items: flex-start;

    gap: 6px;

    margin-top: 14px;

    padding-top: 11px;

    border-top:
        1px solid
        var(--sh-border);

    color:
        var(--sh-text-3);

    font-size: 7.5px;

    line-height: 13px;
}

.password-audit-note svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| SPINNER
|--------------------------------------------------------------------------
*/

.button-spinner {
    width: 13px;
    height: 13px;

    border:
        2px solid
        currentColor;

    border-right-color:
        transparent;

    border-radius: 999px;

    animation:
        account-button-spin
        0.7s
        linear
        infinite;
}

.spinning {
    animation:
        account-spin
        0.8s
        linear
        infinite;
}

@keyframes account-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}

@keyframes account-button-spin {
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

@media (max-width: 1100px) {
    .account-summary-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }

    .account-settings-grid {
        grid-template-columns:
            1fr;
    }
}


@media (max-width: 900px) {
    .administrator-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .administrator-statuses {
        justify-content:
            flex-start;
    }

    .password-content-grid {
        grid-template-columns:
            1fr;
    }
}


@media (max-width: 720px) {
    .account-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .account-refresh-button {
        width: 100%;
    }

    .profile-form {
        grid-template-columns:
            1fr;
    }

    .account-field.full-width {
        grid-column: auto;
    }

    .profile-actions {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .profile-action-buttons {
        width: 100%;
    }

    .profile-action-buttons button {
        flex: 1;
    }
}


@media (max-width: 680px) {
    .account-intro {
        padding: 17px;
    }

    .account-intro h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .account-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .account-error
    .retry-button {
        width: 100%;
    }

    .administrator-hero {
        padding: 17px;
    }

    .administrator-main {
        align-items:
            flex-start;
    }

    .administrator-avatar {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;

        font-size: 17px;
    }

    .administrator-copy h2 {
        font-size: 20px;

        line-height: 25px;
    }

    .administrator-meta {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .administrator-meta-divider {
        display: none;
    }

    .account-summary-grid {
        grid-template-columns:
            1fr;
    }

    .account-panel-header,
    .password-panel-header {
        align-items:
            flex-start;
    }

    .account-panel-heading {
        align-items:
            flex-start;
    }

    .protected-list > div {
        grid-template-columns:
            1fr;

        gap: 4px;

        align-items:
            flex-start;
    }

    .protected-list dd {
        text-align: left;
    }

    .profile-action-buttons {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .profile-action-buttons button {
        width: 100%;
    }

    .password-panel-header {
        flex-direction:
            column;
    }

    .security-session-badge {
        margin-left: 48px;
    }

    .password-content-grid {
        padding: 14px;
    }

    .password-submit-area {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .password-submit-button {
        width: 100%;
    }
}
</style>