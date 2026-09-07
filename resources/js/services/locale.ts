import { router } from '@inertiajs/react';

export const localeService = {
    update(locale: string) {
        router.put('/locale', { locale }, { preserveScroll: true, preserveState: false });
    },
};
