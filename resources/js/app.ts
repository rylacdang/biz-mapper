import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome' || name === 'businesses/Show':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Retire only the previous raster-map worker; unrelated workers are untouched.
if ('serviceWorker' in navigator) {
    void navigator.serviceWorker
        .getRegistrations()
        .then(async (registrations) => {
            for (const registration of registrations) {
                const worker =
                    registration.active ??
                    registration.waiting ??
                    registration.installing;

                if (
                    worker &&
                    new URL(worker.scriptURL).pathname === '/tile-cache-sw.js'
                ) {
                    await registration.unregister();
                    await caches.delete('map-tiles-v1');
                }
            }
        })
        .catch(() => {});
}
