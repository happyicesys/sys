import './bootstrap';
import '../css/app.css';
import 'v-calendar/dist/style.css';

import { createApp, h } from 'vue';
import { createInertiaApp, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
// import DisableAutocomplete from 'vue-disable-autocomplete';

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, Ziggy)
            // .use(DisableAutocomplete)
            .component(Link, Link)
            .mount(el);
    },
    progress: {
        color: 'red',
        showSpinner: true,
    }
});

// InertiaProgress.init({ color: 'red', showSpinner: true });
