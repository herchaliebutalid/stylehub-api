<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref,
} from 'vue';

import { useRouter } from 'vue-router';

import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const configuration = ref(null);
const templates = ref([]);
const generatedAt = ref(null);

const isLoading = ref(false);
const pageError = ref('');

const textareaRefs = {};


/*
|--------------------------------------------------------------------------
| TEMPLATE SUMMARY
|--------------------------------------------------------------------------
*/

const hasTemplates = computed(
    () =>
        templates.value.length > 0,
);

const modifiedTemplateCount = computed(
    () =>
        templates.value.filter(
            (template) =>
                isDirty(template),
        ).length,
);

const invalidTemplateCount = computed(
    () =>
        templates.value.filter(
            (template) =>
                template.draft.trim() === ''
                ||
                isTooLong(template),
        ).length,
);


/*
|--------------------------------------------------------------------------
| TEXTAREA REFS
|--------------------------------------------------------------------------
*/

function setTextareaRef(
    type,
    element,
) {
    if (element) {
        textareaRefs[type] =
            element;

        return;
    }

    delete textareaRefs[type];
}


/*
|--------------------------------------------------------------------------
| TEMPLATE HELPERS
|--------------------------------------------------------------------------
*/

function characterCount(value) {
    return [
        ...String(
            value ?? '',
        ),
    ].length;
}

function isDirty(template) {
    return template.draft
        !== template.template;
}

function isTooLong(template) {
    return characterCount(
        template.draft,
    )
        >
        Number(
            template.max_characters
            ?? 320,
        );
}

function canSave(template) {
    return !template.isSaving
        && isDirty(template)
        && template.draft.trim() !== ''
        && !isTooLong(template);
}

function characterPercentage(
    template,
) {
    const maximum =
        Number(
            template.max_characters
            ?? 320,
        );

    if (
        !Number.isFinite(maximum)
        ||
        maximum <= 0
    ) {
        return 0;
    }

    return Math.min(
        100,
        (
            characterCount(
                template.draft,
            )
            / maximum
        )
        * 100,
    );
}

function clearTemplateMessage(
    template,
) {
    template.error = '';
    template.success = '';
}


/*
|--------------------------------------------------------------------------
| FORMATTERS
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

function statusLabel(value) {
    return value
        ? 'Enabled'
        : 'Disabled';
}

function configurationLabel(value) {
    return value
        ? 'Configured'
        : 'Not configured';
}

function minutesLabel(value) {
    const minutes =
        Number(value);

    if (
        !Number.isFinite(minutes)
    ) {
        return '—';
    }

    if (
        minutes === 60
    ) {
        return '1 hour';
    }

    if (
        minutes > 0
        &&
        minutes % 1440 === 0
    ) {
        const days =
            minutes / 1440;

        return `${days} ${
            days === 1
                ? 'day'
                : 'days'
        }`;
    }

    if (
        minutes > 0
        &&
        minutes % 60 === 0
    ) {
        const hours =
            minutes / 60;

        return `${hours} ${
            hours === 1
                ? 'hour'
                : 'hours'
        }`;
    }

    return `${minutes} minutes`;
}

function formatDateTime(value) {
    if (!value) {
        return 'Never edited';
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


/*
|--------------------------------------------------------------------------
| NORMALIZE TEMPLATE
|--------------------------------------------------------------------------
*/

function normalizeTemplate(template) {
    return {
        ...template,

        draft:
            template.template
            ?? '',

        isSaving:
            false,

        error:
            '',

        success:
            '',
    };
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
| LOAD SYSTEM SETTINGS
|--------------------------------------------------------------------------
*/

async function loadSettings() {
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
                '/admin/system-settings',
                {
                    token:
                        auth.token,
                },
            );

        const sms =
            response
                ?.data
                ?.sms;

        if (
            !sms
            ||
            typeof sms !== 'object'
            ||
            !Array.isArray(
                sms.templates,
            )
        ) {
            throw new Error(
                'The System Settings response was invalid.',
            );
        }

        configuration.value =
            sms.configuration
            ?? null;

        templates.value =
            sms.templates.map(
                normalizeTemplate,
            );

        generatedAt.value =
            response
                ?.generated_at
            ?? null;
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
            error
                ?.data
                ?.message
            ??
            error
                ?.message
            ??
            'Unable to load System Settings.';
    } finally {
        isLoading.value =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| PLACEHOLDER INSERTION
|--------------------------------------------------------------------------
*/

async function insertPlaceholder(
    template,
    placeholder,
) {
    const textarea =
        textareaRefs[
            template.type
        ];

    const current =
        String(
            template.draft
            ?? '',
        );

    const start =
        textarea?.selectionStart
        ?? current.length;

    const end =
        textarea?.selectionEnd
        ?? start;

    template.draft =
        current.slice(
            0,
            start,
        )
        +
        placeholder
        +
        current.slice(
            end,
        );

    clearTemplateMessage(
        template,
    );

    await nextTick();

    const updatedTextarea =
        textareaRefs[
            template.type
        ];

    if (!updatedTextarea) {
        return;
    }

    const cursor =
        start
        +
        placeholder.length;

    updatedTextarea.focus();

    updatedTextarea.setSelectionRange(
        cursor,
        cursor,
    );
}


/*
|--------------------------------------------------------------------------
| UNDO TEMPLATE
|--------------------------------------------------------------------------
*/

function undoTemplate(template) {
    template.draft =
        template.template;

    clearTemplateMessage(
        template,
    );
}


/*
|--------------------------------------------------------------------------
| SAVE TEMPLATE
|--------------------------------------------------------------------------
*/

async function saveTemplate(template) {
    if (
        !canSave(template)
    ) {
        return;
    }

    template.isSaving =
        true;

    template.error =
        '';

    template.success =
        '';

    pageError.value =
        '';

    try {
        const response =
            await apiRequest(
                `/admin/system-settings/sms-templates/${encodeURIComponent(
                    template.type,
                )}`,
                {
                    method:
                        'PUT',

                    token:
                        auth.token,

                    body: {
                        template:
                            template.draft,
                    },
                },
            );

        const saved =
            response
                ?.data
                ?.template;

        if (!saved) {
            throw new Error(
                'The updated SMS template response was invalid.',
            );
        }

        Object.assign(
            template,

            normalizeTemplate(
                saved,
            ),

            {
                success:
                    response
                        ?.message
                    ??
                    'SMS template updated successfully.',
            },
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

        if (
            error?.status === 422
        ) {
            template.error =
                error
                    ?.data
                    ?.errors
                    ?.template
                    ?.[0]
                ??
                error
                    ?.data
                    ?.message
                ??
                'The SMS template is invalid.';

            return;
        }

        if (
            error?.status === 404
        ) {
            template.error =
                'This SMS template is not editable.';

            return;
        }

        template.error =
            error
                ?.data
                ?.message
            ??
            error
                ?.message
            ??
            'Unable to update this SMS template.';
    } finally {
        template.isSaving =
            false;
    }
}


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadSettings();
});
</script>


<template>
    <div class="settings-page">
        <!-- ============================================================
             INTRODUCTION
             ============================================================ -->

        <section class="settings-intro">
            <div class="settings-intro-copy">
                <div class="settings-intro-label">
                    <span class="settings-intro-dot" />

                    Administrator configuration
                </div>

                <h2>
                    System settings
                </h2>

                <p>
                    Review the safe operational SMS configuration
                    exposed by StyleHub and manage the customer-facing
                    message templates used for future notifications.
                </p>
            </div>


            <div class="settings-intro-actions">
                <div
                    v-if="
                        generatedAt
                    "
                    class="settings-loaded-card"
                >
                    <div class="settings-loaded-icon">
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
                    </div>

                    <div>
                        <span>
                            Configuration loaded
                        </span>

                        <strong>
                            {{
                                formatDateTime(
                                    generatedAt
                                )
                            }}
                        </strong>
                    </div>
                </div>


                <button
                    type="button"
                    class="secondary-button settings-refresh-button"
                    :disabled="
                        isLoading
                    "
                    @click="
                        loadSettings
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
             SECURITY NOTICE
             ============================================================ -->

        <section class="security-banner">
            <div class="security-banner-icon">
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

            <div class="security-banner-copy">
                <strong>
                    Provider credentials remain server-controlled
                </strong>

                <p>
                    Semaphore API credentials, provider endpoints and
                    the actual sender name are never returned to or
                    editable from the Administrator frontend.
                </p>
            </div>

            <span class="security-badge">
                Protected
            </span>
        </section>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                pageError
            "
            class="error-alert settings-error"
            role="alert"
        >
            <div class="settings-error-icon">
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

            <div class="settings-error-copy">
                <strong>
                    Unable to load System Settings
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
                    loadSettings
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
                !configuration
            "
            class="loading-card settings-loading-card"
        >
            <div class="settings-loading-icon">
                <div class="spinner" />
            </div>

            <h3>
                Loading system configuration
            </h3>

            <p>
                Retrieving safe SMS operational settings
                and customer message templates.
            </p>
        </section>


        <!-- ============================================================
             SETTINGS CONTENT
             ============================================================ -->

        <template
            v-else-if="
                configuration
            "
        >
            <!-- ========================================================
                 CONFIGURATION HEADING
                 ======================================================== -->

            <section class="settings-section-heading">
                <div>
                    <p class="eyebrow">
                        SMS OPERATIONS
                    </p>

                    <h3>
                        Configuration status
                    </h3>

                    <p>
                        These values come directly from server
                        configuration and are read-only in the
                        Administrator web application.
                    </p>
                </div>

                <span class="readonly-badge">
                    Read only
                </span>
            </section>


            <!-- ========================================================
                 CONFIGURATION GRID
                 ======================================================== -->

            <section class="configuration-grid">
                <!-- SMS DELIVERY -->

                <article class="configuration-card">
                    <div
                        class="configuration-icon"
                        :class="
                            configuration.enabled
                                ? 'enabled'
                                : 'disabled'
                        "
                    >
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 5h16v12H9l-5 4V5z"
                            />

                            <path
                                v-if="
                                    configuration.enabled
                                "
                                d="M8 11l2 2 5-5"
                            />

                            <path
                                v-else
                                d="M8 9l6 6M14 9l-6 6"
                            />
                        </svg>
                    </div>

                    <div class="configuration-card-copy">
                        <span>
                            SMS delivery
                        </span>

                        <strong
                            :class="{
                                enabled:
                                    configuration.enabled,

                                disabled:
                                    !configuration.enabled,
                            }"
                        >
                            {{
                                statusLabel(
                                    configuration.enabled
                                )
                            }}
                        </strong>

                        <small>
                            Global outbound SMS state
                        </small>
                    </div>
                </article>


                <!-- PROVIDER -->

                <article class="configuration-card">
                    <div class="configuration-icon provider">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 4h16v16H4z"
                            />

                            <path
                                d="M8 8h8M8 12h8M8 16h5"
                            />
                        </svg>
                    </div>

                    <div class="configuration-card-copy">
                        <span>
                            SMS provider
                        </span>

                        <strong class="provider-value">
                            {{
                                humanize(
                                    configuration.provider
                                )
                            }}
                        </strong>

                        <small>
                            Provider identity only
                        </small>
                    </div>
                </article>


                <!-- SENDER -->

                <article class="configuration-card">
                    <div
                        class="configuration-icon"
                        :class="
                            configuration
                                .sender_name_configured
                                ? 'enabled'
                                : 'warning'
                        "
                    >
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

                    <div class="configuration-card-copy">
                        <span>
                            Sender name
                        </span>

                        <strong>
                            {{
                                configurationLabel(
                                    configuration
                                        .sender_name_configured
                                )
                            }}
                        </strong>

                        <small>
                            Actual sender value remains hidden
                        </small>
                    </div>
                </article>


                <!-- LIVE TEST -->

                <article class="configuration-card">
                    <div
                        class="configuration-icon"
                        :class="
                            configuration
                                .live_test_enabled
                                ? 'enabled'
                                : 'disabled'
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
                    </div>

                    <div class="configuration-card-copy">
                        <span>
                            Live test gate
                        </span>

                        <strong>
                            {{
                                statusLabel(
                                    configuration
                                        .live_test_enabled
                                )
                            }}
                        </strong>

                        <small>
                            Controlled provider test setting
                        </small>
                    </div>
                </article>


                <!-- REMINDER LEAD -->

                <article class="configuration-card">
                    <div class="configuration-icon reminder">
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
                    </div>

                    <div class="configuration-card-copy">
                        <span>
                            Reminder lead
                        </span>

                        <strong>
                            {{
                                minutesLabel(
                                    configuration
                                        .reminders
                                        ?.lead_minutes
                                )
                            }}
                        </strong>

                        <small>
                            Target reminder window
                        </small>
                    </div>
                </article>


                <!-- MINIMUM REMINDER LEAD -->

                <article class="configuration-card">
                    <div class="configuration-icon minimum">
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

                            <path
                                d="M8 19h8"
                            />
                        </svg>
                    </div>

                    <div class="configuration-card-copy">
                        <span>
                            Minimum reminder lead
                        </span>

                        <strong>
                            {{
                                minutesLabel(
                                    configuration
                                        .reminders
                                        ?.minimum_lead_minutes
                                )
                            }}
                        </strong>

                        <small>
                            Too-close bookings are not reminded
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 CONFIGURATION EXPLANATION
                 ======================================================== -->

            <section class="configuration-information">
                <div class="configuration-information-icon">
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
                        Operational configuration is managed on the server
                    </strong>

                    <p>
                        This screen intentionally exposes safe status
                        information only. Changing provider credentials,
                        endpoints, sender configuration or reminder timing
                        requires server configuration changes.
                    </p>
                </div>
            </section>


            <!-- ========================================================
                 TEMPLATE HEADING
                 ======================================================== -->

            <section class="settings-section-heading templates-heading">
                <div>
                    <p class="eyebrow">
                        SMS TEMPLATES
                    </p>

                    <h3>
                        Customer message templates
                    </h3>

                    <p>
                        Edit the approved templates used for future
                        StyleHub customer notifications.
                    </p>
                </div>


                <div class="template-summary">
                    <div class="template-summary-item">
                        <span>
                            Templates
                        </span>

                        <strong>
                            {{ templates.length }}
                        </strong>
                    </div>

                    <div
                        class="template-summary-item"
                        :class="{
                            changed:
                                modifiedTemplateCount > 0,
                        }"
                    >
                        <span>
                            Unsaved
                        </span>

                        <strong>
                            {{ modifiedTemplateCount }}
                        </strong>
                    </div>

                    <div
                        class="template-summary-item"
                        :class="{
                            invalid:
                                invalidTemplateCount > 0,
                        }"
                    >
                        <span>
                            Invalid
                        </span>

                        <strong>
                            {{ invalidTemplateCount }}
                        </strong>
                    </div>
                </div>
            </section>


            <!-- ========================================================
                 SNAPSHOT INFORMATION
                 ======================================================== -->

            <section class="snapshot-banner">
                <div class="snapshot-banner-icon">
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
                            d="M9 11h6M9 15h6"
                        />
                    </svg>
                </div>

                <div class="snapshot-banner-copy">
                    <strong>
                        Template changes affect future SMS only
                    </strong>

                    <p>
                        Notifications already queued retain their
                        original message snapshot. A controlled retry
                        therefore resends the same original message.
                    </p>
                </div>

                <span class="snapshot-badge">
                    Snapshot safe
                </span>
            </section>


            <!-- ========================================================
                 NO TEMPLATES
                 ======================================================== -->

            <div
                v-if="
                    !hasTemplates
                "
                class="empty-state templates-empty-state"
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
                    No editable templates
                </h3>

                <p>
                    No customer-facing SMS templates are
                    currently available for editing.
                </p>
            </div>


            <!-- ========================================================
                 TEMPLATE LIST
                 ======================================================== -->

            <section
                v-else
                class="templates-list"
            >
                <article
                    v-for="
                        (template, index)
                        in templates
                    "
                    :key="
                        template.type
                    "
                    class="template-card"
                    :class="{
                        dirty:
                            isDirty(
                                template
                            ),

                        invalid:
                            isTooLong(
                                template
                            )
                            ||
                            template.draft.trim()
                                === '',
                    }"
                >
                    <!-- =================================================
                         TEMPLATE HEADER
                         ================================================= -->

                    <div class="template-header">
                        <div class="template-heading-main">
                            <div class="template-number">
                                {{
                                    String(
                                        index + 1
                                    ).padStart(
                                        2,
                                        '0',
                                    )
                                }}
                            </div>

                            <div class="template-heading-copy">
                                <div class="template-title-row">
                                    <h4>
                                        {{ template.label }}
                                    </h4>

                                    <code>
                                        {{ template.type }}
                                    </code>

                                    <span
                                        v-if="
                                            isDirty(
                                                template
                                            )
                                        "
                                        class="unsaved-badge"
                                    >
                                        Unsaved changes
                                    </span>
                                </div>

                                <p>
                                    {{
                                        template.description
                                    }}
                                </p>
                            </div>
                        </div>


                        <div class="template-meta">
                            <span>
                                Last edited
                            </span>

                            <strong>
                                {{
                                    formatDateTime(
                                        template.updated_at
                                    )
                                }}
                            </strong>

                            <small
                                v-if="
                                    template
                                        .updated_by_admin_id
                                "
                            >
                                Administrator
                                #{{
                                    template
                                        .updated_by_admin_id
                                }}
                            </small>

                            <small v-else>
                                Default template
                            </small>
                        </div>
                    </div>


                    <!-- =================================================
                         PLACEHOLDERS
                         ================================================= -->

                    <div class="placeholder-section">
                        <div class="placeholder-heading">
                            <div>
                                <span class="field-label">
                                    Allowed placeholders
                                </span>

                                <small>
                                    Click a placeholder to insert it
                                    at the current cursor position.
                                </small>
                            </div>

                            <span class="placeholder-count">
                                {{
                                    template
                                        .allowed_placeholders
                                        ?.length
                                    ?? 0
                                }}
                                available
                            </span>
                        </div>


                        <div class="placeholder-buttons">
                            <button
                                v-for="
                                    placeholder
                                    in template
                                        .allowed_placeholders
                                "
                                :key="
                                    placeholder
                                "
                                type="button"
                                class="placeholder-button"
                                :disabled="
                                    template.isSaving
                                "
                                @click="
                                    insertPlaceholder(
                                        template,
                                        placeholder
                                    )
                                "
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M8 4H5a2 2 0 00-2 2v12a2 2 0 002 2h3"
                                    />

                                    <path
                                        d="M16 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3"
                                    />

                                    <path
                                        d="M9 12h6"
                                    />
                                </svg>

                                <span>
                                    {{ placeholder }}
                                </span>
                            </button>
                        </div>
                    </div>


                    <!-- =================================================
                         EDITOR
                         ================================================= -->

                    <label class="template-editor">
                        <div class="editor-heading">
                            <div>
                                <span class="field-label">
                                    SMS message
                                </span>

                                <small>
                                    Maximum
                                    {{
                                        template.max_characters
                                    }}
                                    characters
                                </small>
                            </div>


                            <span
                                class="character-counter"
                                :class="{
                                    warning:
                                        characterPercentage(
                                            template
                                        ) >= 85
                                        &&
                                        !isTooLong(
                                            template
                                        ),

                                    invalid:
                                        isTooLong(
                                            template
                                        ),
                                }"
                            >
                                {{
                                    characterCount(
                                        template.draft
                                    )
                                }}
                                /
                                {{
                                    template
                                        .max_characters
                                }}
                            </span>
                        </div>


                        <textarea
                            :ref="
                                (element) =>
                                    setTextareaRef(
                                        template.type,
                                        element
                                    )
                            "
                            v-model="
                                template.draft
                            "
                            rows="5"
                            :maxlength="
                                Number(
                                    template
                                        .max_characters
                                    ?? 320
                                )
                                + 50
                            "
                            :disabled="
                                template.isSaving
                            "
                            :class="{
                                invalid:
                                    isTooLong(
                                        template
                                    )
                                    ||
                                    template.draft.trim()
                                        === '',
                            }"
                            @input="
                                clearTemplateMessage(
                                    template
                                )
                            "
                        />


                        <div class="character-progress">
                            <div
                                class="character-progress-fill"
                                :class="{
                                    warning:
                                        characterPercentage(
                                            template
                                        ) >= 85
                                        &&
                                        !isTooLong(
                                            template
                                        ),

                                    invalid:
                                        isTooLong(
                                            template
                                        ),
                                }"
                                :style="{
                                    width:
                                        `${
                                            characterPercentage(
                                                template
                                            )
                                        }%`,
                                }"
                            />
                        </div>
                    </label>


                    <!-- =================================================
                         VALIDATION MESSAGE
                         ================================================= -->

                    <div
                        v-if="
                            template.draft.trim()
                            === ''
                        "
                        class="template-validation-warning"
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
                                d="M12 8v5M12 17h.01"
                            />
                        </svg>

                        <span>
                            The SMS template cannot be empty.
                        </span>
                    </div>


                    <div
                        v-else-if="
                            isTooLong(
                                template
                            )
                        "
                        class="template-validation-warning"
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
                                d="M12 8v5M12 17h.01"
                            />
                        </svg>

                        <span>
                            This message exceeds the allowed
                            {{
                                template.max_characters
                            }}
                            characters.
                        </span>
                    </div>


                    <!-- =================================================
                         API ERROR
                         ================================================= -->

                    <div
                        v-if="
                            template.error
                        "
                        class="template-message error"
                        role="alert"
                    >
                        <div class="template-message-icon">
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
                                    d="M12 8v5M12 17h.01"
                                />
                            </svg>
                        </div>

                        <div>
                            <strong>
                                Template not saved
                            </strong>

                            <span>
                                {{ template.error }}
                            </span>
                        </div>
                    </div>


                    <!-- =================================================
                         SUCCESS
                         ================================================= -->

                    <div
                        v-if="
                            template.success
                        "
                        class="template-message success"
                    >
                        <div class="template-message-icon">
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
                                Template saved
                            </strong>

                            <span>
                                {{ template.success }}
                            </span>
                        </div>
                    </div>


                    <!-- =================================================
                         FOOTER
                         ================================================= -->

                    <div class="template-footer">
                        <div class="template-footer-note">
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
                                Use only the placeholder buttons
                                listed above. Unsupported or malformed
                                placeholders are rejected by the server.
                            </span>
                        </div>


                        <div class="template-actions">
                            <button
                                type="button"
                                class="secondary-button"
                                :disabled="
                                    template.isSaving
                                    ||
                                    !isDirty(
                                        template
                                    )
                                "
                                @click="
                                    undoTemplate(
                                        template
                                    )
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
                                type="button"
                                class="primary-button template-save-button"
                                :disabled="
                                    !canSave(
                                        template
                                    )
                                "
                                @click="
                                    saveTemplate(
                                        template
                                    )
                                "
                            >
                                <span
                                    v-if="
                                        template.isSaving
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
                                        template.isSaving
                                            ? 'Saving...'
                                            : 'Save template'
                                    }}
                                </span>
                            </button>
                        </div>
                    </div>
                </article>
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

.settings-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| INTRO
|--------------------------------------------------------------------------
*/

.settings-intro {
    min-height: 138px;

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

.settings-intro-copy {
    min-width: 0;

    flex: 1;
}

.settings-intro-label {
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

.settings-intro-dot {
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

.settings-intro h2 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 23px;
    font-weight: 800;

    line-height: 30px;

    letter-spacing:
        -0.65px;
}

.settings-intro p {
    max-width: 720px;

    margin:
        5px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 10.5px;

    line-height: 17px;
}

.settings-intro-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| LOADED CARD
|--------------------------------------------------------------------------
*/

.settings-loaded-card {
    min-width: 215px;

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

.settings-loaded-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.settings-loaded-icon svg {
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

.settings-loaded-card
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 2px;
}

.settings-loaded-card span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.settings-loaded-card strong {
    color:
        var(--sh-text);

    font-size: 8.5px;

    white-space: nowrap;
}

.settings-refresh-button {
    min-height: 48px;
}

.settings-refresh-button svg {
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
| SECURITY BANNER
|--------------------------------------------------------------------------
*/

.security-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #d5e8df;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            var(--sh-success-bg),
            #fbfefd
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.security-banner-icon {
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

.security-banner-icon svg {
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

.security-banner-copy {
    min-width: 0;

    flex: 1;
}

.security-banner-copy strong {
    display: block;

    color:
        #285f4b;

    font-size: 9.5px;
}

.security-banner-copy p {
    max-width: 850px;

    margin:
        3px
        0
        0;

    color:
        #5f7c70;

    font-size: 8.5px;

    line-height: 14px;
}

.security-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #cce2d6;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-success);

    font-size: 7.5px;
    font-weight: 800;

    text-transform:
        uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.settings-error {
    align-items: center;

    margin: 0;
}

.settings-error-icon {
    width: 38px;
    height: 38px;

    flex: 0 0 38px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        var(--sh-danger-bg);
}

.settings-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        var(--sh-danger);

    stroke-width: 1.8;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.settings-error-copy {
    min-width: 0;

    flex: 1;
}

.settings-error-copy strong {
    display: block;

    font-size: 10px;
}

.settings-error-copy p {
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

.settings-loading-card {
    min-height: 340px;
}

.settings-loading-icon {
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

.settings-loading-icon
.spinner {
    margin: 0;
}

.settings-loading-card h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
}

.settings-loading-card p {
    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| SECTION HEADING
|--------------------------------------------------------------------------
*/

.settings-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 18px;

    margin:
        14px
        2px
        0;
}

.settings-section-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 17px;
    font-weight: 800;

    letter-spacing:
        -0.4px;
}

.settings-section-heading
> div:first-child
> p:last-child {
    max-width: 720px;

    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 9px;

    line-height: 14px;
}

.readonly-badge {
    min-height: 26px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 999px;

    background:
        var(--sh-surface);

    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 750;

    text-transform:
        uppercase;
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION GRID
|--------------------------------------------------------------------------
*/

.configuration-grid {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 11px;
}

.configuration-card {
    min-width: 0;

    min-height: 112px;

    display: flex;
    align-items: center;

    gap: 12px;

    padding:
        14px;

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

.configuration-card:hover {
    transform:
        translateY(
            -1px
        );

    box-shadow:
        var(--sh-shadow-card);
}

.configuration-icon {
    width: 41px;
    height: 41px;

    flex: 0 0 41px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.configuration-icon svg {
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

.configuration-icon.enabled {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.configuration-icon.disabled {
    background:
        #f2f4f6;

    color:
        var(--sh-text-3);
}

.configuration-icon.warning,
.configuration-icon.minimum {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.configuration-icon.provider {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.configuration-icon.reminder {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.configuration-card-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.configuration-card-copy
> span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.configuration-card-copy strong {
    margin-top: 3px;

    color:
        var(--sh-text);

    font-size: 14px;
    font-weight: 800;

    line-height: 19px;
}

.configuration-card-copy
strong.enabled {
    color:
        var(--sh-success);
}

.configuration-card-copy
strong.disabled {
    color:
        var(--sh-text-3);
}

.configuration-card-copy
.provider-value {
    text-transform:
        capitalize;
}

.configuration-card-copy small {
    margin-top: 3px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 12px;
}


/*
|--------------------------------------------------------------------------
| CONFIGURATION INFORMATION
|--------------------------------------------------------------------------
*/

.configuration-information {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    padding:
        10px
        12px;

    border:
        1px solid
        #d7e2f7;

    border-radius: 11px;

    background:
        var(--sh-info-bg);
}

.configuration-information-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-info);
}

.configuration-information-icon svg {
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

.configuration-information strong {
    color:
        #365a9c;

    font-size: 8.5px;
}

.configuration-information p {
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
| TEMPLATE SUMMARY
|--------------------------------------------------------------------------
*/

.templates-heading {
    margin-top: 21px;
}

.template-summary {
    display: flex;
    align-items: center;

    gap: 7px;
}

.template-summary-item {
    min-width: 69px;

    padding:
        7px
        9px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 9px;

    background:
        var(--sh-surface);

    text-align: center;
}

.template-summary-item span {
    display: block;

    color:
        var(--sh-text-3);

    font-size: 7px;

    text-transform:
        uppercase;
}

.template-summary-item strong {
    display: block;

    margin-top: 1px;

    color:
        var(--sh-text);

    font-size: 13px;
}

.template-summary-item.changed {
    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);
}

.template-summary-item.changed
strong {
    color:
        #845d1d;
}

.template-summary-item.invalid {
    border-color:
        #eed3d3;

    background:
        var(--sh-danger-bg);
}

.template-summary-item.invalid
strong {
    color:
        var(--sh-danger);
}


/*
|--------------------------------------------------------------------------
| SNAPSHOT BANNER
|--------------------------------------------------------------------------
*/

.snapshot-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #eadfbf;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            var(--sh-accent-soft),
            #fffdf9
        );

    box-shadow:
        var(--sh-shadow-sm);
}

.snapshot-banner-icon {
    width: 37px;
    height: 37px;

    flex: 0 0 37px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;

    background:
        #ffffff;

    color:
        var(--sh-accent);
}

.snapshot-banner-icon svg {
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

.snapshot-banner-copy {
    min-width: 0;

    flex: 1;
}

.snapshot-banner-copy strong {
    display: block;

    color:
        #7c5a22;

    font-size: 9.5px;
}

.snapshot-banner-copy p {
    max-width: 840px;

    margin:
        3px
        0
        0;

    color:
        #957541;

    font-size: 8.5px;

    line-height: 14px;
}

.snapshot-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #ead9b2;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        #896221;

    font-size: 7.5px;
    font-weight: 800;

    text-transform:
        uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.templates-empty-state {
    min-height: 260px;
}


/*
|--------------------------------------------------------------------------
| TEMPLATE LIST
|--------------------------------------------------------------------------
*/

.templates-list {
    display: flex;
    flex-direction: column;

    gap: 14px;
}

.template-card {
    overflow: hidden;

    border:
        1px solid
        var(--sh-border);

    border-radius:
        var(--sh-radius-lg);

    background:
        var(--sh-surface);

    box-shadow:
        var(--sh-shadow-sm);

    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.template-card.dirty {
    border-color:
        #e7d5a9;

    box-shadow:
        0 8px 30px
        rgba(
            160,
            110,
            28,
            0.06
        );
}

.template-card.invalid {
    border-color:
        #e8c8c8;
}


/*
|--------------------------------------------------------------------------
| TEMPLATE HEADER
|--------------------------------------------------------------------------
*/

.template-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 18px;

    padding:
        15px
        16px;

    border-bottom:
        1px solid
        var(--sh-border);

    background:
        #fcfdfe;
}

.template-heading-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: flex-start;

    gap: 11px;
}

.template-number {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 9px;

    background:
        linear-gradient(
            145deg,
            var(--sh-primary),
            var(--sh-primary-2)
        );

    color:
        var(--sh-accent);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 8px;
    font-weight: 800;
}

.template-heading-copy {
    min-width: 0;
}

.template-title-row {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 7px;
}

.template-title-row h4 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.template-title-row code {
    padding:
        3px
        6px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 6px;

    background:
        #f3f5f7;

    color:
        var(--sh-text-3);

    font-size: 7px;
}

.unsaved-badge {
    min-height: 21px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        7px;

    border:
        1px solid
        #eadfbf;

    border-radius: 999px;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;

    font-size: 7px;
    font-weight: 750;
}

.template-heading-copy p {
    max-width: 720px;

    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8.5px;

    line-height: 14px;
}

.template-meta {
    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 2px;

    text-align: right;
}

.template-meta > span {
    color:
        var(--sh-text-3);

    font-size: 7px;

    text-transform:
        uppercase;
}

.template-meta strong {
    color:
        var(--sh-text-2);

    font-size: 8px;
}

.template-meta small {
    color:
        var(--sh-text-3);

    font-size: 7px;
}


/*
|--------------------------------------------------------------------------
| PLACEHOLDERS
|--------------------------------------------------------------------------
*/

.placeholder-section {
    padding:
        14px
        16px
        0;
}

.placeholder-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 12px;

    margin-bottom: 8px;
}

.field-label {
    display: block;

    color:
        var(--sh-text-2);

    font-size: 8.5px;
    font-weight: 750;
}

.placeholder-heading small,
.editor-heading small {
    display: block;

    margin-top: 2px;

    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.placeholder-count {
    color:
        var(--sh-text-3);

    font-size: 7.5px;

    white-space: nowrap;
}

.placeholder-buttons {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 6px;
}

.placeholder-button {
    min-height: 29px;

    display: inline-flex;
    align-items: center;

    gap: 5px;

    padding:
        0
        8px;

    border:
        1px solid
        #e1e5ea;

    border-radius: 7px;

    background:
        #f8fafb;

    color:
        #59636f;

    font: inherit;

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7.5px;
    font-weight: 650;

    cursor: pointer;

    transition:
        border-color 0.15s ease,
        background 0.15s ease,
        color 0.15s ease;
}

.placeholder-button:hover:not(:disabled) {
    border-color:
        #e0c58f;

    background:
        var(--sh-accent-soft);

    color:
        #805a1e;
}

.placeholder-button:disabled {
    cursor: not-allowed;

    opacity: 0.55;
}

.placeholder-button svg {
    width: 10px;
    height: 10px;

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
| EDITOR
|--------------------------------------------------------------------------
*/

.template-editor {
    display: block;

    padding:
        14px
        16px
        0;
}

.editor-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 12px;

    margin-bottom: 7px;
}

.character-counter {
    color:
        var(--sh-text-3);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 8px;
    font-weight: 700;
}

.character-counter.warning {
    color:
        var(--sh-warning);
}

.character-counter.invalid {
    color:
        var(--sh-danger);
}

.template-editor textarea {
    width: 100%;
    min-height: 126px;

    resize: vertical;

    padding:
        11px
        12px;

    border:
        1px solid
        var(--sh-border-strong);

    border-radius:
        10px;

    outline: none;

    background:
        #ffffff;

    color:
        var(--sh-text);

    font: inherit;

    font-size: 9.5px;

    line-height: 17px;

    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.template-editor textarea:focus {
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

.template-editor textarea.invalid {
    border-color:
        #dca9a9;
}

.template-editor textarea:disabled {
    cursor: not-allowed;

    opacity: 0.65;

    background:
        #f7f8fa;
}

.character-progress {
    height: 3px;

    overflow: hidden;

    margin-top: 6px;

    border-radius: 999px;

    background:
        #edf0f3;
}

.character-progress-fill {
    height: 100%;

    border-radius: inherit;

    background:
        var(--sh-success);

    transition:
        width 0.15s ease,
        background 0.15s ease;
}

.character-progress-fill.warning {
    background:
        var(--sh-warning);
}

.character-progress-fill.invalid {
    background:
        var(--sh-danger);
}


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

.template-validation-warning {
    display: flex;
    align-items: center;

    gap: 6px;

    margin:
        10px
        16px
        0;

    padding:
        8px
        9px;

    border:
        1px solid
        #eed3d3;

    border-radius: 8px;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);

    font-size: 8px;
}

.template-validation-warning svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

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
| TEMPLATE MESSAGES
|--------------------------------------------------------------------------
*/

.template-message {
    display: flex;
    align-items: flex-start;

    gap: 8px;

    margin:
        10px
        16px
        0;

    padding:
        9px
        10px;

    border:
        1px solid
        transparent;

    border-radius: 9px;
}

.template-message.error {
    border-color:
        #eed3d3;

    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.template-message.success {
    border-color:
        #d4ebdf;

    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.template-message-icon {
    width: 24px;
    height: 24px;

    flex: 0 0 24px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 7px;

    background:
        rgba(
            255,
            255,
            255,
            0.65
        );
}

.template-message-icon svg {
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

.template-message
> div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.template-message strong {
    font-size: 8.5px;
}

.template-message span {
    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| TEMPLATE FOOTER
|--------------------------------------------------------------------------
*/

.template-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 16px;

    margin-top: 14px;

    padding:
        12px
        16px;

    border-top:
        1px solid
        var(--sh-border);

    background:
        #fcfdfe;
}

.template-footer-note {
    min-width: 0;

    max-width: 680px;

    display: flex;
    align-items: flex-start;

    gap: 6px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;

    line-height: 13px;
}

.template-footer-note svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        var(--sh-text-3);

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.template-actions {
    flex: 0 0 auto;

    display: flex;
    align-items: center;

    gap: 8px;
}

.template-actions
.secondary-button,
.template-actions
.primary-button {
    min-height: 37px;
}

.template-actions svg {
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

.template-save-button {
    min-width: 122px;
}

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
        settings-button-spin
        0.7s
        linear
        infinite;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        settings-spin
        0.8s
        linear
        infinite;
}

@keyframes settings-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}

@keyframes settings-button-spin {
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
    .settings-intro {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .settings-intro-actions {
        width: 100%;
    }

    .settings-loaded-card {
        flex: 1;
    }

    .configuration-grid {
        grid-template-columns:
            repeat(
                2,
                minmax(
                    0,
                    1fr
                )
            );
    }
}


@media (max-width: 850px) {
    .template-header {
        flex-direction:
            column;
    }

    .template-meta {
        align-items:
            flex-start;

        text-align: left;
    }

    .settings-section-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .template-summary {
        width: 100%;
    }

    .template-summary-item {
        flex: 1;
    }
}


@media (max-width: 680px) {
    .settings-intro {
        padding: 17px;
    }

    .settings-intro h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .settings-intro-actions {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .settings-loaded-card,
    .settings-refresh-button {
        width: 100%;
    }

    .security-banner,
    .snapshot-banner {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .security-banner-copy,
    .snapshot-banner-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .security-badge,
    .snapshot-badge {
        margin-left: 48px;
    }

    .settings-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .settings-error
    .retry-button {
        width: 100%;
    }

    .configuration-grid {
        grid-template-columns:
            1fr;
    }

    .template-heading-main {
        align-items:
            flex-start;
    }

    .placeholder-heading {
        flex-direction:
            column;
    }

    .editor-heading {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .template-footer {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .template-actions {
        width: 100%;

        align-items:
            stretch;

        flex-direction:
            column;
    }

    .template-actions
    .secondary-button,
    .template-actions
    .primary-button {
        width: 100%;
    }
}
</style>