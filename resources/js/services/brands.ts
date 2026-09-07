import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/brands';

export const brandsService = {
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

    destroy(id: number) {
        router.delete(`${base}/${id}`);
    },

    storeMessage(id: number, payload: { body?: string; file?: File | null }) {
        const data = new FormData();

        if (payload.file) {
            data.append('file', payload.file);
        } else if (payload.body) {
            data.append('body', payload.body);
        }

        router.post(`${base}/${id}/messages`, data, {
            forceFormData: true,
            preserveScroll: true,
        });
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
    dataPath: `${base}/data`,
};
