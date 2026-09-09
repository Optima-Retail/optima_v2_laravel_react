import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/config/numbering-patterns';

export const numberingPatternsService = {
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

    upsertByResource(resource: string, form: InertiaFormPoster) {
        form.put(`${base}/resource/${resource}`);
    },

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    configurePath: (resource: string, returnTo?: string) => {
        const query = returnTo ? `?return=${encodeURIComponent(returnTo)}` : '';

        return `${base}/resource/${resource}/configure${query}`;
    },
    indexPath: base,
    dataPath: `${base}/data`,
};
