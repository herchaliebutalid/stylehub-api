<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const styleHubLogo = '/images/stylehub-logo.png';

const email = ref('');
const password = ref('');
const showPassword = ref(false);

const errorMessage = ref('');
const fieldErrors = ref({});

async function submitLogin() {
    if (auth.isLoggingIn) {
        return;
    }

    errorMessage.value = '';
    fieldErrors.value = {};

    try {
        await auth.login({
            email: email.value.trim().toLowerCase(),
            password: password.value,
        });

        await router.replace({
            name: 'admin.dashboard',
        });
    } catch (error) {
        if (
            error?.status === 422
            && error?.data?.errors
        ) {
            fieldErrors.value = error.data.errors;
        }

        errorMessage.value =
            error?.data?.message
            ?? error?.message
            ?? 'Unable to sign in. Please try again.';
    }
}
</script>


<template>
    <main class="login-page">
        <!-- ============================================================
             BRAND / VISUAL SIDE
             ============================================================ -->

        <section class="brand-panel">
            <div class="brand-background-grid" />

            <div class="brand-glow brand-glow-one" />
            <div class="brand-glow brand-glow-two" />

            <div class="brand-shell">
                <!-- BRAND TOP -->

                <div class="brand-top">
                    <div class="brand-identity">
                        <div class="brand-logo-frame">
                            <img
                                :src="styleHubLogo"
                                alt="StyleHub logo"
                                class="brand-logo"
                            >
                        </div>

                        <div class="brand-name-block">
                            <strong>
                                StyleHub
                            </strong>

                            <span>
                                Administrator Portal
                            </span>
                        </div>
                    </div>


                    <div class="portal-status">
                        <span class="portal-status-dot" />

                        <span>
                            Secure access
                        </span>
                    </div>
                </div>


                <!-- BRAND CONTENT -->

                <div class="brand-content">
                    <div class="brand-eyebrow">
                        <span />

                        PLATFORM ADMINISTRATION
                    </div>

                    <h1>
                        Manage StyleHub
                        <span>with confidence.</span>
                    </h1>

                    <p class="brand-description">
                        A centralized Administrator workspace for
                        managing platform operations, accounts,
                        shops, appointments, messaging and
                        security activity.
                    </p>


                    <div class="brand-features">
                        <article class="brand-feature">
                            <div class="brand-feature-icon">
                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 10v10h16V10"
                                    />

                                    <path
                                        d="M3 10l2-6h14l2 6"
                                    />

                                    <path
                                        d="M8 20v-6h8v6"
                                    />
                                </svg>
                            </div>

                            <div>
                                <strong>
                                    Platform operations
                                </strong>

                                <span>
                                    Shops, users and appointment
                                    administration in one workspace.
                                </span>
                            </div>
                        </article>


                        <article class="brand-feature">
                            <div class="brand-feature-icon">
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

                            <div>
                                <strong>
                                    Messaging visibility
                                </strong>

                                <span>
                                    Monitor StyleHub SMS operations
                                    and delivery activity.
                                </span>
                            </div>
                        </article>


                        <article class="brand-feature">
                            <div class="brand-feature-icon">
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

                            <div>
                                <strong>
                                    Security accountability
                                </strong>

                                <span>
                                    Review sensitive operational
                                    activity through Administrator
                                    audit history.
                                </span>
                            </div>
                        </article>
                    </div>
                </div>


                <!-- BRAND FOOTER -->

                <div class="brand-footer">
                    <div class="brand-footer-line" />

                    <div class="brand-footer-content">
                        <div>
                            <span>
                                STYLEHUB
                            </span>

                            <strong>
                                Barber Shop &amp; Beauty Parlor Management
                            </strong>
                        </div>

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
                </div>
            </div>
        </section>


        <!-- ============================================================
             LOGIN SIDE
             ============================================================ -->

        <section class="form-panel">
            <div class="mobile-brand">
                <div class="mobile-logo-frame">
                    <img
                        :src="styleHubLogo"
                        alt="StyleHub logo"
                        class="mobile-logo"
                    >
                </div>

                <div>
                    <strong>
                        StyleHub
                    </strong>

                    <span>
                        Administrator Portal
                    </span>
                </div>
            </div>


            <div class="login-shell">
                <!-- LOGIN HEADER -->

                <header class="login-heading">
                    <div class="login-heading-eyebrow">
                        ADMINISTRATOR ACCESS
                    </div>

                    <h2>
                        Welcome back
                    </h2>

                    <p>
                        Sign in with your authorized StyleHub
                        Administrator account to continue.
                    </p>
                </header>


                <!-- ERROR -->

                <div
                    v-if="errorMessage"
                    class="login-alert"
                    role="alert"
                >
                    <div class="login-alert-icon">
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

                    <div class="login-alert-copy">
                        <strong>
                            Sign in failed
                        </strong>

                        <p>
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>


                <!-- LOGIN FORM -->

                <form
                    class="login-form"
                    @submit.prevent="submitLogin"
                >
                    <!-- EMAIL -->

                    <div class="field-group">
                        <div class="field-heading">
                            <label for="email">
                                Email address
                            </label>
                        </div>


                        <div
                            class="login-input"
                            :class="{
                                invalid:
                                    fieldErrors.email?.[0],
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
                                id="email"
                                v-model="email"
                                type="email"
                                name="email"
                                autocomplete="username"
                                inputmode="email"
                                maxlength="255"
                                placeholder="administrator@example.com"
                                :disabled="auth.isLoggingIn"
                                required
                            >
                        </div>


                        <p
                            v-if="fieldErrors.email?.[0]"
                            class="field-error"
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
                                {{ fieldErrors.email[0] }}
                            </span>
                        </p>
                    </div>


                    <!-- PASSWORD -->

                    <div class="field-group">
                        <div class="field-heading">
                            <label for="password">
                                Password
                            </label>
                        </div>


                        <div
                            class="login-input password-input"
                            :class="{
                                invalid:
                                    fieldErrors.password?.[0],
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
                                id="password"
                                v-model="password"
                                :type="
                                    showPassword
                                        ? 'text'
                                        : 'password'
                                "
                                name="password"
                                autocomplete="current-password"
                                maxlength="255"
                                placeholder="Enter your password"
                                :disabled="auth.isLoggingIn"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                :disabled="auth.isLoggingIn"
                                :aria-label="
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                @click="
                                    showPassword =
                                        !showPassword
                                "
                            >
                                <svg
                                    v-if="!showPassword"
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


                        <p
                            v-if="fieldErrors.password?.[0]"
                            class="field-error"
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
                                {{ fieldErrors.password[0] }}
                            </span>
                        </p>
                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        class="login-button"
                        :disabled="auth.isLoggingIn"
                    >
                        <span
                            v-if="auth.isLoggingIn"
                            class="login-spinner"
                        />

                        <span>
                            {{
                                auth.isLoggingIn
                                    ? 'Signing in...'
                                    : 'Sign in to StyleHub'
                            }}
                        </span>

                        <svg
                            v-if="!auth.isLoggingIn"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                d="M5 12h14"
                            />

                            <path
                                d="M14 7l5 5-5 5"
                            />
                        </svg>
                    </button>
                </form>


                <!-- AUTHORIZED ACCESS -->

                <div class="authorized-access">
                    <div class="authorized-icon">
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

                    <div>
                        <strong>
                            Authorized access only
                        </strong>

                        <p>
                            This portal is restricted to
                            authorized StyleHub Administrators.
                        </p>
                    </div>
                </div>
            </div>


            <!-- FORM FOOTER -->

            <footer class="login-footer">
                <span>
                    StyleHub Administrator Portal
                </span>

                <span class="login-footer-dot" />

                <span>
                    Secure platform access
                </span>
            </footer>
        </section>
    </main>
</template>


<style scoped>
/*
|--------------------------------------------------------------------------
| GLOBAL LOGIN RESET
|--------------------------------------------------------------------------
*/

:global(html),
:global(body),
:global(#app) {
    width: 100%;
    min-height: 100%;
    margin: 0;
    padding: 0;
}

* {
    box-sizing: border-box;
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.login-page {
    width: 100%;
    min-height: 100vh;
    min-height: 100dvh;

    display: grid;

    grid-template-columns:
        minmax(
            500px,
            1.05fr
        )
        minmax(
            520px,
            0.95fr
        );

    overflow: hidden;

    background:
        #f5f7fa;

    color:
        var(
            --sh-text,
            #1f2937
        );
}


/*
|--------------------------------------------------------------------------
| BRAND PANEL
|--------------------------------------------------------------------------
*/

.brand-panel {
    position: relative;

    min-width: 0;
    min-height: 100vh;
    min-height: 100dvh;

    overflow: hidden;

    background:
        radial-gradient(
            circle at 15% 15%,
            rgba(
                214,
                154,
                45,
                0.12
            ),
            transparent 28%
        ),
        radial-gradient(
            circle at 91% 80%,
            rgba(
                255,
                255,
                255,
                0.06
            ),
            transparent 32%
        ),
        linear-gradient(
            145deg,
            #111820 0%,
            #17202a 48%,
            #222d3a 100%
        );

    color:
        #ffffff;
}


/*
|--------------------------------------------------------------------------
| BRAND DECORATIONS
|--------------------------------------------------------------------------
*/

.brand-background-grid {
    position: absolute;
    inset: 0;

    opacity: 0.14;

    background-image:
        linear-gradient(
            rgba(
                255,
                255,
                255,
                0.05
            )
            1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(
                255,
                255,
                255,
                0.05
            )
            1px,
            transparent 1px
        );

    background-size:
        54px
        54px;

    mask-image:
        linear-gradient(
            to bottom,
            rgba(
                0,
                0,
                0,
                0.6
            ),
            transparent
        );
}

.brand-glow {
    position: absolute;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.07
        );

    border-radius:
        999px;

    pointer-events: none;
}

.brand-glow-one {
    width: 440px;
    height: 440px;

    top: -240px;
    right: -190px;
}

.brand-glow-two {
    width: 550px;
    height: 550px;

    bottom: -390px;
    left: -300px;
}


/*
|--------------------------------------------------------------------------
| BRAND SHELL
|--------------------------------------------------------------------------
*/

.brand-shell {
    position: relative;
    z-index: 2;

    width: 100%;
    min-height: 100vh;
    min-height: 100dvh;

    display: flex;
    flex-direction: column;

    padding:
        32px
        clamp(
            38px,
            5vw,
            76px
        )
        31px;
}


/*
|--------------------------------------------------------------------------
| BRAND TOP
|--------------------------------------------------------------------------
*/

.brand-top {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;
}

.brand-identity {
    display: flex;
    align-items: center;

    gap: 11px;
}

.brand-logo-frame {
    width: 52px;
    height: 52px;

    flex: 0 0 52px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 5px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.1
        );

    border-radius: 14px;

    background:
        rgba(
            255,
            255,
            255,
            0.06
        );

    box-shadow:
        0
        12px
        30px
        rgba(
            0,
            0,
            0,
            0.12
        );
}

.brand-logo {
    width: 100%;
    height: 100%;

    object-fit: contain;
}

.brand-name-block {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.brand-name-block strong {
    color:
        #ffffff;

    font-size: 16px;
    font-weight: 800;

    letter-spacing:
        -0.25px;
}

.brand-name-block span {
    color:
        rgba(
            255,
            255,
            255,
            0.48
        );

    font-size: 8.5px;
    font-weight: 650;

    letter-spacing: 0.35px;

    text-transform:
        uppercase;
}


/*
|--------------------------------------------------------------------------
| PORTAL STATUS
|--------------------------------------------------------------------------
*/

.portal-status {
    min-height: 31px;

    display: inline-flex;
    align-items: center;

    gap: 7px;

    padding:
        0
        10px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.1
        );

    border-radius: 999px;

    background:
        rgba(
            255,
            255,
            255,
            0.045
        );

    color:
        rgba(
            255,
            255,
            255,
            0.64
        );

    font-size: 8px;
    font-weight: 700;

    text-transform:
        uppercase;

    letter-spacing: 0.45px;
}

.portal-status-dot {
    width: 6px;
    height: 6px;

    border-radius: 999px;

    background:
        #5ec693;

    box-shadow:
        0
        0
        0
        4px
        rgba(
            94,
            198,
            147,
            0.09
        );
}


/*
|--------------------------------------------------------------------------
| BRAND CONTENT
|--------------------------------------------------------------------------
*/

.brand-content {
    width: 100%;
    max-width: 650px;

    margin:
        auto
        0;

    padding:
        48px
        0;
}

.brand-eyebrow {
    display: flex;
    align-items: center;

    gap: 8px;

    margin-bottom: 16px;

    color:
        #d69a2d;

    font-size: 9px;
    font-weight: 800;

    letter-spacing: 1.7px;

    text-transform:
        uppercase;
}

.brand-eyebrow > span {
    width: 22px;
    height: 1px;

    background:
        #d69a2d;
}

.brand-content h1 {
    max-width: 620px;

    margin: 0;

    color:
        #ffffff;

    font-size:
        clamp(
            44px,
            5vw,
            72px
        );

    font-weight: 750;

    line-height: 0.98;

    letter-spacing:
        -3px;
}

.brand-content h1 span {
    display: block;

    color:
        rgba(
            255,
            255,
            255,
            0.48
        );
}

.brand-description {
    max-width: 560px;

    margin:
        22px
        0
        0;

    color:
        rgba(
            255,
            255,
            255,
            0.58
        );

    font-size: 13px;

    line-height: 1.75;
}


/*
|--------------------------------------------------------------------------
| BRAND FEATURES
|--------------------------------------------------------------------------
*/

.brand-features {
    display: grid;

    grid-template-columns:
        repeat(
            3,
            minmax(
                0,
                1fr
            )
        );

    gap: 9px;

    margin-top: 31px;
}

.brand-feature {
    min-width: 0;

    min-height: 130px;

    padding:
        13px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            0.08
        );

    border-radius: 13px;

    background:
        rgba(
            255,
            255,
            255,
            0.035
        );

    backdrop-filter:
        blur(
            10px
        );
}

.brand-feature-icon {
    width: 33px;
    height: 33px;

    display: flex;
    align-items: center;
    justify-content: center;

    margin-bottom: 12px;

    border:
        1px solid
        rgba(
            214,
            154,
            45,
            0.16
        );

    border-radius: 9px;

    background:
        rgba(
            214,
            154,
            45,
            0.08
        );

    color:
        #d69a2d;
}

.brand-feature-icon svg {
    width: 15px;
    height: 15px;

    fill: none;

    stroke:
        currentColor;

    stroke-width: 1.6;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.brand-feature strong {
    display: block;

    color:
        rgba(
            255,
            255,
            255,
            0.86
        );

    font-size: 9px;
    font-weight: 750;

    line-height: 14px;
}

.brand-feature span {
    display: block;

    margin-top: 5px;

    color:
        rgba(
            255,
            255,
            255,
            0.42
        );

    font-size: 7.7px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| BRAND FOOTER
|--------------------------------------------------------------------------
*/

.brand-footer-line {
    width: 100%;
    height: 1px;

    background:
        linear-gradient(
            to right,
            rgba(
                255,
                255,
                255,
                0.11
            ),
            transparent
        );
}

.brand-footer-content {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 18px;

    padding-top: 15px;
}

.brand-footer-content > div {
    display: flex;
    flex-direction: column;

    gap: 2px;
}

.brand-footer-content span {
    color:
        #d69a2d;

    font-size: 7px;
    font-weight: 800;

    letter-spacing: 1.2px;
}

.brand-footer-content strong {
    color:
        rgba(
            255,
            255,
            255,
            0.4
        );

    font-size: 7.5px;
    font-weight: 550;
}

.brand-footer-content > svg {
    width: 18px;
    height: 18px;

    fill: none;

    stroke:
        rgba(
            255,
            255,
            255,
            0.22
        );

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}


/*
|--------------------------------------------------------------------------
| FORM PANEL
|--------------------------------------------------------------------------
*/

.form-panel {
    position: relative;

    min-width: 0;
    min-height: 100vh;
    min-height: 100dvh;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-direction: column;

    padding:
        50px
        clamp(
            32px,
            5vw,
            80px
        )
        34px;

    background:
        radial-gradient(
            circle at 100% 0,
            rgba(
                214,
                154,
                45,
                0.05
            ),
            transparent 27%
        ),
        #f5f7fa;
}


/*
|--------------------------------------------------------------------------
| LOGIN SHELL
|--------------------------------------------------------------------------
*/

.login-shell {
    width: 100%;
    max-width: 430px;

    margin:
        auto
        0;
}


/*
|--------------------------------------------------------------------------
| LOGIN HEADING
|--------------------------------------------------------------------------
*/

.login-heading {
    margin-bottom: 27px;
}

.login-heading-eyebrow {
    margin-bottom: 8px;

    color:
        var(
            --sh-accent,
            #d69a2d
        );

    font-size: 8px;
    font-weight: 800;

    letter-spacing: 1.25px;

    text-transform:
        uppercase;
}

.login-heading h2 {
    margin: 0;

    color:
        var(
            --sh-text,
            #1f2937
        );

    font-size: 33px;
    font-weight: 800;

    line-height: 40px;

    letter-spacing:
        -1.15px;
}

.login-heading p {
    max-width: 390px;

    margin:
        8px
        0
        0;

    color:
        var(
            --sh-text-3,
            #7b8491
        );

    font-size: 10.5px;

    line-height: 17px;
}


/*
|--------------------------------------------------------------------------
| ALERT
|--------------------------------------------------------------------------
*/

.login-alert {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin-bottom: 18px;

    padding:
        11px
        12px;

    border:
        1px solid
        #eed3d3;

    border-radius: 10px;

    background:
        #fff0f0;
}

.login-alert-icon {
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
        #a94f4f;
}

.login-alert-icon svg {
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

.login-alert-copy {
    min-width: 0;
}

.login-alert-copy strong {
    display: block;

    color:
        #8e4444;

    font-size: 9px;
}

.login-alert-copy p {
    margin:
        2px
        0
        0;

    color:
        #a45a5a;

    font-size: 8.5px;

    line-height: 14px;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.login-form {
    display: flex;
    flex-direction: column;

    gap: 17px;
}

.field-group {
    display: flex;
    flex-direction: column;

    gap: 6px;
}

.field-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 10px;
}

.field-heading label {
    color:
        var(
            --sh-text-2,
            #4b5563
        );

    font-size: 9px;
    font-weight: 750;
}


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

.login-input {
    min-height: 48px;

    display: flex;
    align-items: center;

    gap: 9px;

    padding:
        0
        12px;

    border:
        1px solid
        var(
            --sh-border-strong,
            #d7dee7
        );

    border-radius: 10px;

    background:
        #ffffff;

    transition:
        border-color
        0.16s ease,
        box-shadow
        0.16s ease,
        background
        0.16s ease;
}

.login-input:focus-within {
    border-color:
        rgba(
            214,
            154,
            45,
            0.74
        );

    box-shadow:
        0
        0
        0
        3px
        rgba(
            214,
            154,
            45,
            0.09
        );
}

.login-input.invalid {
    border-color:
        #dda5a5;

    background:
        #fffdfd;
}

.login-input > svg {
    width: 15px;
    height: 15px;

    flex: 0 0 15px;

    fill: none;

    stroke:
        var(
            --sh-text-3,
            #7b8491
        );

    stroke-width: 1.7;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.login-input input {
    min-width: 0;
    width: 100%;

    height: 46px;

    padding: 0;

    border: 0;

    outline: none;

    background:
        transparent;

    color:
        var(
            --sh-text,
            #1f2937
        );

    font: inherit;

    font-size: 10px;
}

.login-input input::placeholder {
    color:
        #afb5bd;
}

.login-input:has(
    input:disabled
) {
    background:
        #f3f5f7;

    opacity: 0.7;
}


/*
|--------------------------------------------------------------------------
| PASSWORD
|--------------------------------------------------------------------------
*/

.password-input {
    padding-right: 6px;
}

.password-toggle {
    width: 34px;
    height: 34px;

    flex: 0 0 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    border: 0;

    border-radius: 8px;

    background:
        transparent;

    color:
        var(
            --sh-text-3,
            #7b8491
        );

    cursor: pointer;

    transition:
        color
        0.15s ease,
        background
        0.15s ease;
}

.password-toggle:hover:not(:disabled) {
    background:
        #f5f7f9;

    color:
        var(
            --sh-text-2,
            #4b5563
        );
}

.password-toggle:disabled {
    cursor: not-allowed;

    opacity: 0.45;
}

.password-toggle svg {
    width: 15px;
    height: 15px;

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
| FIELD ERROR
|--------------------------------------------------------------------------
*/

.field-error {
    display: flex;
    align-items: flex-start;

    gap: 5px;

    margin: 0;

    color:
        #a94f4f;

    font-size: 8px;

    line-height: 13px;
}

.field-error svg {
    width: 11px;
    height: 11px;

    flex: 0 0 11px;

    margin-top: 1px;

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
| LOGIN BUTTON
|--------------------------------------------------------------------------
*/

.login-button {
    width: 100%;
    min-height: 49px;

    display: flex;
    align-items: center;
    justify-content: center;

    gap: 8px;

    margin-top: 3px;

    padding:
        0
        16px;

    border:
        1px solid
        #17202a;

    border-radius: 10px;

    background:
        linear-gradient(
            145deg,
            #17202a,
            #222d3a
        );

    box-shadow:
        0
        11px
        24px
        rgba(
            23,
            32,
            42,
            0.14
        );

    color:
        #ffffff;

    font: inherit;

    font-size: 9.5px;
    font-weight: 750;

    cursor: pointer;

    transition:
        transform
        0.15s ease,
        box-shadow
        0.15s ease,
        background
        0.15s ease;
}

.login-button:hover:not(:disabled) {
    transform:
        translateY(
            -1px
        );

    background:
        linear-gradient(
            145deg,
            #202b37,
            #2b3948
        );

    box-shadow:
        0
        14px
        28px
        rgba(
            23,
            32,
            42,
            0.18
        );
}

.login-button:active:not(:disabled) {
    transform:
        translateY(
            0
        );
}

.login-button:disabled {
    cursor: not-allowed;

    opacity: 0.65;
}

.login-button > svg {
    width: 14px;
    height: 14px;

    fill: none;

    stroke:
        #d69a2d;

    stroke-width: 1.9;

    stroke-linecap:
        round;

    stroke-linejoin:
        round;
}

.login-spinner {
    width: 14px;
    height: 14px;

    border:
        2px solid
        rgba(
            255,
            255,
            255,
            0.38
        );

    border-top-color:
        #ffffff;

    border-radius: 999px;

    animation:
        login-spin
        0.7s
        linear
        infinite;
}


/*
|--------------------------------------------------------------------------
| AUTHORIZED ACCESS
|--------------------------------------------------------------------------
*/

.authorized-access {
    display: flex;
    align-items: flex-start;

    gap: 9px;

    margin-top: 21px;

    padding:
        12px;

    border:
        1px solid
        var(
            --sh-border,
            #e3e8ef
        );

    border-radius: 10px;

    background:
        rgba(
            255,
            255,
            255,
            0.62
        );
}

.authorized-icon {
    width: 30px;
    height: 30px;

    flex: 0 0 30px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background:
        #edf9f3;

    color:
        #287558;
}

.authorized-icon svg {
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

.authorized-access > div:last-child {
    min-width: 0;
}

.authorized-access strong {
    display: block;

    color:
        var(
            --sh-text-2,
            #4b5563
        );

    font-size: 8.5px;
}

.authorized-access p {
    margin:
        2px
        0
        0;

    color:
        var(
            --sh-text-3,
            #7b8491
        );

    font-size: 8px;

    line-height: 13px;
}


/*
|--------------------------------------------------------------------------
| MOBILE BRAND
|--------------------------------------------------------------------------
*/

.mobile-brand {
    display: none;

    width: 100%;
    max-width: 430px;

    align-items: center;

    gap: 10px;

    margin-bottom: 40px;
}

.mobile-logo-frame {
    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 4px;

    border:
        1px solid
        var(
            --sh-border,
            #e3e8ef
        );

    border-radius: 12px;

    background:
        #ffffff;

    box-shadow:
        0
        8px
        20px
        rgba(
            15,
            23,
            42,
            0.06
        );
}

.mobile-logo {
    width: 100%;
    height: 100%;

    object-fit: contain;
}

.mobile-brand > div:last-child {
    display: flex;
    flex-direction: column;

    gap: 1px;
}

.mobile-brand strong {
    color:
        var(
            --sh-text,
            #1f2937
        );

    font-size: 14px;
    font-weight: 800;
}

.mobile-brand span {
    color:
        var(
            --sh-text-3,
            #7b8491
        );

    font-size: 7.5px;

    text-transform:
        uppercase;

    letter-spacing: 0.55px;
}


/*
|--------------------------------------------------------------------------
| LOGIN FOOTER
|--------------------------------------------------------------------------
*/

.login-footer {
    width: 100%;
    max-width: 430px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-wrap: wrap;

    gap: 7px;

    margin-top: auto;

    padding-top: 30px;

    color:
        #9ba2ac;

    font-size: 7.5px;
}

.login-footer-dot {
    width: 3px;
    height: 3px;

    border-radius: 999px;

    background:
        #c4c8ce;
}


/*
|--------------------------------------------------------------------------
| ANIMATION
|--------------------------------------------------------------------------
*/

@keyframes login-spin {
    to {
        transform:
            rotate(
                360deg
            );
    }
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE - SMALL DESKTOP
|--------------------------------------------------------------------------
*/

@media (max-width: 1180px) {
    .login-page {
        grid-template-columns:
            minmax(
                440px,
                0.9fr
            )
            minmax(
                480px,
                1.1fr
            );
    }

    .brand-features {
        grid-template-columns:
            1fr;
    }

    .brand-feature {
        min-height: 0;

        display: flex;
        align-items: flex-start;

        gap: 10px;

        padding: 10px;
    }

    .brand-feature-icon {
        flex:
            0
            0
            31px;

        width: 31px;
        height: 31px;

        margin-bottom: 0;
    }
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE - TABLET
|--------------------------------------------------------------------------
*/

@media (max-width: 920px) {
    .login-page {
        display: block;

        min-height: 100vh;
        min-height: 100dvh;

        overflow: visible;
    }

    .brand-panel {
        display: none;
    }

    .form-panel {
        min-height: 100vh;
        min-height: 100dvh;

        justify-content: center;

        padding:
            34px
            24px
            26px;
    }

    .mobile-brand {
        display: flex;
    }

    .login-shell {
        margin:
            auto
            0;
    }
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE - MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 520px) {
    .form-panel {
        justify-content: flex-start;

        padding:
            26px
            18px
            22px;
    }

    .mobile-brand {
        margin-bottom: 51px;
    }

    .login-heading h2 {
        font-size: 29px;

        line-height: 35px;
    }

    .login-heading p {
        font-size: 10px;
    }

    .login-input {
        min-height: 47px;
    }

    .login-input input {
        height: 45px;
    }

    .login-button {
        min-height: 48px;
    }

    .authorized-access {
        margin-top: 18px;
    }

    .login-footer {
        margin-top: 38px;
    }
}
</style>