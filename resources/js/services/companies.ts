import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/companies';

export type CompanyListQuery = ListQuery & {
    kind?: string;
};

export const companiesService = {
    index(filters: CompanyListQuery = {}, options: SearchOptions = {}) {
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

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    switchTo(companyId: number) {
        router.post('/me/company/switch', { company_id: companyId }, { preserveScroll: true });
    },

    assignUser(companyId: number, userId: number) {
        router.post(`${base}/${companyId}/users`, { user_id: userId }, { preserveScroll: true });
    },

    unlinkUser(companyId: number, userId: number) {
        router.delete(`${base}/${companyId}/users/${userId}`, { preserveScroll: true });
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
    dataPath: `${base}/data`,
};
