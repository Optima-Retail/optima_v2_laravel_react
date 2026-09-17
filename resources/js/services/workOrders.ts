import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/work-orders';

export const workOrdersService = {
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

    destroyAttachment(workOrderId: number, attachmentId: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${workOrderId}/attachments/${attachmentId}`, {
            preserveScroll: true,
            ...options,
        });
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    pdfPath: (id: number) => `${base}/${id}/pdf`,
    indexPath: base,
    dataPath: `${base}/data`,
    totalsPath: `${base}/totals`,
    bulkStatusPath: `${base}/bulk-status`,

    async bulkStatus(payload: {
        ids: number[];
        status_id: number;
        status_justification?: string | null;
    }): Promise<{ updated: number; failed: Array<{ id: number; message: string }> }> {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
        const csrf = match?.[1] ? decodeURIComponent(match[1]) : '';

        const response = await fetch(workOrdersService.bulkStatusPath, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error('Bulk status update failed');
        }

        return response.json();
    },
};
