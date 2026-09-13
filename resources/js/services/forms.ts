import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/forms';

export const formsService = {
    index(filters: ListQuery = {}, options: SearchOptions = {}) {
        router.get(
            base,
            cleanQuery({
                search: filters.search,
                sort: filters.sort,
                direction: filters.direction,
                per_page: filters.per_page,
            }),
            { preserveState: true, replace: true, ...options },
        );
    },

    store(form: InertiaFormPoster) {
        form.post(base);
    },

    update(id: number, form: InertiaFormPoster) {
        form.put(`${base}/${id}`);
    },

    advance(id: number, options: Record<string, unknown> = {}) {
        router.post(`${base}/${id}/advance`, {}, { preserveScroll: true, ...options });
    },

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
    dataPath: `${base}/data`,
    publicPath: (publicId: string) => `${base}/public/${publicId}`,
    publicUrl: (publicId: string) => {
        if (typeof window === 'undefined') {
            return `${base}/public/${publicId}`;
        }

        return `${window.location.origin}${base}/public/${publicId}`;
    },
};
