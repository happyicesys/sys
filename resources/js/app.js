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
import { i18nPlugin, loadLocale, stripMarks } from './i18n';

createInertiaApp({
    title: (title) => `${stripMarks(title)} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    async setup({ el, App, props, plugin }) {
        // The user's language is loaded before the first paint, so a Chinese page never flashes English.
        // Wording edit mode is offered only to those who may change wording (Settings → Translations).
        const permissions = props.initialPage.props.auth?.permissions ?? [];
        await loadLocale(props.initialPage.props.i18n, permissions.includes('update translations'));

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18nPlugin)
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
