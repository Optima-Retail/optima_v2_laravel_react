import '../css/app.css';
import '@/i18n';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import i18n from '@/i18n';
import type { SharedPageProps } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel Optima';

const pages = import.meta.glob('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: async (name) => {
        const importer = pages[`./pages/${name}.tsx`];

        if (!importer) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        const module = (await importer()) as { default: ComponentType };
        return module.default;
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        const locale = (props.initialPage.props as SharedPageProps).locale ?? 'en';
        void i18n.changeLanguage(locale);
        document.documentElement.lang = locale;

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#2563eb',
        showSpinner: true,
    },
});
