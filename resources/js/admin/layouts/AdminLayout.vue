<script setup>
import {
    computed,
    ref,
} from 'vue';

import {
    useRoute,
} from 'vue-router';

import AdminHeader from '../components/AdminHeader.vue';
import AdminSidebar from '../components/AdminSidebar.vue';

const route = useRoute();

const sidebarOpen =
    ref(false);

const pageTitle =
    computed(
        () =>
            route.meta.title
            ?? 'Administrator',
    );

function toggleSidebar() {
    sidebarOpen.value =
        !sidebarOpen.value;
}

function closeSidebar() {
    sidebarOpen.value =
        false;
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
                    <RouterView />
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

    color:
        var(--sh-text);
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

    margin-left:
        282px;

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
|
| Keeps wide admin screens organized instead of stretching every card
| from one side of a large monitor to the other.
|
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