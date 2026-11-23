import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import router from './router';
import { useThemeStore } from './stores/theme';

const app = createApp(App);

app.use(createPinia());

// Apply the saved theme before the first render.
useThemeStore().init();

app.use(router);

app.mount('#app');