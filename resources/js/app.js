import './bootstrap';
import '../css/app.css';
import 'floating-vue/dist/style.css'
import 'v-calendar/dist/style.css';
import 'vue-toastification/dist/index.css';

import { createApp, h } from 'vue';
import { createInertiaApp, Link } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import Toast, { POSITION } from 'vue-toastification';  // Import vue-toastification
// import DisableAutocomplete from 'vue-disable-autocomplete';

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Laravel';

import FloatingVue from 'floating-vue'

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue, Ziggy)
            .use(FloatingVue)
            .use(Toast, {  // Set up vue-toastification with options
                position: POSITION.TOP_RIGHT,
                timeout: 5000,
                closeOnClick: true,
                pauseOnHover: true,
                draggable: true,
                draggablePercent: 0.6,
                showCloseButtonOnHover: false,
                hideProgressBar: false,
                closeButton: 'button',
                icon: true,
                rtl: false,
            })
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
