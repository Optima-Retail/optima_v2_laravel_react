import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/establishments';

export const establishmentsService = {
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

    storeAttachment(id: number, file: File, isPrivate = false, options: Record<string, unknown> = {}) {
        const data = new FormData();
        data.append('file', file);
        data.append('is_private', isPrivate ? '1' : '0');

        router.post(`${base}/${id}/attachments`, data, {
            forceFormData: true,
            preserveScroll: true,
            ...options,
        });
    },

    destroyAttachment(establishmentId: number, attachmentId: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${establishmentId}/attachments/${attachmentId}`, {
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
