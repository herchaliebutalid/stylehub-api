import { createApp } from 'vue';

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