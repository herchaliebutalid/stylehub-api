<script setup>
import {
    computed,
    nextTick,
    ref,
} from 'vue';

import { useRoute } from 'vue-router';

import AdminHeader from '../components/AdminHeader.vue';
import AdminSidebar from '../components/AdminSidebar.vue';

const route = useRoute();

const sidebarOpen = ref(false);

const pageTitle = computed(
    () => route.meta.title ?? 'Administrator',
);

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value;
}

function closeSidebar() {
    sidebarOpen.value = false;
}

/*
|--------------------------------------------------------------------------
| RESET SCROLL POSITION
|--------------------------------------------------------------------------
|
| Runs when Suspense finishes loading the new page.
|
| The newly opened page always starts at the top.
| This applies to all Administrator pages.
|
*/

async function resetScrollPosition() {
    await nextTick();

    requestAnimationFrame(() => {
        window.scrollTo({
            top: 0,
            left: 0,
            behavior: 'instant',
        });

        const scrollContainers = [
            document.querySelector('.content-area'),
            document.querySelector('.page-content'),
            document.querySelector('.page-content-inner'),
        ];

        scrollContainers.forEach((element) => {
            if (element) {
                element.scrollTop = 0;
                element.scrollLeft = 0;
            }
        });
    });
}
</script>

<template>
    <div class="admin-layout">

        <!-- ============================================================
             SIDEBAR
             ============================================================ -->

        <AdminSidebar
            :open="sidebarOpen"
            @close="closeSidebar"
        />

        <!-- ============================================================
             MOBILE SIDEBAR BACKDROP
             ============================================================ -->

        <Transition name="overlay-fade">
            <button
                v-if="sidebarOpen"
                type="button"
                class="mobile-overlay"
                aria-label="Close navigation"
                @click="closeSidebar"
            />
        </Transition>

        <!-- ============================================================
             ADMIN CONTENT
             ============================================================ -->

        <div class="content-area">
            <AdminHeader
                :title="pageTitle"
                @toggle-sidebar="toggleSidebar"
            />

            <main class="page-content">
                <div class="page-content-inner">

                    <!-- =================================================
                         ROUTER VIEW
                         =================================================

                         Suspense waits for the next page to load.

                         The previous page remains visible while
                         the next page retrieves its initial data.

                         When the new page is ready, @resolve
                         automatically resets scrolling to the top.

                         ================================================= -->

                    <RouterView
                        v-slot="{
                            Component,
                            route: currentRoute,
                        }"
                    >
                        <Suspense
                            :timeout="-1"
                            @resolve="resetScrollPosition"
                        >
                            <component
                                :is="Component"
                                :key="currentRoute.fullPath"
                            />

                            <template #fallback>
                                <div
                                    class="page-pending-space"
                                    aria-hidden="true"
                                ></div>
                            </template>
                        </Suspense>
                    </RouterView>

                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/*
|--------------------------------------------------------------------------
| ADMIN APPLICATION SHELL
|--------------------------------------------------------------------------
*/

.admin-layout {
    position: relative;

    width: 100%;
    min-height: 100vh;

    background:
        linear-gradient(
            180deg,
            #f8fafc 0,
            var(--sh-bg) 180px
        );

    color: var(--sh-text);
}

/*
|--------------------------------------------------------------------------
| MAIN CONTENT AREA
|--------------------------------------------------------------------------
*/

.content-area {
    position: relative;

    min-width: 0;
    min-height: 100vh;

    margin-left: 282px;

    transition:
        margin-left
        0.2s ease;
}

/*
|--------------------------------------------------------------------------
| PAGE CONTENT
|--------------------------------------------------------------------------
*/

.page-content {
    position: relative;

    min-height:
        calc(
            100vh - 94px
        );

    padding:
        26px
        28px
        40px;
}

/*
|--------------------------------------------------------------------------
| CONTENT CONTAINER
|--------------------------------------------------------------------------
*/

.page-content-inner {
    width: 100%;
    max-width: 1680px;

    margin:
        0
        auto;
}

/*
|--------------------------------------------------------------------------
| PAGE TRANSITION PLACEHOLDER
|--------------------------------------------------------------------------
*/

.page-pending-space {
    min-height: 1px;
}

/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR OVERLAY
|--------------------------------------------------------------------------
*/

.mobile-overlay {
    display: none;
}

/*
|--------------------------------------------------------------------------
| OVERLAY TRANSITION
|--------------------------------------------------------------------------
*/

.overlay-fade-enter-active,
.overlay-fade-leave-active {
    transition:
        opacity
        0.2s ease;
}

.overlay-fade-enter-from,
.overlay-fade-leave-to {
    opacity: 0;
}

/*
|--------------------------------------------------------------------------
| LARGE DESKTOP
|--------------------------------------------------------------------------
*/

@media (min-width: 1600px) {
    .page-content {
        padding:
            30px
            34px
            48px;
    }
}

/*
|--------------------------------------------------------------------------
| TABLET / SMALL DESKTOP
|--------------------------------------------------------------------------
*/

@media (max-width: 1200px) {
    .page-content {
        padding:
            24px
            22px
            36px;
    }
}

/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR MODE
|--------------------------------------------------------------------------
*/

@media (max-width: 1024px) {
    .content-area {
        margin-left: 0;
    }

    .mobile-overlay {
        position: fixed;

        z-index: 40;

        inset: 0;

        display: block;

        width: 100%;
        height: 100%;

        padding: 0;

        border: 0;

        background:
            rgba(
                15,
                23,
                42,
                0.48
            );

        backdrop-filter:
            blur(
                2px
            );

        -webkit-backdrop-filter:
            blur(
                2px
            );

        cursor: default;
    }

    .page-content {
        min-height:
            calc(
                100vh - 94px
            );

        padding:
            22px
            20px
            34px;
    }
}

/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 760px) {
    .page-content {
        min-height:
            calc(
                100vh - 78px
            );

        padding:
            18px
            15px
            30px;
    }
}

/*
|--------------------------------------------------------------------------
| SMALL MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 480px) {
    .page-content {
        padding:
            15px
            12px
            26px;
    }
}
</style>