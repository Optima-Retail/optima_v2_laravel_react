import type { InertiaFormPoster } from '@/services/shared';

const base = '/profile';

export const profileService = {
    updatePassword(form: InertiaFormPoster, options: Record<string, unknown> = {}) {
        form.put(`${base}/password`, options);
    },

    showPath: base,
};
