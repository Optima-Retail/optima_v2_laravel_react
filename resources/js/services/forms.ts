import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/forms';

/** Laravel sets an encrypted XSRF-TOKEN cookie; send it as X-XSRF-TOKEN (decoded). */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

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

    async templateSuggestions(params: {
        workOrderId: number;
        formTypeId?: number | null;
    }): Promise<Array<{ id: number; label: string; is_default: boolean }>> {
        const query = new URLSearchParams({ work_order_id: String(params.workOrderId) });

        if (params.formTypeId) {
            query.set('form_type_id', String(params.formTypeId));
        }

        const response = await fetch(`${base}/template-suggestions?${query.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        const payload = (await response.json()) as {
            data: Array<{ id: number; label: string; is_default: boolean }>;
        };

        return payload.data ?? [];
    },

    async uploadFieldFile(
        formId: number,
        fieldId: number,
        target: 'value' | 'before' | 'after',
        file: File,
    ): Promise<{ value: string | null; payload: Record<string, unknown> | null; url: string }> {
        const data = new FormData();
        data.append('file', file);
        data.append('target', target);

        const response = await fetch(`${base}/${formId}/fields/${fieldId}/file`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: data,
        });

        if (!response.ok) {
            const body = (await response.json().catch(() => null)) as { message?: string } | null;

            throw new Error(body?.message ?? `Upload failed (${response.status})`);
        }

        return (await response.json()) as {
            value: string | null;
            payload: Record<string, unknown> | null;
            url: string;
        };
    },

    fieldFileUrl(formId: number, fieldId: number, target: 'value' | 'before' | 'after' = 'value') {
        return `${base}/${formId}/fields/${fieldId}/file?target=${target}`;
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    pdfPath: (id: number) => `${base}/${id}/pdf`,
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
