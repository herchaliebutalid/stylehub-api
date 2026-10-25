
<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
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

/* ==========================================================
   TEMPLATE SUMMARY
   ========================================================== */

const hasTemplates = computed(
    () => templates.value.length > 0,
);

const modifiedTemplateCount = computed(
    () => templates.value.filter(isDirty).length,
);

const invalidTemplateCount = computed(
    () =>
        templates.value.filter(
            (template) =>
                template.draft.trim() === '' ||
                isTooLong(template),
        ).length,
);

/* ==========================================================
   TEMPLATE HELPERS
   ========================================================== */

function characterCount(value) {
    return [...String(value ?? '')].length;
}

function isDirty(template) {
    return template.draft !== template.template;
}

function isTooLong(template) {
    return characterCount(template.draft) >
        Number(template.max_characters ?? 320);
}

function canSave(template) {
    return (
        !template.isSaving &&
        isDirty(template) &&
        template.draft.trim() !== '' &&
        !isTooLong(template)
    );
}

function characterPercentage(template) {
    const maximum = Number(template.max_characters ?? 320);

    if (!Number.isFinite(maximum) || maximum <= 0) {
        return 0;
    }

    return Math.min(
        100,
        (characterCount(template.draft) / maximum) * 100,
    );
}

function clearTemplateMessage(template) {
    template.error = '';
    template.success = '';
}

function setTextareaRef(type, element) {
    if (element) {
        textareaRefs[type] = element;
    } else {
        delete textareaRefs[type];
    }
}

function normalizeTemplate(template) {
    return {
        ...template,
        draft: template.template ?? '',
        isSaving: false,
        error: '',
        success: '',
    };
}

/* ==========================================================
   FORMATTERS
   ========================================================== */

function humanize(value) {
    if (!value) {
        return '—';
    }

    return String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) =>
            character.toUpperCase(),
        );
}

function providerLabel(value) {
    const provider = String(value ?? '').trim();

    if (provider.toLowerCase() === 'textbee') {
        return 'TextBee';
    }

    return humanize(provider);
}

function statusLabel(value) {
    return value ? 'Enabled' : 'Disabled';
}

function configurationLabel(value) {
    return value ? 'Configured' : 'Not configured';
}

function minutesLabel(value) {
    const minutes = Number(value);

    if (!Number.isFinite(minutes)) {
        return '—';
    }

    if (minutes === 60) {
        return '1 hour';
    }

    if (minutes > 0 && minutes % 1440 === 0) {
        const days = minutes / 1440;

        return `${days} ${days === 1 ? 'day' : 'days'}`;
    }

    if (minutes > 0 && minutes % 60 === 0) {
        const hours = minutes / 60;

        return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
    }

    return `${minutes} minutes`;
}

function formatDateTime(value) {
    if (!value) {
        return 'Never edited';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    }).format(date);
}

/* ==========================================================
   CONFIGURATION CARDS

   These fields match the updated Laravel
   AdminSystemSettingsService.php from Step 5.
   ========================================================== */

const configurationCards = computed(() => {
    if (!configuration.value) {
        return [];
    }

    const settings = configuration.value;

    return [
        {
            key: 'delivery',
            title: 'SMS Delivery',
            value: statusLabel(settings.enabled),
            description: 'Global outbound SMS status',
            state: settings.enabled ? 'success' : 'neutral',
            icon: 'message',
        },
        {
            key: 'provider',
            title: 'SMS Provider',
            value: providerLabel(settings.provider),
            description: 'Current messaging gateway',
            state: 'accent',
            icon: 'provider',
        },
        {
            key: 'api_key',
            title: 'TextBee API Key',
            value: configurationLabel(
                settings.api_key_configured,
            ),
            description: 'Secret key remains on the server',
            state: settings.api_key_configured
                ? 'success'
                : 'warning',
            icon: 'key',
        },
        {
            key: 'device',
            title: 'Android Gateway Device',
            value: configurationLabel(
                settings.device_id_configured,
            ),
            description: 'Registered TextBee device ID setting',
            state: settings.device_id_configured
                ? 'success'
                : 'warning',
            icon: 'device',
        },
        {
            key: 'live_test',
            title: 'Live SMS Test',
            value: statusLabel(settings.live_test_enabled),
            description: 'Controlled provider test setting',
            state: settings.live_test_enabled
                ? 'success'
                : 'neutral',
            icon: 'shield',
        },
        {
            key: 'reminder_lead',
            title: 'Reminder Lead',
            value: minutesLabel(
                settings.reminders?.lead_minutes,
            ),
            description: 'Target appointment reminder window',
            state: 'info',
            icon: 'clock',
        },
        {
            key: 'minimum_lead',
            title: 'Minimum Reminder Lead',
            value: minutesLabel(
                settings.reminders?.minimum_lead_minutes,
            ),
            description: 'Minimum time before appointment',
            state: 'warning',
            icon: 'clock',
        },
    ];
});

/* ==========================================================
   AUTHENTICATION
   ========================================================== */

async function authenticationFailure() {
    auth.clearSession();

    await router.replace({
        name: 'admin.login',
    });
}

/* ==========================================================
   LOAD SYSTEM SETTINGS
   ========================================================== */

async function loadSettings() {
    if (!auth.token) {
        await authenticationFailure();
        return;
    }

    isLoading.value = true;
    pageError.value = '';

    try {
        const response = await apiRequest(
            '/admin/system-settings',
            {
                token: auth.token,
            },
        );

        const sms = response?.data?.sms;

        if (
            !sms ||
            typeof sms !== 'object' ||
            !sms.configuration ||
            !Array.isArray(sms.templates)
        ) {
            throw new Error(
                'The System Settings response was invalid.',
            );
        }

        configuration.value = sms.configuration;

        templates.value = sms.templates.map(
            normalizeTemplate,
        );

        generatedAt.value = response?.generated_at ?? null;
    } catch (error) {
        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            await authenticationFailure();
            return;
        }

        pageError.value =
            error?.data?.message ??
            error?.message ??
            'Unable to load System Settings.';
    } finally {
        isLoading.value = false;
    }
}

/* ==========================================================
   INSERT ALLOWED PLACEHOLDER
   ========================================================== */

async function insertPlaceholder(template, placeholder) {
    const textarea = textareaRefs[template.type];

    const current = String(template.draft ?? '');

    const start = textarea?.selectionStart ?? current.length;
    const end = textarea?.selectionEnd ?? start;

    template.draft =
        current.slice(0, start) +
        placeholder +
        current.slice(end);

    clearTemplateMessage(template);

    await nextTick();

    const updatedTextarea = textareaRefs[template.type];

    if (!updatedTextarea) {
        return;
    }

    const cursor = start + placeholder.length;

    updatedTextarea.focus();
    updatedTextarea.setSelectionRange(cursor, cursor);
}

/* ==========================================================
   UNDO TEMPLATE
   ========================================================== */

function undoTemplate(template) {
    template.draft = template.template;
    clearTemplateMessage(template);
}

/* ==========================================================
   SAVE TEMPLATE
   ========================================================== */

async function saveTemplate(template) {
    if (!canSave(template)) {
        return;
    }

    template.isSaving = true;
    template.error = '';
    template.success = '';
    pageError.value = '';

    try {
        const response = await apiRequest(
            `/admin/system-settings/sms-templates/${encodeURIComponent(
                template.type,
            )}`,
            {
                method: 'PUT',
                token: auth.token,
                body: {
                    template: template.draft,
                },
            },
        );

        const saved = response?.data?.template;

        if (!saved) {
            throw new Error(
                'The updated SMS template response was invalid.',
            );
        }

        Object.assign(
            template,
            normalizeTemplate(saved),
            {
                success:
                    response?.message ??
                    'SMS template updated successfully.',
            },
        );
    } catch (error) {
        if (
            error?.status === 401 ||
            error?.status === 403
        ) {
            await authenticationFailure();
            return;
        }

        if (error?.status === 422) {
            template.error =
                error?.data?.errors?.template?.[0] ??
                error?.data?.message ??
                'The SMS template is invalid.';

            return;
        }

        if (error?.status === 404) {
            template.error =
                'This SMS template is not editable.';

            return;
        }

        template.error =
            error?.data?.message ??
            error?.message ??
            'Unable to update this SMS template.';
    } finally {
        template.isSaving = false;
    }
}

/* ==========================================================
   INITIAL LOAD
   ========================================================== */

onMounted(() => {
    void loadSettings();
});
</script>

<template>
    <div class="settings-page">

        <!-- ==================================================
             HEADER
             ================================================== -->

        <section class="settings-intro">
            <div class="settings-intro-copy">
                

                <h2>System Settings</h2>

                <p>
                    Review StyleHub's secure SMS configuration
                    and manage the customer notification templates
                    used for future messages.
                </p>
            </div>

            <div class="settings-intro-actions">
                <div
                    v-if="generatedAt"
                    class="settings-loaded-card"
                >
                    <div class="settings-loaded-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" />
                        </svg>
                    </div>

                    <div>
                        <span>Configuration Loaded</span>

                        <strong>
                            {{ formatDateTime(generatedAt) }}
                        </strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="secondary-button settings-refresh-button"
                    :disabled="isLoading"
                    @click="loadSettings"
                >
                    <svg
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                        :class="{ spinning: isLoading }"
                    >
                        <path d="M20 11a8 8 0 10-2.3 5.7" />
                        <path d="M20 5v6h-6" />
                    </svg>

                    {{ isLoading ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>
        </section>

        <!-- ==================================================
             SECURITY NOTICE
             ================================================== -->

        <section class="security-banner">
            <div class="security-banner-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                    />
                    <path d="M9 12l2 2 4-4" />
                </svg>
            </div>

            <div class="security-banner-copy">
                <strong>
                    TextBee credentials are protected
                </strong>

                <p>
                    StyleHub stores its TextBee API key,
                    Android device ID, and provider endpoint
                    on the Laravel server. This page displays
                    configuration indicators only. Actual
                    credentials and device identifiers are
                    never returned to the Administrator browser.
                </p>
            </div>

            <span class="security-badge">
                Protected
            </span>
        </section>

        <!-- ==================================================
             ERROR MESSAGE
             ================================================== -->

        <div
            v-if="pageError"
            class="error-alert settings-error"
            role="alert"
        >
            <div class="settings-error-icon">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v5M12 17h.01" />
                </svg>
            </div>

            <div class="settings-error-copy">
                <strong>Unable to Load System Settings</strong>
                <p>{{ pageError }}</p>
            </div>

            <button
                type="button"
                class="retry-button"
                :disabled="isLoading"
                @click="loadSettings"
            >
                Try Again
            </button>
        </div>

        <!-- ==================================================
             LOADING
             ================================================== -->

        <section
            v-if="isLoading && !configuration"
            class="loading-card settings-loading-card"
        >
            <div class="settings-spinner"></div>

            <h3>Loading System Configuration</h3>

            <p>
                Retrieving operational SMS settings
                and customer message templates.
            </p>
        </section>

        <!-- ==================================================
             CONFIGURATION
             ================================================== -->

        <template v-else-if="configuration">

            <section class="settings-section-heading">
                <div>
                    <p class="eyebrow">SMS OPERATIONS</p>

                    <h3>Configuration Status</h3>

                    <p>
                        These settings are provided by your
                        Laravel backend. They are read-only
                        in the Administrator portal.
                    </p>
                </div>

                <span class="readonly-badge">
                    Read Only
                </span>
            </section>

            <!-- CONFIGURATION CARDS -->

            <section class="configuration-grid">
                <article
                    v-for="card in configurationCards"
                    :key="card.key"
                    class="configuration-card"
                >
                    <div
                        class="configuration-icon"
                        :class="card.state"
                    >
                        <!-- MESSAGE ICON -->

                        <svg
                            v-if="card.icon === 'message'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M4 5h16v12H9l-5 4V5z" />
                            <path d="M8 9h8M8 13h5" />
                        </svg>

                        <!-- PROVIDER ICON -->

                        <svg
                            v-else-if="card.icon === 'provider'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="4"
                                y="4"
                                width="16"
                                height="16"
                                rx="2"
                            />
                            <path d="M8 8h8M8 12h8M8 16h5" />
                        </svg>

                        <!-- KEY ICON -->

                        <svg
                            v-else-if="card.icon === 'key'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="8" cy="15" r="4" />
                            <path d="M11 12L21 2M17 6l3 3M14 9l3 3" />
                        </svg>

                        <!-- DEVICE ICON -->

                        <svg
                            v-else-if="card.icon === 'device'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <rect
                                x="7"
                                y="2"
                                width="10"
                                height="20"
                                rx="2"
                            />
                            <path d="M11 18h2" />
                        </svg>

                        <!-- SHIELD ICON -->

                        <svg
                            v-else-if="card.icon === 'shield'"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M12 3l8 4v5c0 5-3.4 8.2-8 9.5C7.4 20.2 4 17 4 12V7l8-4z"
                            />
                            <path d="M9 12l2 2 4-4" />
                        </svg>

                        <!-- CLOCK ICON -->

                        <svg
                            v-else
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" />
                        </svg>
                    </div>

                    <div class="configuration-card-copy">
                        <span>{{ card.title }}</span>

                        <strong
                            :class="{
                                'success-text':
                                    card.state === 'success',
                                'warning-text':
                                    card.state === 'warning',
                            }"
                        >
                            {{ card.value }}
                        </strong>

                        <small>{{ card.description }}</small>
                    </div>
                </article>
            </section>

            <!-- CONFIGURATION EXPLANATION -->

            <section class="configuration-information">
                <div class="configuration-information-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M12 11v5M12 8h.01" />
                    </svg>
                </div>

                <div>
                    <strong>
                        TextBee configuration is server-managed
                    </strong>

                    <p>
                        The API key and device indicators confirm
                        only that values are configured; they do
                        not prove that your Android gateway is
                        currently connected or can deliver SMS.
                        Credentials, gateway settings, and reminder
                        timing must be updated securely on the
                        Laravel server.
                    </p>
                </div>
            </section>

            <!-- ==================================================
                 TEMPLATE HEADING
                 ================================================== -->

            <section class="settings-section-heading templates-heading">
                <div>
                    <p class="eyebrow">SMS TEMPLATES</p>

                    <h3>Customer Message Templates</h3>

                    <p>
                        Edit the approved message templates used
                        for future StyleHub notifications.
                    </p>
                </div>

                <div class="template-summary">
                    <div class="template-summary-item">
                        <span>Templates</span>
                        <strong>{{ templates.length }}</strong>
                    </div>

                    <div
                        class="template-summary-item"
                        :class="{
                            changed: modifiedTemplateCount > 0,
                        }"
                    >
                        <span>Unsaved</span>
                        <strong>{{ modifiedTemplateCount }}</strong>
                    </div>

                    <div
                        class="template-summary-item"
                        :class="{
                            invalid: invalidTemplateCount > 0,
                        }"
                    >
                        <span>Invalid</span>
                        <strong>{{ invalidTemplateCount }}</strong>
                    </div>
                </div>
            </section>

            <!-- TEMPLATE SAFETY NOTICE -->

            <section class="snapshot-banner">
                <div class="snapshot-banner-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 3h9l3 3v15H6V3z" />
                        <path d="M14 3v4h4" />
                        <path d="M9 11h6M9 15h6" />
                    </svg>
                </div>

                <div class="snapshot-banner-copy">
                    <strong>
                        Template changes affect future SMS only
                    </strong>

                    <p>
                        Notifications already queued retain their
                        original message snapshot. A controlled
                        retry uses the same original message,
                        not a newly edited template.
                    </p>
                </div>

                <span class="snapshot-badge">
                    Snapshot Safe
                </span>
            </section>

            <!-- ==================================================
                 EMPTY TEMPLATES
                 ================================================== -->

            <div
                v-if="!hasTemplates"
                class="empty-state templates-empty-state"
            >
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 5h16v12H9l-5 4V5z" />
                        <path d="M8 9h8M8 13h5" />
                    </svg>
                </div>

                <h3>No Editable Templates</h3>

                <p>
                    No customer-facing SMS templates are
                    currently available for editing.
                </p>
            </div>

            <!-- ==================================================
                 TEMPLATE LIST
                 ================================================== -->

            <section v-else class="templates-list">
                <article
                    v-for="(template, index) in templates"
                    :key="template.type"
                    class="template-card"
                    :class="{
                        dirty: isDirty(template),
                        invalid:
                            isTooLong(template) ||
                            template.draft.trim() === '',
                    }"
                >
                    <!-- TEMPLATE HEADER -->

                    <div class="template-header">
                        <div class="template-heading-main">
                            <div class="template-number">
                                {{ String(index + 1).padStart(2, '0') }}
                            </div>

                            <div class="template-heading-copy">
                                <div class="template-title-row">
                                    <h4>{{ template.label }}</h4>

                                    <code>{{ template.type }}</code>

                                    <span
                                        v-if="isDirty(template)"
                                        class="unsaved-badge"
                                    >
                                        Unsaved Changes
                                    </span>
                                </div>

                                <p>{{ template.description }}</p>
                            </div>
                        </div>

                        <div class="template-meta">
                            <span>Last Edited</span>

                            <strong>
                                {{
                                    formatDateTime(
                                        template.updated_at
                                    )
                                }}
                            </strong>

                            <small
                                v-if="template.updated_by_admin_id"
                            >
                                Administrator
                                #{{ template.updated_by_admin_id }}
                            </small>

                            <small v-else>Default Template</small>
                        </div>
                    </div>

                    <!-- PLACEHOLDERS -->

                    <div class="placeholder-section">
                        <div class="placeholder-heading">
                            <div>
                                <span class="field-label">
                                    Allowed Placeholders
                                </span>

                                <small>
                                    Click a placeholder to insert it
                                    at your current cursor position.
                                </small>
                            </div>

                            <span class="placeholder-count">
                                {{
                                    template.allowed_placeholders
                                        ?.length ?? 0
                                }}
                                available
                            </span>
                        </div>

                        <div class="placeholder-buttons">
                            <button
                                v-for="placeholder in
                                    template.allowed_placeholders ?? []"
                                :key="placeholder"
                                type="button"
                                class="placeholder-button"
                                :disabled="template.isSaving"
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
                                    <path d="M9 12h6" />
                                </svg>

                                {{ placeholder }}
                            </button>
                        </div>
                    </div>

                    <!-- TEMPLATE EDITOR -->

                    <label class="template-editor">
                        <div class="editor-heading">
                            <div>
                                <span class="field-label">
                                    SMS Message
                                </span>

                                <small>
                                    Maximum
                                    {{ template.max_characters }}
                                    characters
                                </small>
                            </div>

                            <span
                                class="character-counter"
                                :class="{
                                    warning:
                                        characterPercentage(template) >= 85 &&
                                        !isTooLong(template),
                                    invalid: isTooLong(template),
                                }"
                            >
                                {{ characterCount(template.draft) }}
                                /
                                {{ template.max_characters }}
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
                            v-model="template.draft"
                            rows="5"
                            :maxlength="
                                Number(
                                    template.max_characters ?? 320
                                ) + 50
                            "
                            :disabled="template.isSaving"
                            :class="{
                                invalid:
                                    isTooLong(template) ||
                                    template.draft.trim() === '',
                            }"
                            @input="
                                clearTemplateMessage(template)
                            "
                        ></textarea>

                        <div class="character-progress">
                            <div
                                class="character-progress-fill"
                                :class="{
                                    warning:
                                        characterPercentage(template) >= 85 &&
                                        !isTooLong(template),
                                    invalid: isTooLong(template),
                                }"
                                :style="{
                                    width:
                                        `${characterPercentage(template)}%`,
                                }"
                            ></div>
                        </div>
                    </label>

                    <!-- VALIDATION -->

                    <div
                        v-if="template.draft.trim() === ''"
                        class="template-validation-warning"
                    >
                        The SMS template cannot be empty.
                    </div>

                    <div
                        v-else-if="isTooLong(template)"
                        class="template-validation-warning"
                    >
                        This message exceeds the allowed
                        {{ template.max_characters }} characters.
                    </div>

                    <!-- ERROR MESSAGE -->

                    <div
                        v-if="template.error"
                        class="template-message error"
                        role="alert"
                    >
                        <strong>Template Not Saved</strong>
                        <span>{{ template.error }}</span>
                    </div>

                    <!-- SUCCESS MESSAGE -->

                    <div
                        v-if="template.success"
                        class="template-message success"
                        role="status"
                    >
                        <strong>Template Saved</strong>
                        <span>{{ template.success }}</span>
                    </div>

                    <!-- FOOTER -->

                    <div class="template-footer">
                        <div class="template-footer-note">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 11v5M12 8h.01" />
                            </svg>

                            <span>
                                Use only the allowed placeholders.
                                Unsupported placeholders are rejected
                                by the Laravel backend.
                            </span>
                        </div>

                        <div class="template-actions">
                            <button
                                type="button"
                                class="secondary-button"
                                :disabled="
                                    template.isSaving ||
                                    !isDirty(template)
                                "
                                @click="undoTemplate(template)"
                            >
                                Undo Changes
                            </button>

                            <button
                                type="button"
                                class="primary-button template-save-button"
                                :disabled="!canSave(template)"
                                @click="saveTemplate(template)"
                            >
                                <span
                                    v-if="template.isSaving"
                                    class="button-spinner"
                                ></span>

                                <svg
                                    v-else
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M5 4h12l2 2v14H5V4z" />
                                    <path d="M8 4v6h8V4" />
                                    <path d="M8 15h8" />
                                </svg>

                                {{
                                    template.isSaving
                                        ? 'Saving...'
                                        : 'Save Template'
                                }}
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
/* ==========================================================
   PAGE ROOT
   ========================================================== */

.settings-page {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

/* ==========================================================
   PAGE HEADER
   ========================================================== */

.settings-intro {
    min-height: 138px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 21px 22px;
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-xl);
    background:
        radial-gradient(
            circle at 94% 8%,
            rgba(214, 154, 45, 0.08),
            transparent 28%
        ),
        linear-gradient(145deg, #ffffff, #fbfcfd);
    box-shadow: var(--sh-shadow-sm);
}

.settings-intro-copy {
    min-width: 0;
    flex: 1;
}

.settings-intro-label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 5px;
    color: var(--sh-accent);
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.1px;
    text-transform: uppercase;
}

.settings-intro-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--sh-accent);
    box-shadow: 0 0 0 4px rgba(214, 154, 45, 0.08);
}

.settings-intro h2 {
    margin: 0;
    color: var(--sh-text);
    font-size: 23px;
    font-weight: 800;
    line-height: 30px;
}

.settings-intro p {
    max-width: 720px;
    margin: 5px 0 0;
    color: var(--sh-text-3);
    font-size: 11px;
    line-height: 17px;
}

.settings-intro-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ==========================================================
   CONFIGURATION LOADED CARD
   ========================================================== */

.settings-loaded-card {
    min-width: 215px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border: 1px solid var(--sh-border);
    border-radius: 12px;
    background: var(--sh-surface-muted);
}

.settings-loaded-icon {
    width: 37px;
    height: 37px;
    flex: 0 0 37px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.settings-loaded-icon svg,
.settings-refresh-button svg {
    width: 17px;
    height: 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.settings-loaded-card > div:last-child {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.settings-loaded-card span {
    color: var(--sh-text-3);
    font-size: 9px;
}

.settings-loaded-card strong {
    color: var(--sh-text);
    font-size: 10px;
}

.settings-refresh-button {
    min-height: 45px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
}

/* ==========================================================
   SECURITY BANNER
   ========================================================== */

.security-banner {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 15px;
    border: 1px solid #d5e8df;
    border-radius: 13px;
    background:
        linear-gradient(
            135deg,
            var(--sh-success-bg),
            #fbfefd
        );
}

.security-banner-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #ffffff;
    color: var(--sh-success);
}

.security-banner-icon svg,
.configuration-information-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.security-banner-copy {
    min-width: 0;
    flex: 1;
}

.security-banner-copy strong {
    color: #285f4b;
    font-size: 11px;
}

.security-banner-copy p {
    margin: 4px 0 0;
    color: #5f7c70;
    font-size: 10px;
    line-height: 16px;
}

.security-badge {
    display: inline-flex;
    align-items: center;
    padding: 7px 10px;
    border: 1px solid #cce2d6;
    border-radius: 999px;
    background: #ffffff;
    color: var(--sh-success);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

/* ==========================================================
   LOADING AND ERROR
   ========================================================== */

.settings-error {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
}

.settings-error-icon {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
}

.settings-error-icon svg {
    width: 19px;
    height: 19px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.settings-error-copy {
    min-width: 0;
    flex: 1;
}

.settings-error-copy strong {
    display: block;
    font-size: 11px;
}

.settings-error-copy p {
    margin: 4px 0 0;
}

.settings-loading-card {
    min-height: 250px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.settings-loading-card h3 {
    margin: 0;
    font-size: 14px;
}

.settings-loading-card p {
    margin: 0;
    color: var(--sh-text-3);
    font-size: 11px;
}

.settings-spinner {
    width: 28px;
    height: 28px;
    border: 3px solid var(--sh-border);
    border-top-color: var(--sh-accent);
    border-radius: 50%;
    animation: settings-spin .8s linear infinite;
}

/* ==========================================================
   SECTION HEADING
   ========================================================== */

.settings-section-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin: 14px 2px 0;
}

.settings-section-heading h3 {
    margin: 0;
    color: var(--sh-text);
    font-size: 17px;
    font-weight: 800;
}

.settings-section-heading > div:first-child > p:last-child {
    max-width: 720px;
    margin: 4px 0 0;
    color: var(--sh-text-3);
    font-size: 10px;
    line-height: 16px;
}

.readonly-badge {
    padding: 7px 10px;
    border: 1px solid var(--sh-border);
    border-radius: 999px;
    background: var(--sh-surface);
    color: var(--sh-text-3);
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

/* ==========================================================
   CONFIGURATION GRID
   ========================================================== */

.configuration-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.configuration-card {
    min-width: 0;
    min-height: 112px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--sh-border);
    border-radius: 13px;
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.configuration-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: grid;
    place-items: center;
    border-radius: 11px;
    background: var(--sh-surface-muted);
    color: var(--sh-text-3);
}

.configuration-icon svg {
    width: 20px;
    height: 20px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.configuration-icon.success {
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.configuration-icon.warning {
    background: var(--sh-warning-bg);
    color: var(--sh-warning);
}

.configuration-icon.accent {
    background: var(--sh-accent-soft);
    color: var(--sh-accent);
}

.configuration-icon.info {
    background: var(--sh-info-bg);
    color: var(--sh-info);
}

.configuration-icon.neutral {
    background: var(--sh-surface-muted);
    color: var(--sh-text-3);
}

.configuration-card-copy {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.configuration-card-copy > span {
    color: var(--sh-text-3);
    font-size: 10px;
    font-weight: 700;
}

.configuration-card-copy strong {
    margin-top: 4px;
    color: var(--sh-text);
    font-size: 15px;
    font-weight: 800;
}

.configuration-card-copy strong.success-text {
    color: var(--sh-success);
}

.configuration-card-copy strong.warning-text {
    color: var(--sh-warning);
}

.configuration-card-copy small {
    margin-top: 4px;
    color: var(--sh-text-3);
    font-size: 9px;
    line-height: 14px;
}

/* ==========================================================
   CONFIGURATION INFORMATION
   ========================================================== */

.configuration-information {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border: 1px solid #d7e2f7;
    border-radius: 11px;
    background: var(--sh-info-bg);
}

.configuration-information-icon {
    width: 32px;
    height: 32px;
    flex: 0 0 32px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: #ffffff;
    color: var(--sh-info);
}

.configuration-information strong {
    color: #365a9c;
    font-size: 10px;
}

.configuration-information p {
    margin: 3px 0 0;
    color: #607ca9;
    font-size: 10px;
    line-height: 16px;
}

/* ==========================================================
   TEMPLATE HEADING AND SUMMARY
   ========================================================== */

.templates-heading {
    margin-top: 22px;
}

.template-summary {
    display: flex;
    align-items: center;
    gap: 8px;
}

.template-summary-item {
    min-width: 75px;
    padding: 9px 12px;
    border: 1px solid var(--sh-border);
    border-radius: 9px;
    background: var(--sh-surface);
    text-align: center;
}

.template-summary-item span {
    display: block;
    color: var(--sh-text-3);
    font-size: 9px;
}

.template-summary-item strong {
    display: block;
    margin-top: 3px;
    color: var(--sh-text);
    font-size: 16px;
}

.template-summary-item.changed {
    background: var(--sh-accent-soft);
    border-color: #eadfbf;
}

.template-summary-item.invalid {
    background: var(--sh-danger-bg);
    border-color: #eed3d3;
}

/* ==========================================================
   SNAPSHOT INFORMATION
   ========================================================== */

.snapshot-banner {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 13px 15px;
    border: 1px solid #eadfbf;
    border-radius: 13px;
    background: var(--sh-accent-soft);
}

.snapshot-banner-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #ffffff;
    color: var(--sh-accent);
}

.snapshot-banner-icon svg {
    width: 18px;
    height: 18px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.snapshot-banner-copy {
    min-width: 0;
    flex: 1;
}

.snapshot-banner-copy strong {
    color: #7c5a22;
    font-size: 11px;
}

.snapshot-banner-copy p {
    margin: 4px 0 0;
    color: #957541;
    font-size: 10px;
    line-height: 16px;
}

.snapshot-badge {
    padding: 7px 10px;
    border: 1px solid #ead9b2;
    border-radius: 999px;
    background: #ffffff;
    color: #896221;
    font-size: 9px;
    font-weight: 800;
    white-space: nowrap;
}

/* ==========================================================
   TEMPLATE LIST
   ========================================================== */

.templates-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.template-card {
    overflow: hidden;
    border: 1px solid var(--sh-border);
    border-radius: var(--sh-radius-lg);
    background: var(--sh-surface);
    box-shadow: var(--sh-shadow-sm);
}

.template-card.dirty {
    border-color: #e7d5a9;
}

.template-card.invalid {
    border-color: #e8c8c8;
}

.template-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    padding: 16px;
    border-bottom: 1px solid var(--sh-border);
    background: #fcfdfe;
}

.template-heading-main {
    min-width: 0;
    flex: 1;
    display: flex;
    align-items: flex-start;
    gap: 11px;
}

.template-number {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: var(--sh-primary);
    color: var(--sh-accent);
    font-size: 10px;
    font-weight: 800;
}

.template-heading-copy {
    min-width: 0;
}

.template-title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.template-title-row h4 {
    margin: 0;
    color: var(--sh-text);
    font-size: 14px;
    font-weight: 800;
}

.template-title-row code {
    padding: 4px 7px;
    border: 1px solid var(--sh-border);
    border-radius: 6px;
    background: #f3f5f7;
    color: var(--sh-text-3);
    font-size: 10px;
}

.unsaved-badge {
    padding: 5px 8px;
    border-radius: 999px;
    background: var(--sh-accent-soft);
    color: #845d1d;
    font-size: 9px;
    font-weight: 800;
}

.template-heading-copy p {
    margin: 6px 0 0;
    color: var(--sh-text-3);
    font-size: 10px;
    line-height: 16px;
}

.template-meta {
    flex: 0 0 auto;
    display: flex;
    align-items: flex-end;
    flex-direction: column;
    gap: 4px;
    text-align: right;
}

.template-meta > span {
    color: var(--sh-text-3);
    font-size: 9px;
}

.template-meta strong {
    color: var(--sh-text-2);
    font-size: 10px;
}

.template-meta small {
    color: var(--sh-text-3);
    font-size: 9px;
}

/* ==========================================================
   PLACEHOLDERS
   ========================================================== */

.placeholder-section {
    padding: 15px 16px 0;
}

.placeholder-heading {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 9px;
}

.field-label {
    display: block;
    color: var(--sh-text-2);
    font-size: 11px;
    font-weight: 800;
}

.placeholder-heading small,
.editor-heading small {
    display: block;
    margin-top: 3px;
    color: var(--sh-text-3);
    font-size: 10px;
}

.placeholder-count {
    color: var(--sh-text-3);
    font-size: 10px;
    white-space: nowrap;
}

.placeholder-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.placeholder-button {
    min-height: 32px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 10px;
    border: 1px solid #e1e5ea;
    border-radius: 7px;
    background: #f8fafb;
    color: #59636f;
    font: inherit;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
}

.placeholder-button:hover:not(:disabled) {
    border-color: #e0c58f;
    background: var(--sh-accent-soft);
    color: #805a1e;
}

.placeholder-button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.placeholder-button svg {
    width: 12px;
    height: 12px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
}

/* ==========================================================
   TEMPLATE EDITOR
   ========================================================== */

.template-editor {
    display: block;
    padding: 15px 16px 0;
}

.editor-heading {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.character-counter {
    color: var(--sh-text-3);
    font-size: 11px;
    font-weight: 800;
}

.character-counter.warning {
    color: var(--sh-warning);
}

.character-counter.invalid {
    color: var(--sh-danger);
}

.template-editor textarea {
    display: block;
    width: 100%;
    min-height: 132px;
    box-sizing: border-box;
    resize: vertical;
    padding: 12px;
    border: 1px solid var(--sh-border-strong);
    border-radius: 10px;
    outline: none;
    background: #ffffff;
    color: var(--sh-text);
    font: inherit;
    font-size: 12px;
    line-height: 19px;
}

.template-editor textarea:focus {
    border-color: rgba(214, 154, 45, 0.7);
    box-shadow: 0 0 0 3px rgba(214, 154, 45, 0.08);
}

.template-editor textarea.invalid {
    border-color: #dca9a9;
}

.template-editor textarea:disabled {
    opacity: 0.65;
    background: #f7f8fa;
}

.character-progress {
    height: 4px;
    overflow: hidden;
    margin-top: 7px;
    border-radius: 999px;
    background: #edf0f3;
}

.character-progress-fill {
    height: 100%;
    border-radius: inherit;
    background: var(--sh-success);
    transition: width .15s ease;
}

.character-progress-fill.warning {
    background: var(--sh-warning);
}

.character-progress-fill.invalid {
    background: var(--sh-danger);
}

/* ==========================================================
   TEMPLATE VALIDATION / MESSAGES
   ========================================================== */

.template-validation-warning {
    margin: 11px 16px 0;
    padding: 10px 12px;
    border: 1px solid #eed3d3;
    border-radius: 8px;
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
    font-size: 10px;
}

.template-message {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 11px 16px 0;
    padding: 11px 12px;
    border: 1px solid transparent;
    border-radius: 9px;
}

.template-message.error {
    border-color: #eed3d3;
    background: var(--sh-danger-bg);
    color: var(--sh-danger);
}

.template-message.success {
    border-color: #d4ebdf;
    background: var(--sh-success-bg);
    color: var(--sh-success);
}

.template-message strong {
    font-size: 11px;
}

.template-message span {
    font-size: 10px;
}

/* ==========================================================
   TEMPLATE FOOTER
   ========================================================== */

.template-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 15px;
    padding: 13px 16px;
    border-top: 1px solid var(--sh-border);
    background: #fcfdfe;
}

.template-footer-note {
    min-width: 0;
    max-width: 680px;
    display: flex;
    align-items: flex-start;
    gap: 7px;
    color: var(--sh-text-3);
    font-size: 10px;
    line-height: 16px;
}

.template-footer-note svg {
    width: 15px;
    height: 15px;
    flex: 0 0 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.template-actions {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 8px;
}

.template-actions button {
    min-height: 39px;
}

.template-save-button {
    min-width: 135px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}

.template-save-button svg {
    width: 15px;
    height: 15px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
}

.button-spinner {
    width: 14px;
    height: 14px;
    border: 2px solid currentColor;
    border-right-color: transparent;
    border-radius: 50%;
    animation: settings-spin .7s linear infinite;
}

.spinning {
    animation: settings-spin .8s linear infinite;
}

@keyframes settings-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ==========================================================
   RESPONSIVE LAYOUT
   ========================================================== */

@media (max-width: 1100px) {
    .settings-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .settings-intro-actions {
        width: 100%;
    }

    .settings-loaded-card {
        flex: 1;
    }

    .configuration-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 850px) {
    .template-header {
        flex-direction: column;
    }

    .template-meta {
        align-items: flex-start;
        text-align: left;
    }

    .settings-section-heading {
        align-items: flex-start;
        flex-direction: column;
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
        font-size: 20px;
    }

    .settings-intro-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .settings-loaded-card,
    .settings-refresh-button {
        width: 100%;
    }

    .security-banner,
    .snapshot-banner {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .security-badge,
    .snapshot-badge {
        margin-left: 49px;
    }

    .configuration-grid {
        grid-template-columns: 1fr;
    }

    .placeholder-heading,
    .editor-heading {
        flex-direction: column;
        align-items: flex-start;
    }

    .template-footer {
        align-items: stretch;
        flex-direction: column;
    }

    .template-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .template-actions button {
        width: 100%;
    }

    .settings-error {
        flex-wrap: wrap;
    }
}
</style>
