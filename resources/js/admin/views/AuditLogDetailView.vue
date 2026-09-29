<script setup>
import {
    computed,
    onMounted,
    ref,
    watch,
} from 'vue';

import {
    useRoute,
    useRouter,
} from 'vue-router';

import { apiRequest } from '../services/api';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const auditLog = ref(null);
const isLoading = ref(false);
const errorMessage = ref('');

const auditLogId =
    computed(
        () =>
            route.params.auditLogId,
    );


/*
|--------------------------------------------------------------------------
| METADATA
|--------------------------------------------------------------------------
*/

const metadataEntries =
    computed(() => {
        const metadata =
            auditLog.value
                ?.metadata;

        if (
            !metadata
            ||
            typeof metadata !== 'object'
            ||
            Array.isArray(metadata)
        ) {
            return [];
        }

        return Object.entries(
            metadata,
        );
    });


/*
|--------------------------------------------------------------------------
| RESOURCE ROUTING
|--------------------------------------------------------------------------
*/

const isAdministratorAccountResource =
    computed(
        () => (
            auditLog.value
                ?.resource
                ?.type
                === 'user'

            &&
            auditLog.value
                ?.resource
                ?.id

            &&
            auditLog.value
                ?.actor
                ?.role
                === 'admin'

            &&
            Number(
                auditLog.value
                    ?.actor
                    ?.id,
            )
                ===
                Number(
                    auditLog.value
                        ?.resource
                        ?.id,
                )

            &&
            [
                'admin_account_updated',
                'admin_password_changed',
            ].includes(
                auditLog.value
                    ?.event
                    ?.value,
            )
        ),
    );

const isSmsTemplateResource =
    computed(
        () => (
            auditLog.value
                ?.resource
                ?.type
                === 'sms_template'

            &&
            auditLog.value
                ?.event
                ?.value
                === 'sms_template_updated'
        ),
    );

const targetRoute =
    computed(() => {
        const resource =
            auditLog.value
                ?.resource;

        if (
            !resource?.id
            ||
            !resource?.type
        ) {
            return null;
        }

        if (
            isAdministratorAccountResource.value
        ) {
            return {
                name:
                    'admin.account-settings',
            };
        }

        if (
            isSmsTemplateResource.value
        ) {
            return {
                name:
                    'admin.system-settings',
            };
        }

        switch (
            resource.type
        ) {
            case 'user':
                return {
                    name:
                        'admin.user.detail',

                    params: {
                        userId:
                            resource.id,
                    },
                };

            case 'shop':
                return {
                    name:
                        'admin.shop.detail',

                    params: {
                        shopId:
                            resource.id,
                    },
                };

            case 'appointment':
                return {
                    name:
                        'admin.appointment.detail',

                    params: {
                        appointmentId:
                            resource.id,
                    },
                };

            case 'feedback':
                return {
                    name:
                        'admin.feedback.detail',

                    params: {
                        feedbackId:
                            resource.id,
                    },
                };

            default:
                return null;
        }
    });

const resourceActionLabel =
    computed(() => {
        if (
            isAdministratorAccountResource.value
        ) {
            return 'Open Account Settings';
        }

        if (
            isSmsTemplateResource.value
        ) {
            return 'Open System Settings';
        }

        return 'View Resource';
    });


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
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            timeZone: 'Asia/Manila',
        },
    ).format(date);
}

function formatMetadataValue(value) {
    if (
        value === null
    ) {
        return 'null';
    }

    if (
        typeof value === 'object'
    ) {
        try {
            return JSON.stringify(
                value,
                null,
                2,
            );
        } catch {
            return '[Unable to display value]';
        }
    }

    return String(value);
}

function eventTone(value) {
    const event =
        String(
            value ?? '',
        );

    if (
        event.includes('approved')
        ||
        event.includes('reactivated')
        ||
        event.includes('completed')
        ||
        event.includes('confirmed')
        ||
        event.includes('created')
        ||
        event.includes('submitted')
    ) {
        return 'positive';
    }

    if (
        event.includes('suspended')
        ||
        event.includes('rejected')
        ||
        event.includes('declined')
        ||
        event.includes('cancelled')
        ||
        event.includes('deleted')
        ||
        event.includes('no_show')
    ) {
        return 'negative';
    }

    if (
        event.includes('updated')
        ||
        event.includes('changed')
        ||
        event.includes('requested')
        ||
        event.includes('retry')
    ) {
        return 'warning';
    }

    return 'neutral';
}

function actorRoleClass(value) {
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
| LOAD AUDIT LOG
|--------------------------------------------------------------------------
*/

async function loadAuditLog() {
    if (!auth.token) {
        await authenticationFailure();

        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response =
            await apiRequest(
                `/admin/audit-logs/${encodeURIComponent(
                    auditLogId.value,
                )}`,
                {
                    token:
                        auth.token,
                },
            );

        auditLog.value =
            response?.data
                ?.audit_log
            ?? null;

        if (!auditLog.value) {
            throw new Error(
                'The audit log response was invalid.',
            );
        }
    } catch (error) {
        if (
            error?.status === 401
            ||
            error?.status === 403
        ) {
            await authenticationFailure();

            return;
        }

        errorMessage.value =
            error?.status === 404
                ? 'The requested audit log could not be found.'
                : error?.data?.message
                    ?? error?.message
                    ?? 'Unable to load the audit log.';
    } finally {
        isLoading.value = false;
    }
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

async function goBack() {
    await router.push({
        name:
            'admin.audit-logs',
    });
}

async function openAffectedResource() {
    if (!targetRoute.value) {
        return;
    }

    await router.push(
        targetRoute.value,
    );
}


/*
|--------------------------------------------------------------------------
| ROUTE WATCH
|--------------------------------------------------------------------------
*/

watch(
    () =>
        route.params
            .auditLogId,

    () => {
        loadAuditLog();
    },
);


/*
|--------------------------------------------------------------------------
| INITIAL LOAD
|--------------------------------------------------------------------------
*/

onMounted(() => {
    loadAuditLog();
});
</script>


<template>
    <div class="detail-page audit-detail-page">
        <!-- ============================================================
             NAVIGATION
             ============================================================ -->

        <div class="audit-detail-navigation">
            <button
                type="button"
                class="back-button"
                @click="
                    goBack
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
                    Back to Audit Logs
                </span>
            </button>


            <button
                v-if="
                    auditLog
                "
                type="button"
                class="secondary-button audit-refresh-button"
                :disabled="
                    isLoading
                "
                @click="
                    loadAuditLog
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
                    Refresh
                </span>
            </button>
        </div>


        <!-- ============================================================
             ERROR
             ============================================================ -->

        <div
            v-if="
                errorMessage
            "
            class="error-alert audit-detail-error"
            role="alert"
        >
            <div class="audit-error-icon">
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

            <div class="audit-error-copy">
                <strong>
                    Unable to load audit log
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
                    loadAuditLog
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
                !auditLog
            "
            class="loading-card audit-detail-loading"
        >
            <div class="audit-loading-icon">
                <div class="spinner" />
            </div>

            <h3>
                Loading audit record
            </h3>

            <p>
                Retrieving the immutable event,
                actor, resource and request context.
            </p>
        </section>


        <!-- ============================================================
             CONTENT
             ============================================================ -->

        <template
            v-else-if="
                auditLog
            "
        >
            <!-- ========================================================
                 HERO
                 ======================================================== -->

            <section class="audit-record-hero">
                <div class="audit-record-main">
                    <div
                        class="audit-record-icon"
                        :class="
                            eventTone(
                                auditLog.event
                                    ?.value
                            )
                        "
                    >
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


                    <div class="audit-record-copy">
                        <div class="audit-record-eyebrow">
                            <span class="audit-record-dot" />

                            Immutable audit record

                            <span class="audit-record-id">
                                #{{ auditLog.id }}
                            </span>
                        </div>

                        <h2>
                            {{
                                auditLog.event
                                    ?.label
                                ||
                                humanize(
                                    auditLog.event
                                        ?.value
                                )
                            }}
                        </h2>

                        <div class="audit-record-meta">
                            <div class="audit-meta-item">
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
                                    {{
                                        formatDateTime(
                                            auditLog.created_at
                                        )
                                    }}
                                </span>
                            </div>

                            <span class="audit-meta-divider" />

                            <div class="audit-meta-item">
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
                                        humanize(
                                            auditLog.actor
                                                ?.role
                                        )
                                    }}
                                </span>
                            </div>

                            <span class="audit-meta-divider" />

                            <div class="audit-meta-item">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M5 4h14v16H5V4z"
                                    />

                                    <path
                                        d="M9 8h6M9 12h6M9 16h4"
                                    />
                                </svg>

                                <span>
                                    {{
                                        auditLog.resource
                                            ?.type_label
                                        ||
                                        humanize(
                                            auditLog.resource
                                                ?.type
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="audit-hero-right">
                    <code class="audit-event-code">
                        {{
                            auditLog.event
                                ?.value
                            || 'unknown'
                        }}
                    </code>

                    <span
                        class="audit-event-tone"
                        :class="
                            eventTone(
                                auditLog.event
                                    ?.value
                            )
                        "
                    >
                        <span />

                        Recorded event
                    </span>
                </div>
            </section>


            <!-- ========================================================
                 IMMUTABILITY NOTICE
                 ======================================================== -->

            <section class="audit-readonly-banner">
                <div class="audit-readonly-icon">
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

                <div class="audit-readonly-copy">
                    <strong>
                        Read-only security history
                    </strong>

                    <p>
                        Audit records cannot be edited or deleted
                        through the Administrator interface.
                        Passwords, provider credentials and SMS
                        template contents are not stored in this
                        audit record.
                    </p>
                </div>

                <span class="audit-readonly-badge">
                    Immutable
                </span>
            </section>


            <!-- ========================================================
                 SUMMARY
                 ======================================================== -->

            <section class="audit-summary-grid">
                <!-- ACTOR -->

                <article class="audit-summary-card">
                    <div
                        class="summary-icon actor"
                        :class="
                            actorRoleClass(
                                auditLog.actor
                                    ?.role
                            )
                        "
                    >
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

                    <div class="summary-copy">
                        <span>
                            Actor
                        </span>

                        <strong class="summary-text">
                            {{
                                humanize(
                                    auditLog.actor
                                        ?.role
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                auditLog.actor
                                    ?.id
                                    ? `User #${auditLog.actor.id}`
                                    : 'System / unavailable'
                            }}
                        </small>
                    </div>
                </article>


                <!-- RESOURCE -->

                <article class="audit-summary-card">
                    <div class="summary-icon resource">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M5 4h14v16H5V4z"
                            />

                            <path
                                d="M9 8h6M9 12h6M9 16h4"
                            />
                        </svg>
                    </div>

                    <div class="summary-copy">
                        <span>
                            Resource
                        </span>

                        <strong class="summary-text">
                            {{
                                auditLog.resource
                                    ?.type_label
                                ||
                                humanize(
                                    auditLog.resource
                                        ?.type
                                )
                            }}
                        </strong>

                        <small>
                            {{
                                auditLog.resource
                                    ?.id
                                    ? `#${auditLog.resource.id}`
                                    : 'Unavailable'
                            }}
                        </small>
                    </div>
                </article>


                <!-- IP -->

                <article class="audit-summary-card">
                    <div class="summary-icon network">
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
                                d="M3 12h18"
                            />

                            <path
                                d="M12 3c3 3 4 6 4 9s-1 6-4 9"
                            />

                            <path
                                d="M12 3c-3 3-4 6-4 9s1 6 4 9"
                            />
                        </svg>
                    </div>

                    <div class="summary-copy">
                        <span>
                            IP address
                        </span>

                        <strong class="summary-code">
                            {{
                                auditLog.request_context
                                    ?.ip_address
                                || '—'
                            }}
                        </strong>

                        <small>
                            Request source
                        </small>
                    </div>
                </article>


                <!-- TIME -->

                <article class="audit-summary-card">
                    <div class="summary-icon timestamp">
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

                    <div class="summary-copy">
                        <span>
                            Recorded
                        </span>

                        <strong class="summary-date">
                            {{
                                formatDateTime(
                                    auditLog.created_at
                                )
                            }}
                        </strong>

                        <small>
                            Asia/Manila
                        </small>
                    </div>
                </article>
            </section>


            <!-- ========================================================
                 DETAIL GRID
                 ======================================================== -->

            <section class="audit-information-grid">
                <!-- ====================================================
                     EVENT INFORMATION
                     ==================================================== -->

                <article class="detail-card audit-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div
                                class="information-icon event"
                                :class="
                                    eventTone(
                                        auditLog.event
                                            ?.value
                                    )
                                "
                            >
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

                            <div>
                                <p class="eyebrow">
                                    EVENT
                                </p>

                                <h3>
                                    Event information
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="event-preview">
                        <div
                            class="event-preview-icon"
                            :class="
                                eventTone(
                                    auditLog.event
                                        ?.value
                                )
                            "
                        >
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
                        </div>

                        <div class="event-preview-copy">
                            <span>
                                Recorded action
                            </span>

                            <strong>
                                {{
                                    auditLog.event
                                        ?.label
                                    ||
                                    humanize(
                                        auditLog.event
                                            ?.value
                                    )
                                }}
                            </strong>
                        </div>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Audit Log ID
                            </dt>

                            <dd class="code-value">
                                #{{ auditLog.id }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Event label
                            </dt>

                            <dd>
                                {{
                                    auditLog.event
                                        ?.label
                                    ||
                                    humanize(
                                        auditLog.event
                                            ?.value
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Event value
                            </dt>

                            <dd>
                                <code class="inline-code">
                                    {{
                                        auditLog.event
                                            ?.value
                                        || '—'
                                    }}
                                </code>
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Recorded
                            </dt>

                            <dd>
                                {{
                                    formatDateTime(
                                        auditLog.created_at
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ====================================================
                     ACTOR
                     ==================================================== -->

                <article class="detail-card audit-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div
                                class="information-icon actor"
                                :class="
                                    actorRoleClass(
                                        auditLog.actor
                                            ?.role
                                    )
                                "
                            >
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
                                    ACTOR
                                </p>

                                <h3>
                                    Actor snapshot
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="actor-preview">
                        <div
                            class="actor-avatar"
                            :class="
                                actorRoleClass(
                                    auditLog.actor
                                        ?.role
                                )
                            "
                        >
                            {{
                                auditLog.actor
                                    ?.role
                                    ? String(
                                        humanize(
                                            auditLog.actor
                                                .role
                                        )
                                    )
                                        .charAt(0)
                                        .toUpperCase()
                                    : 'S'
                            }}
                        </div>

                        <div class="actor-preview-copy">
                            <span>
                                Actor role
                            </span>

                            <strong>
                                {{
                                    humanize(
                                        auditLog.actor
                                            ?.role
                                    )
                                }}
                            </strong>

                            <small>
                                {{
                                    auditLog.actor
                                        ?.id
                                        ? `User #${auditLog.actor.id}`
                                        : 'System / unavailable'
                                }}
                            </small>
                        </div>

                        <span
                            v-if="
                                auditLog.actor
                                    ?.role
                            "
                            class="actor-role-badge"
                            :class="
                                actorRoleClass(
                                    auditLog.actor
                                        .role
                                )
                            "
                        >
                            {{
                                humanize(
                                    auditLog.actor
                                        .role
                                )
                            }}
                        </span>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Actor ID
                            </dt>

                            <dd class="code-value">
                                {{
                                    auditLog.actor
                                        ?.id
                                        ? `#${auditLog.actor.id}`
                                        : '—'
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Role
                            </dt>

                            <dd>
                                {{
                                    humanize(
                                        auditLog.actor
                                            ?.role
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>


                <!-- ====================================================
                     RESOURCE
                     ==================================================== -->

                <article class="detail-card audit-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon resource">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M5 4h14v16H5V4z"
                                    />

                                    <path
                                        d="M9 8h6M9 12h6M9 16h4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    RESOURCE
                                </p>

                                <h3>
                                    Affected resource
                                </h3>
                            </div>
                        </div>


                        <button
                            v-if="
                                targetRoute
                            "
                            type="button"
                            class="resource-link-button"
                            @click="
                                openAffectedResource
                            "
                        >
                            <span>
                                {{ resourceActionLabel }}
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


                    <div class="resource-preview">
                        <div class="resource-preview-icon">
                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    d="M5 4h14v16H5V4z"
                                />

                                <path
                                    d="M9 8h6M9 12h6M9 16h4"
                                />
                            </svg>
                        </div>

                        <div class="resource-preview-copy">
                            <span>
                                Resource type
                            </span>

                            <strong>
                                {{
                                    auditLog.resource
                                        ?.type_label
                                    ||
                                    humanize(
                                        auditLog.resource
                                            ?.type
                                    )
                                }}
                            </strong>

                            <small>
                                {{
                                    auditLog.resource
                                        ?.id
                                        ? `Resource #${auditLog.resource.id}`
                                        : 'No resource identifier'
                                }}
                            </small>
                        </div>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                Type
                            </dt>

                            <dd>
                                {{
                                    auditLog.resource
                                        ?.type_label
                                    ||
                                    humanize(
                                        auditLog.resource
                                            ?.type
                                    )
                                }}
                            </dd>
                        </div>

                        <div class="information-row">
                            <dt>
                                Resource ID
                            </dt>

                            <dd class="code-value">
                                {{
                                    auditLog.resource
                                        ?.id
                                        ? `#${auditLog.resource.id}`
                                        : '—'
                                }}
                            </dd>
                        </div>
                    </dl>


                    <div
                        v-if="
                            targetRoute
                        "
                        class="resource-destination-note"
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
                                d="M12 11v5M12 8h.01"
                            />
                        </svg>

                        <span>
                            A safe Administrator destination is
                            available for this affected resource.
                        </span>
                    </div>

                    <div
                        v-else
                        class="resource-destination-note unavailable"
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
                                d="M12 11v5M12 8h.01"
                            />
                        </svg>

                        <span>
                            This resource type does not have a safe
                            Administrator detail destination.
                        </span>
                    </div>
                </article>


                <!-- ====================================================
                     REQUEST CONTEXT
                     ==================================================== -->

                <article class="detail-card audit-information-card">
                    <div class="information-card-header">
                        <div class="information-heading">
                            <div class="information-icon request">
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
                                        d="M3 12h18"
                                    />

                                    <path
                                        d="M12 3c3 3 4 6 4 9s-1 6-4 9"
                                    />

                                    <path
                                        d="M12 3c-3 3-4 6-4 9s1 6 4 9"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p class="eyebrow">
                                    REQUEST
                                </p>

                                <h3>
                                    Request context
                                </h3>
                            </div>
                        </div>
                    </div>


                    <div class="network-preview">
                        <div class="network-preview-icon">
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
                                    d="M3 12h18"
                                />

                                <path
                                    d="M12 3c3 3 4 6 4 9s-1 6-4 9"
                                />
                            </svg>
                        </div>

                        <div>
                            <span>
                                Recorded IP address
                            </span>

                            <code>
                                {{
                                    auditLog.request_context
                                        ?.ip_address
                                    || 'Unavailable'
                                }}
                            </code>
                        </div>
                    </div>


                    <dl class="information-list">
                        <div class="information-row">
                            <dt>
                                IP address
                            </dt>

                            <dd>
                                <code class="inline-code">
                                    {{
                                        auditLog.request_context
                                            ?.ip_address
                                        || '—'
                                    }}
                                </code>
                            </dd>
                        </div>

                        <div class="information-row user-agent-row">
                            <dt>
                                User agent
                            </dt>

                            <dd class="wrap-value">
                                {{
                                    auditLog.request_context
                                        ?.user_agent
                                    || '—'
                                }}
                            </dd>
                        </div>
                    </dl>
                </article>
            </section>


            <!-- ========================================================
                 METADATA
                 ======================================================== -->

            <section class="detail-card audit-metadata-card">
                <div class="metadata-card-header">
                    <div class="information-heading">
                        <div class="information-icon metadata">
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
                        </div>

                        <div>
                            <p class="eyebrow">
                                SAFE METADATA
                            </p>

                            <h3>
                                Event metadata
                            </h3>

                            <p>
                                Context intentionally recorded with
                                this audit event.
                            </p>
                        </div>
                    </div>


                    <span class="metadata-count">
                        {{ metadataEntries.length }}
                        field{{
                            metadataEntries.length === 1
                                ? ''
                                : 's'
                        }}
                    </span>
                </div>


                <div
                    v-if="
                        metadataEntries.length
                    "
                    class="metadata-grid"
                >
                    <article
                        v-for="
                            ([key, value])
                            in metadataEntries
                        "
                        :key="
                            key
                        "
                        class="metadata-item"
                    >
                        <div class="metadata-item-heading">
                            <div class="metadata-key-icon">
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
                                </svg>
                            </div>

                            <div>
                                <span>
                                    {{ humanize(key) }}
                                </span>

                                <code>
                                    {{ key }}
                                </code>
                            </div>
                        </div>

                        <pre>{{
                            formatMetadataValue(
                                value
                            )
                        }}</pre>
                    </article>
                </div>


                <div
                    v-else
                    class="metadata-empty-state"
                >
                    <div class="metadata-empty-icon">
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
                                d="M8 12h8"
                            />
                        </svg>
                    </div>

                    <strong>
                        No event metadata
                    </strong>

                    <p>
                        No safe additional metadata was recorded
                        for this event.
                    </p>
                </div>


                <div class="metadata-security-note">
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

                    <div>
                        <strong>
                            Sensitive values are intentionally excluded
                        </strong>

                        <span>
                            Passwords, provider credentials and SMS
                            template contents are not displayed as
                            audit metadata.
                        </span>
                    </div>
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

.audit-detail-page {
    display: flex;
    flex-direction: column;

    gap: 14px;
}


/*
|--------------------------------------------------------------------------
| NAVIGATION
|--------------------------------------------------------------------------
*/

.audit-detail-navigation {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;
}

.audit-detail-page
.back-button {
    margin: 0;
}

.back-button svg,
.audit-refresh-button svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| ERROR
|--------------------------------------------------------------------------
*/

.audit-detail-error {
    align-items: center;

    margin: 0;
}

.audit-error-icon {
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

.audit-error-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.8;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-error-copy {
    min-width: 0;

    flex: 1;
}

.audit-error-copy strong {
    display: block;

    font-size: 10px;
}

.audit-error-copy p {
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

.audit-detail-loading {
    min-height: 350px;
}

.audit-loading-icon {
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

.audit-loading-icon
.spinner {
    margin: 0;
}

.audit-detail-loading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
}

.audit-detail-loading p {
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
| HERO
|--------------------------------------------------------------------------
*/

.audit-record-hero {
    position: relative;

    overflow: hidden;

    min-height: 170px;

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
            circle at 94% 5%,
            rgba(
                214,
                154,
                45,
                0.08
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

.audit-record-main {
    min-width: 0;

    flex: 1;

    display: flex;
    align-items: center;

    gap: 15px;
}

.audit-record-icon {
    width: 67px;
    height: 67px;

    flex: 0 0 67px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 19px;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);

    box-shadow:
        0 10px 24px
        rgba(
            23,
            32,
            42,
            0.08
        );
}

.audit-record-icon.positive {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.audit-record-icon.negative {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.audit-record-icon.warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.audit-record-icon svg {
    width: 27px;
    height: 27px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.6;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-record-copy {
    min-width: 0;

    flex: 1;
}

.audit-record-eyebrow {
    display: flex;
    align-items: center;

    gap: 7px;

    margin-bottom: 5px;

    color:
        var(--sh-accent);

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.05px;

    text-transform: uppercase;
}

.audit-record-dot {
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

.audit-record-id {
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

.audit-record-copy h2 {
    max-width: 750px;

    margin: 0;

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

.audit-record-meta {
    display: flex;
    align-items: center;

    flex-wrap: wrap;

    gap: 9px;

    margin-top: 9px;
}

.audit-meta-item {
    display: flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 9px;
}

.audit-meta-item svg {
    width: 13px;
    height: 13px;

    flex: 0 0 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-meta-divider {
    width: 3px;
    height: 3px;

    flex: 0 0 3px;

    border-radius: 999px;

    background:
        #c9ced5;
}


/*
|--------------------------------------------------------------------------
| HERO RIGHT
|--------------------------------------------------------------------------
*/

.audit-hero-right {
    flex: 0 0 auto;

    display: flex;
    align-items: flex-end;
    flex-direction: column;

    gap: 9px;
}

.audit-event-code {
    max-width: 250px;

    padding:
        7px
        9px;

    overflow: hidden;

    border:
        1px solid
        var(--sh-border);

    border-radius: 8px;

    background:
        var(--sh-surface-muted);

    color:
        #596675;

    font-size: 8px;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.audit-event-tone {
    display: inline-flex;
    align-items: center;

    gap: 5px;

    color:
        var(--sh-text-3);

    font-size: 8px;
    font-weight: 750;
}

.audit-event-tone > span {
    width: 6px;
    height: 6px;

    border-radius: 999px;

    background:
        currentColor;
}

.audit-event-tone.positive {
    color:
        var(--sh-success);
}

.audit-event-tone.negative {
    color:
        var(--sh-danger);
}

.audit-event-tone.warning {
    color:
        var(--sh-warning);
}

.audit-event-tone.neutral {
    color:
        var(--sh-info);
}


/*
|--------------------------------------------------------------------------
| READONLY
|--------------------------------------------------------------------------
*/

.audit-readonly-banner {
    display: flex;
    align-items: center;

    gap: 11px;

    padding:
        12px
        14px;

    border:
        1px solid
        #d7e2f7;

    border-radius: 13px;

    background:
        var(--sh-info-bg);

    box-shadow:
        var(--sh-shadow-sm);
}

.audit-readonly-icon {
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
        var(--sh-info);
}

.audit-readonly-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.audit-readonly-copy {
    min-width: 0;

    flex: 1;
}

.audit-readonly-copy strong {
    display: block;

    color:
        #31558e;

    font-size: 9.5px;
}

.audit-readonly-copy p {
    max-width: 860px;

    margin:
        3px
        0
        0;

    color:
        #607ca9;

    font-size: 8.5px;

    line-height: 14px;
}

.audit-readonly-badge {
    min-height: 25px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        9px;

    border:
        1px solid
        #ccdaf1;

    border-radius: 999px;

    background:
        #ffffff;

    color:
        var(--sh-info);

    font-size: 7.5px;
    font-weight: 800;

    text-transform: uppercase;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

.audit-summary-grid {
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

.audit-summary-card {
    min-width: 0;

    min-height: 105px;

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

.summary-icon {
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

.summary-icon svg {
    width: 19px;
    height: 19px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.summary-icon.actor.admin {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.summary-icon.actor.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.summary-icon.actor.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.summary-icon.resource {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.summary-icon.network {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.summary-icon.timestamp {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.summary-copy {
    min-width: 0;

    display: flex;
    flex-direction: column;
}

.summary-copy > span {
    color:
        var(--sh-text-3);

    font-size: 8.5px;
    font-weight: 700;
}

.summary-copy strong {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-weight: 800;
}

.summary-text {
    font-size: 13px;
}

.summary-code {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 10px;
}

.summary-date {
    font-size: 9px;

    line-height: 14px;
}

.summary-copy small {
    margin-top: 3px;

    color:
        var(--sh-text-3);

    font-size: 7.8px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION GRID
|--------------------------------------------------------------------------
*/

.audit-information-grid {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 14px;
}

.audit-information-card {
    min-width: 0;

    padding: 0;

    overflow: hidden;
}

.information-card-header {
    min-height: 68px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        13px
        15px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.information-heading {
    min-width: 0;

    display: flex;
    align-items: center;

    gap: 10px;
}

.information-icon {
    width: 36px;
    height: 36px;

    flex: 0 0 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 10px;
}

.information-icon svg {
    width: 17px;
    height: 17px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.information-icon.event,
.information-icon.request {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.information-icon.event.positive {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.information-icon.event.negative {
    background:
        var(--sh-danger-bg);

    color:
        var(--sh-danger);
}

.information-icon.event.warning {
    background:
        var(--sh-warning-bg);

    color:
        var(--sh-warning);
}

.information-icon.actor.admin {
    background:
        var(--sh-accent-soft);

    color:
        var(--sh-accent);
}

.information-icon.actor.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.information-icon.actor.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.information-icon.resource {
    background:
        var(--sh-success-bg);

    color:
        var(--sh-success);
}

.information-icon.metadata {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.information-heading
.eyebrow {
    margin-bottom: 2px;

    font-size: 7.5px;
}

.information-heading h3 {
    margin: 0;

    color:
        var(--sh-text);

    font-size: 12px;
    font-weight: 800;
}

.information-heading
p:last-child {
    margin:
        2px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8px;
}


/*
|--------------------------------------------------------------------------
| EVENT PREVIEW
|--------------------------------------------------------------------------
*/

.event-preview,
.actor-preview,
.resource-preview,
.network-preview {
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
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.event-preview-icon,
.resource-preview-icon,
.network-preview-icon {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        #ffffff;

    color:
        var(--sh-info);
}

.event-preview-icon.positive {
    color:
        var(--sh-success);
}

.event-preview-icon.negative {
    color:
        var(--sh-danger);
}

.event-preview-icon.warning {
    color:
        var(--sh-warning);
}

.event-preview-icon svg,
.resource-preview-icon svg,
.network-preview-icon svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.event-preview-copy,
.resource-preview-copy,
.network-preview > div:last-child {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.event-preview-copy span,
.resource-preview-copy span,
.network-preview span {
    color:
        var(--sh-text-3);

    font-size: 8px;
}

.event-preview-copy strong,
.resource-preview-copy strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.event-preview-copy strong {
    overflow-wrap: anywhere;
}

.resource-preview-copy small {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}


/*
|--------------------------------------------------------------------------
| ACTOR PREVIEW
|--------------------------------------------------------------------------
*/

.actor-preview {
    background:
        var(--sh-surface-muted);
}

.actor-avatar {
    width: 39px;
    height: 39px;

    flex: 0 0 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 11px;

    background:
        #ffffff;

    color:
        var(--sh-text-3);

    font-size: 11px;
    font-weight: 800;
}

.actor-avatar.admin {
    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.actor-avatar.customer {
    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.actor-avatar.shop-owner {
    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}

.actor-preview-copy {
    min-width: 0;

    flex: 1;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.actor-preview-copy span {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.actor-preview-copy strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.actor-preview-copy small {
    color:
        var(--sh-text-3);

    font-size: 7.5px;
}

.actor-role-badge {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

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

    white-space: nowrap;
}

.actor-role-badge.admin {
    border-color:
        #eadfbf;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.actor-role-badge.customer {
    border-color:
        #d7e2f7;

    background:
        var(--sh-info-bg);

    color:
        var(--sh-info);
}

.actor-role-badge.shop-owner {
    border-color:
        #e4ddf8;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);
}


/*
|--------------------------------------------------------------------------
| NETWORK
|--------------------------------------------------------------------------
*/

.network-preview {
    border-color:
        #d7e2f7;

    background:
        var(--sh-info-bg);
}

.network-preview-icon {
    color:
        var(--sh-info);
}

.network-preview code {
    margin-top: 2px;

    color:
        var(--sh-text);

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 9px;
}


/*
|--------------------------------------------------------------------------
| RESOURCE BUTTON
|--------------------------------------------------------------------------
*/

.resource-link-button {
    min-height: 33px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 5px;

    padding:
        0
        9px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 8px;

    background:
        #ffffff;

    color:
        var(--sh-text-2);

    font: inherit;

    font-size: 8px;
    font-weight: 750;

    cursor: pointer;
}

.resource-link-button:hover {
    border-color:
        #d6c18d;

    background:
        var(--sh-accent-soft);

    color:
        #845d1d;
}

.resource-link-button svg {
    width: 11px;
    height: 11px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.9;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| INFORMATION LIST
|--------------------------------------------------------------------------
*/

.information-list {
    margin: 0;

    padding:
        4px
        15px;
}

.information-row {
    min-height: 49px;

    display: grid;

    grid-template-columns:
        minmax(
            120px,
            0.8fr
        )
        minmax(
            0,
            1.5fr
        );

    align-items: center;

    gap: 15px;

    padding:
        8px
        0;

    border-bottom:
        1px solid
        #edf0f3;
}

.information-row:last-child {
    border-bottom: 0;
}

.information-row dt {
    color:
        var(--sh-text-3);

    font-size: 9px;
    font-weight: 650;
}

.information-row dd {
    min-width: 0;

    margin: 0;

    overflow-wrap: anywhere;

    color:
        var(--sh-text-2);

    font-size: 9.5px;
    font-weight: 650;

    text-align: right;
}

.code-value {
    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;
}

.inline-code {
    display: inline-block;

    max-width: 100%;

    padding:
        4px
        6px;

    overflow-wrap: anywhere;

    border:
        1px solid
        var(--sh-border);

    border-radius: 6px;

    background:
        var(--sh-surface-muted);

    color:
        #59636f;

    font-size: 7.8px;
}

.user-agent-row {
    align-items: flex-start;
}

.user-agent-row dd {
    line-height: 15px;
}


/*
|--------------------------------------------------------------------------
| RESOURCE NOTE
|--------------------------------------------------------------------------
*/

.resource-destination-note {
    display: flex;
    align-items: flex-start;

    gap: 6px;

    margin:
        6px
        15px
        15px;

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

    font-size: 8px;

    line-height: 13px;
}

.resource-destination-note.unavailable {
    border-color:
        var(--sh-border);

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.resource-destination-note svg {
    width: 12px;
    height: 12px;

    flex: 0 0 12px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}


/*
|--------------------------------------------------------------------------
| METADATA
|--------------------------------------------------------------------------
*/

.audit-metadata-card {
    padding: 0;

    overflow: hidden;
}

.metadata-card-header {
    min-height: 72px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding:
        13px
        15px;

    border-bottom:
        1px solid
        var(--sh-border);
}

.metadata-count {
    min-height: 24px;

    display: inline-flex;
    align-items: center;

    padding:
        0
        8px;

    border:
        1px solid
        #e4ddf8;

    border-radius: 999px;

    background:
        var(--sh-purple-bg);

    color:
        var(--sh-purple);

    font-size: 7.5px;
    font-weight: 800;

    white-space: nowrap;
}

.metadata-grid {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 10px;

    padding:
        15px;
}

.metadata-item {
    min-width: 0;

    padding:
        11px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 11px;

    background:
        var(--sh-surface-muted);
}

.metadata-item-heading {
    display: flex;
    align-items: center;

    gap: 8px;
}

.metadata-key-icon {
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
        var(--sh-purple);
}

.metadata-key-icon svg {
    width: 13px;
    height: 13px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.metadata-item-heading
> div:last-child {
    min-width: 0;

    display: flex;
    flex-direction: column;

    gap: 1px;
}

.metadata-item-heading span {
    color:
        var(--sh-text);

    font-size: 9px;
    font-weight: 750;
}

.metadata-item-heading code {
    overflow: hidden;

    color:
        var(--sh-text-3);

    font-size: 7px;

    text-overflow: ellipsis;

    white-space: nowrap;
}

.metadata-item pre {
    max-width: 100%;

    max-height: 260px;

    overflow: auto;

    margin:
        10px
        0
        0;

    padding:
        9px
        10px;

    border:
        1px solid
        var(--sh-border);

    border-radius: 8px;

    background:
        #ffffff;

    color:
        #4f5965;

    font-family:
        ui-monospace,
        SFMono-Regular,
        Menlo,
        Monaco,
        Consolas,
        monospace;

    font-size: 7.8px;

    line-height: 14px;

    white-space: pre-wrap;

    overflow-wrap: anywhere;
}


/*
|--------------------------------------------------------------------------
| METADATA EMPTY
|--------------------------------------------------------------------------
*/

.metadata-empty-state {
    min-height: 210px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding: 30px;

    text-align: center;
}

.metadata-empty-icon {
    width: 46px;
    height: 46px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 9px;

    border-radius: 13px;

    background:
        var(--sh-surface-muted);

    color:
        var(--sh-text-3);
}

.metadata-empty-icon svg {
    width: 20px;
    height: 20px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.metadata-empty-state strong {
    color:
        var(--sh-text);

    font-size: 10px;
}

.metadata-empty-state p {
    margin:
        4px
        0
        0;

    color:
        var(--sh-text-3);

    font-size: 8.5px;
}


/*
|--------------------------------------------------------------------------
| METADATA SECURITY
|--------------------------------------------------------------------------
*/

.metadata-security-note {
    display: flex;
    align-items: flex-start;

    gap: 8px;

    margin:
        0
        15px
        15px;

    padding:
        10px
        11px;

    border:
        1px solid
        #d4ebdf;

    border-radius: 10px;

    background:
        var(--sh-success-bg);
}

.metadata-security-note
> svg {
    width: 15px;
    height: 15px;

    flex: 0 0 15px;

    fill: none;

    stroke:
        var(--sh-success);

    stroke-width: 1.7;

    stroke-linecap: round;
    stroke-linejoin: round;
}

.metadata-security-note
> div {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.metadata-security-note strong {
    color:
        var(--sh-success);

    font-size: 8.5px;
}

.metadata-security-note span {
    color:
        var(--sh-text-3);

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

.spinning {
    animation:
        audit-detail-spin
        0.8s
        linear
        infinite;
}

@keyframes audit-detail-spin {
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

@media (max-width: 1050px) {
    .audit-record-hero {
        align-items:
            flex-start;

        flex-direction:
            column;
    }

    .audit-hero-right {
        align-items:
            flex-start;
    }

    .audit-summary-grid {
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


@media (max-width: 900px) {
    .audit-information-grid {
        grid-template-columns:
            1fr;
    }

    .metadata-grid {
        grid-template-columns:
            1fr;
    }
}


@media (max-width: 680px) {
    .audit-detail-navigation {
        align-items:
            stretch;

        flex-direction:
            column;
    }

    .audit-detail-navigation
    .back-button,
    .audit-detail-navigation
    .audit-refresh-button {
        width: 100%;
    }

    .audit-detail-error {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .audit-detail-error
    .retry-button {
        width: 100%;
    }

    .audit-record-hero {
        padding: 17px;
    }

    .audit-record-main {
        align-items:
            flex-start;
    }

    .audit-record-icon {
        width: 54px;
        height: 54px;

        flex-basis: 54px;

        border-radius: 15px;
    }

    .audit-record-icon svg {
        width: 22px;
        height: 22px;
    }

    .audit-record-copy h2 {
        font-size: 19px;

        line-height: 25px;
    }

    .audit-record-meta {
        align-items:
            flex-start;

        flex-direction:
            column;

        gap: 6px;
    }

    .audit-meta-divider {
        display: none;
    }

    .audit-event-code {
        max-width: 100%;
    }

    .audit-readonly-banner {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .audit-readonly-copy {
        flex:
            1
            1
            calc(
                100% - 50px
            );
    }

    .audit-readonly-badge {
        margin-left: 49px;
    }

    .audit-summary-grid {
        grid-template-columns:
            1fr;
    }

    .information-card-header {
        align-items:
            flex-start;
    }

    .information-heading {
        align-items:
            flex-start;
    }

    .resource-link-button {
        flex: 0 0 auto;
    }

    .event-preview,
    .actor-preview,
    .resource-preview,
    .network-preview {
        align-items:
            flex-start;

        flex-wrap: wrap;
    }

    .information-row {
        grid-template-columns:
            1fr;

        gap: 4px;

        align-items:
            flex-start;
    }

    .information-row dd {
        text-align: left;
    }

    .metadata-card-header {
        align-items:
            flex-start;
    }

    .metadata-count {
        flex: 0 0 auto;
    }
}
</style>