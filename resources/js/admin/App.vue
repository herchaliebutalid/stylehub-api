
<script setup>
import {
    onMounted,
    onUnmounted,
    watch,
} from 'vue';

import { useRoute } from 'vue-router';

const route = useRoute();

// Automatically refresh appointments every 20 seconds.
const REFRESH_INTERVAL_MS = 20000;

let refreshTimer = null;
let hasUnappliedFilters = false;

function isAppointmentListOpen() {
    return route.name === 'admin.appointments';
}

function refreshAppointmentList() {
    // Only refresh the appointments page while visible.
    // Avoid changing filters while the admin is editing.
    if (
        !isAppointmentListOpen()
        || document.visibilityState !== 'visible'
        || hasUnappliedFilters
    ) {
        return;
    }

    const refreshButton = document.querySelector(
        '.appointments-page .appointments-refresh-button'
    );

    // Reuse the existing refresh action.
    // The button is disabled when a request is loading.
    if (
        refreshButton instanceof HTMLButtonElement
        && !refreshButton.disabled
    ) {
        refreshButton.click();
    }
}

function markFiltersAsEdited(event) {
    if (
        isAppointmentListOpen()
        && event.target instanceof Element
        && event.target.closest(
            'form.appointments-filters'
        )
    ) {
        hasUnappliedFilters = true;
    }
}

function handleFilterSubmit(event) {
    if (
        !isAppointmentListOpen()
        || !(event.target instanceof Element)
        || !event.target.matches(
            'form.appointments-filters'
        )
    ) {
        return;
    }

    const from = event.target.querySelector(
        '#date-from'
    )?.value;

    const to = event.target.querySelector(
        '#date-to'
    )?.value;

    // Do not resume automatic refresh if the date
    // range is invalid.
    if (!from || !to || to >= from) {
        hasUnappliedFilters = false;
    }
}

function handleFilterReset(event) {
    if (
        isAppointmentListOpen()
        && event.target instanceof Element
        && event.target.closest(
            'form.appointments-filters button.secondary-button[type="button"]'
        )
    ) {
        hasUnappliedFilters = false;
    }
}

function handleVisibilityChange() {
    // Refresh when the administrator returns to this tab.
    if (document.visibilityState === 'visible') {
        refreshAppointmentList();
    }
}

// Reset filter-tracking when navigating between pages.
watch(
    () => route.name,
    () => {
        hasUnappliedFilters = false;
    }
);

onMounted(() => {
    document.addEventListener(
        'input',
        markFiltersAsEdited,
        true
    );

    document.addEventListener(
        'change',
        markFiltersAsEdited,
        true
    );

    document.addEventListener(
        'submit',
        handleFilterSubmit,
        true
    );

    document.addEventListener(
        'click',
        handleFilterReset,
        true
    );

    document.addEventListener(
        'visibilitychange',
        handleVisibilityChange
    );

    refreshTimer = window.setInterval(
        refreshAppointmentList,
        REFRESH_INTERVAL_MS
    );
});

onUnmounted(() => {
    if (refreshTimer !== null) {
        window.clearInterval(refreshTimer);
        refreshTimer = null;
    }

    document.removeEventListener(
        'input',
        markFiltersAsEdited,
        true
    );

    document.removeEventListener(
        'change',
        markFiltersAsEdited,
        true
    );

    document.removeEventListener(
        'submit',
        handleFilterSubmit,
        true
    );

    document.removeEventListener(
        'click',
        handleFilterReset,
        true
    );

    document.removeEventListener(
        'visibilitychange',
        handleVisibilityChange
    );
});
</script>

<template>
    <RouterView />
</template>
