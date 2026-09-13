import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/technicians';

export type TechnicianListQuery = ListQuery & {
    kind?: string;
};

export const techniciansService = {
    index(filters: TechnicianListQuery = {}, options: SearchOptions = {}) {
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

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number, params?: { tab?: string; incident?: number }) => {
        const query = cleanQuery({
            tab: params?.tab,
            incident: params?.incident,
        });
        const qs = new URLSearchParams(
            Object.entries(query).map(([key, value]) => [key, String(value)]),
        ).toString();

        return qs ? `${base}/${id}/edit?${qs}` : `${base}/${id}/edit`;
    },
    indexPath: base,
    dataPath: `${base}/data`,
    incidentsPath: '/technician-incidents',
};
