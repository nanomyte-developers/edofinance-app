// Assuming this is your main entry file (e.g., resources/js/app.js)

import '../css/app.css'; // Tailwind v4
import '../css/assets/styles.scss';
import '../css/sakai-theme.css'; // Sakai styles

// PrimeVue 4 CSS - New import method
import 'primeflex/primeflex.css';
import 'primeicons/primeicons.css';

// Your Sakai custom styles
import '../css/sakai-custom.css';

import { createInertiaApp, Link } from '@inertiajs/vue3'; // <-- Import Link here
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PrimeVue from 'primevue/config';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { Ziggy } from './ziggy';

import Aura from '@primeuix/themes/aura';
import ConfirmationService from 'primevue/confirmationservice';
import SelectButton from 'primevue/selectbutton';
import StyleClass from 'primevue/styleclass';
import ToastService from 'primevue/toastservice';
import PrimeVueComponents from './plugins/primevue';

// ✅ Enhanced route function with better error handling
function route(
    name: string,
    params: any = {},
    absolute: boolean = true,
): string {
    try {
        const routeConfig = Ziggy.routes[name];

        if (!routeConfig) {
            console.warn(`Route [${name}] not defined.`);
            return '#';
        }

        let uri = routeConfig.uri; // Replace route parameters

        if (params && typeof params === 'object') {
            Object.keys(params).forEach((key) => {
                const paramValue = params[key]?.toString() || '';
                uri = uri.replace(
                    new RegExp(`\\{${key}\\??\\}`, 'g'),
                    paramValue,
                );
            });
        } // Clean up any remaining optional parameters

        uri = uri.replace(/\/\{[^}]+\?\}/g, '');
        uri = uri.replace(/\{[^}]+\?\}/g, ''); // Clean up any remaining required parameters

        uri = uri.replace(/\/\{[^}]+\}/g, '');
        uri = uri.replace(/\{[^}]+\}/g, '');

        const baseUrl = Ziggy.url.endsWith('/')
            ? Ziggy.url.slice(0, -1)
            : Ziggy.url;
        const cleanUri = uri.startsWith('/') ? uri : `/${uri}`;

        return absolute ? `${baseUrl}${cleanUri}` : cleanUri;
    } catch (error) {
        console.error('Error generating route:', error);
        return '#';
    }
}

// ✅ Make it globally available
declare global {
    interface Window {
        route: typeof route;
    }
}
window.route = route;

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName), // ✅ Fixed page resolution with better error handling

    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue');
        const pagePath = `./pages/${name}.vue`;

        if (!pages[pagePath]) {
            console.error(`Page not found: ${name}`); // Return a fallback component
            return Promise.resolve({
                default: {
                    template: '<div>Page not found</div>',
                },
            });
        }

        return resolvePageComponent(pagePath, pages);
    },

    setup({ el, App, props, plugin }) {
        const vueApp = createApp({
            render: () => h(App, props),
        });

        // --- FIX: Register Link IMMEDIATELY ---
        vueApp.component('Link', Link);
        // --- End FIX ---

        vueApp.use(plugin);
        vueApp.use(PrimeVue, {
            theme: {
                preset: Aura,
                options: {
                    darkModeSelector: '.app-dark',
                },
            },
        });
        vueApp.use(ConfirmationService);
        vueApp.use(ToastService);
        vueApp.use(PrimeVueComponents);
        vueApp.component('SelectButton', SelectButton);
        vueApp.directive('styleclass', StyleClass); // ✅ Provide route function to all components

        vueApp.config.globalProperties.route = route;
        vueApp.provide('route', route);

        vueApp.mount(el);
    },

    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
