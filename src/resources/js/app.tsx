import '../css/app.css';
import './bootstrap';

import { applyLocale } from '@/i18n';
import type { AppLocale } from '@/i18n';
import type { PageProps } from '@/types';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'NKKakeist';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        const locale = (props.initialPage.props as PageProps).locale;

        void applyLocale(locale).then(() => {
            root.render(<App {...props} />);
        });

        router.on('navigate', (event) => {
            const nextLocale = event.detail.page.props.locale as AppLocale | undefined;

            if (nextLocale) {
                void applyLocale(nextLocale);
            }
        });
    },
    progress: {
        color: '#4B5563',
    },
});
