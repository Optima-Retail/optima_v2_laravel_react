import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/relationships';

export type RelationshipListQuery = ListQuery & {
    kind?: string;
};

export const relationshipsService = {
    index(filters: RelationshipListQuery = {}, options: SearchOptions = {}) {
        router.get(
            base,
            cleanQuery({
                search: filters.search,
                kind: filters.kind,
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

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
};
