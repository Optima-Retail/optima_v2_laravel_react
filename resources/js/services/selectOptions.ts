export type SelectOptionRow = {
    id: number;
    label: string;
    color?: string | null;
    logo_url?: string | null;
    company_id?: number;
    company_name?: string | null;
    company_logo_url?: string | null;
    brand_name?: string | null;
    currency_id?: number | null;
    currency_label?: string | null;
    code?: string;
    description?: string | null;
    unit_price?: string;
    [key: string]: unknown;
};

export type SelectOptionsResource =
    | 'brands'
    | 'users'
    | 'establishments'
    | 'technicians'
    | 'contracts'
    | 'requesters'
    | 'articles'
    | 'work-orders'
    | 'evaluations';

export type SelectOptionsQuery = {
    search?: string;
    limit?: number;
    includeId?: number | null;
    includeIds?: number[];
    companyId?: number | null;
    establishmentId?: number | null;
    clientPriorityId?: number | null;
    workOrderTypeId?: number | null;
    exceptId?: number | null;
    scope?: string;
    stage?: string;
    rich?: boolean;
};

function appendParams(params: URLSearchParams, query: SelectOptionsQuery): void {
    if (query.search?.trim()) {
        params.set('search', query.search.trim());
    }
    if (query.limit) {
        params.set('limit', String(query.limit));
    }
    if (query.includeId) {
        params.set('include_id', String(query.includeId));
    }
    if (query.includeIds?.length) {
        for (const id of query.includeIds) {
            params.append('include_ids[]', String(id));
        }
    }
    if (query.companyId) {
        params.set('company_id', String(query.companyId));
    }
    if (query.establishmentId) {
        params.set('establishment_id', String(query.establishmentId));
    }
    if (query.clientPriorityId) {
        params.set('client_priority_id', String(query.clientPriorityId));
    }
    if (query.workOrderTypeId) {
        params.set('work_order_type_id', String(query.workOrderTypeId));
    }
    if (query.exceptId) {
        params.set('except_id', String(query.exceptId));
    }
    if (query.scope) {
        params.set('scope', query.scope);
    }
    if (query.stage) {
        params.set('stage', query.stage);
    }
    if (query.rich === false) {
        params.set('rich', '0');
    }
}

export const selectOptionsService = {
    async fetch(resource: SelectOptionsResource, query: SelectOptionsQuery = {}): Promise<SelectOptionRow[]> {
        const params = new URLSearchParams();
        appendParams(params, query);

        const response = await fetch(`/select-options/${resource}?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Select options failed (${resource}: ${response.status})`);
        }

        const payload = (await response.json()) as { data?: SelectOptionRow[] };

        return payload.data ?? [];
    },
};
