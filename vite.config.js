import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => ({
    define: {
        // enable hydration mismatch details in production build
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: 'true'
    },
    // Strip console.* and debugger calls from both dev and production builds
    // so the browser console stays clean. To temporarily re-enable them while
    // debugging, comment out this `esbuild` block.
    esbuild: { drop: ['console', 'debugger'] },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            }
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                // The libraries every page loads go in their own chunk. It holds no app code, so its hashed name
                // stays the same across deploys and browsers keep it cached; before, each deploy re-sent ~170 KB
                // gzipped of unchanged library code inside app.js. Only libraries the entry already imports are
                // listed: anything else stays lazy with the page that needs it.
                manualChunks(id) {
                    // Stylesheets stay where they were (app.css, in import order), so no page looks any different.
                    if (/\.css($|\?)/.test(id)) {
                        return undefined;
                    }
                    if (/[\\/]node_modules[\\/](@vue|vue|@inertiajs|axios|lodash|moment|floating-vue|vue-toastification|@floating-ui|qs|deepmerge|nprogress|es-toolkit|side-channel|object-inspect|call-bind|get-intrinsic|has-symbols|function-bind|hasown|es-errors|gopd|define-data-property|set-function-length|es-define-property|has-property-descriptors|has-proto|call-bound|dunder-proto|get-proto|es-object-atoms|math-intrinsics|call-bind-apply-helpers|side-channel-list|side-channel-map|side-channel-weakmap|vue-resize)[\\/]/.test(id)) {
                        return 'vendor';
                    }
                },
            },
        },
    },
    server: {
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: false
        }
    },
}));
