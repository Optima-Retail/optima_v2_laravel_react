import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/technician-requests';

export const technicianRequestsService = {
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

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    createScreening(id: number, options: Record<string, unknown> = {}) {
        router.post(`${base}/${id}/screenings`, {}, options);
    },

    cancel(id: number, options: Record<string, unknown> = {}) {
        router.post(`${base}/${id}/cancel`, {}, options);
    },

    attachTechnician(id: number, companyRelationshipId: number, options: Record<string, unknown> = {}) {
        router.post(
            `${base}/${id}/technicians`,
            { company_relationship_id: companyRelationshipId },
            { preserveScroll: true, ...options },
        );
    },

    detachTechnician(id: number, companyRelationshipId: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}/technicians/${companyRelationshipId}`, {
            preserveScroll: true,
            ...options,
        });
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
    dataPath: `${base}/data`,
};
