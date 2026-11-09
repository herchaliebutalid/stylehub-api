import { createApp } from 'vue';

// Shop management styling only; scoped to .shops-page selectors.
import '../css/admin-shops-modern.css';

// User management styling only; scoped to .users-page selectors.
import '../css/admin-users-modern.css';

// Appointment monitoring styling only; scoped to .appointments-page.
import '../css/admin-appointments-modern.css';

// Feedback monitoring styling only; scoped to .feedback-page.
import '../css/admin-feedback-modern.css';

// Reports styling only; scoped to .reports-page.
import '../css/admin-reports-modern.css';

// SMS monitoring styling only; scoped to .sms-page.
import '../css/admin-sms-modern.css';

// Audit log styling only; scoped to .audit-page.
import '../css/admin-audit-modern.css';

// System settings styling only; scoped to .settings-page.
import '../css/admin-settings-modern.css';

// Account settings styling only; scoped to .account-settings-page.
import '../css/admin-account-modern.css';

import App from './admin/App.vue';
import { pinia } from './admin/pinia';
import router from './admin/router';

const appElement = document.getElementById('app');

if (appElement) {
    const app = createApp(App);

    app.use(pinia);
    app.use(router);

    app.mount('#app');
}