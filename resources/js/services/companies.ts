import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';
import type { CompanyOption } from '@/support/types/domain/common';

const base = '/companies';

export type CompanyListQuery = ListQuery & {
    kind?: string;
};

export type CompanyOptionsScope = 'party' | 'client' | 'all';

export type CompanyOptionsQuery = {
    search?: string;
    scope?: CompanyOptionsScope;
    exceptId?: number | null;
    includeId?: number | null;
    limit?: number;
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

    async options(query: CompanyOptionsQuery = {}): Promise<CompanyOption[]> {
        const params = new URLSearchParams();
        if (query.search?.trim()) {
            params.set('search', query.search.trim());
        }
        if (query.scope) {
            params.set('scope', query.scope);
        }
        if (query.exceptId) {
            params.set('except_id', String(query.exceptId));
        }
        if (query.includeId) {
            params.set('include_id', String(query.includeId));
        }
        if (query.limit) {
            params.set('limit', String(query.limit));
        }

        const response = await fetch(`${base}/options?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Company options failed (${response.status})`);
        }

        const payload = (await response.json()) as { data?: CompanyOption[] };

        return payload.data ?? [];
    },

    store(form: InertiaFormPoster) {
        form.post(base, { forceFormData: true });
    },

    update(id: number, form: InertiaFormPoster) {
        form.put(`${base}/${id}`, { forceFormData: true });
    },

    destroy(id: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${id}`, options);
    },

    switchTo(companyId: number) {
        router.post(
            '/me/company/switch',
            { company_id: companyId },
            {
                preserveScroll: true,
                preserveState: false,
            },
        );
    },

    assignUser(companyId: number, userId: number) {
        router.post(`${base}/${companyId}/users`, { user_id: userId }, { preserveScroll: true });
    },

    unlinkUser(companyId: number, userId: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${companyId}/users/${userId}`, { preserveScroll: true, ...options });
    },

    leave(companyId: number, options: Record<string, unknown> = {}) {
        router.delete(`${base}/${companyId}/membership`, {
            preserveScroll: true,
            preserveState: false,
            ...options,
        });
    },

    visitPage,

    createPath: `${base}/create`,
    editPath: (id: number) => `${base}/${id}/edit`,
    indexPath: base,
    dataPath: `${base}/data`,
    optionsPath: `${base}/options`,
};
