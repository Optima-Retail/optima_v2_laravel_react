import { router } from '@inertiajs/react';
import type { InertiaFormPoster } from '@/services/shared';

export const authService = {
    login(form: InertiaFormPoster) {
        form.post('/login');
    },

    logout() {
        router.post('/logout');
    },
};
