import { router } from '@inertiajs/react';
import { cleanQuery, type InertiaFormPoster, type ListQuery, type SearchOptions, visitPage } from '@/services/shared';

const base = '/technician-incidents';

export type TechnicianIncidentListItem = {
    id: number;
    status_id: number | null;
    status_name: string | null;
    status_color: string | null;
    technician_incident_type_id: number;
    type_name: string | null;
    incident_text: string | null;
    technician_id: number | null;
    technician_name: string | null;
    requested_by_id: number | null;
    requested_by_name: string | null;
    responded_by_id: number | null;
    responded_by_name: string | null;
    is_verified: boolean;
    due_at: string | null;
    responded_at: string | null;
    created_at: string | null;
};

export type TechnicianIncidentMessage = {
    id: number;
    technician_incident_id: number;
    user_id: number;
    user_name: string | null;
    body: string;
    type: string;
    preview_url: string | null;
    download_name: string | null;
    is_mine: boolean;
    time: string;
    date: string;
    date_label: string;
    created_at: string | null;
};

export type TechnicianIncidentStatusOption = {
    id: number;
    label: string;
    color: string | null;
    is_open: boolean;
};

export type TechnicianIncidentDetail = {
    id: number;
    status_id: number | null;
    status_name: string | null;
    status_color: string | null;
    technician_incident_type_id: number;
    type_name: string | null;
    incident_text: string | null;
    response_text: string | null;
    requested_by_id: number | null;
    requested_by_name: string | null;
    responded_by_id: number | null;
    responded_by_name: string | null;
    responded_at: string | null;
    technician_id: number | null;
    technician_name: string | null;
    is_verified: boolean;
    verified_at: string | null;
    verified_by_id: number | null;
    verified_by_name: string | null;
    due_at: string | null;
    negotiation_succeeded: boolean | null;
    unsuccessful_negotiation_solution: string | null;
};

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

export const technicianIncidentsService = {
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

    visitPage,

    indexPath: base,
    createPath: `${base}/create`,
    createPathForTechnician: (relationshipId: number) => `${base}/create?technician_id=${relationshipId}`,
    dataPath: `${base}/data`,
    dataPathForTechnician: (relationshipId: number) => `/technicians/${relationshipId}/incidents/data`,
    showPath: (id: number) => `${base}/${id}`,

    async fetchDetail(id: number): Promise<{
        incident: TechnicianIncidentDetail;
        messages: TechnicianIncidentMessage[];
        statusOptions: TechnicianIncidentStatusOption[];
    }> {
        const response = await fetch(`${base}/${id}`, {
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

        return (await response.json()) as {
            incident: TechnicianIncidentDetail;
            messages: TechnicianIncidentMessage[];
            statusOptions: TechnicianIncidentStatusOption[];
        };
    },

    async updateStatus(id: number, statusId: number): Promise<{
        incident: TechnicianIncidentDetail;
        statusOptions: TechnicianIncidentStatusOption[];
    }> {
        const response = await fetch(`${base}/${id}/status`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ status_id: statusId }),
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        return (await response.json()) as {
            incident: TechnicianIncidentDetail;
            statusOptions: TechnicianIncidentStatusOption[];
        };
    },

    async postMessage(
        id: number,
        payload: { body?: string; file?: File | null },
    ): Promise<TechnicianIncidentMessage> {
        const data = new FormData();

        if (payload.file) {
            data.append('file', payload.file);
        } else if (payload.body) {
            data.append('body', payload.body);
        }

        const response = await fetch(`${base}/${id}/messages`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: data,
        });

        if (!response.ok) {
            throw new Error(`Request failed (${response.status})`);
        }

        const result = (await response.json()) as { message: TechnicianIncidentMessage };

        return result.message;
    },

    async verify(
        id: number,
        data: {
            response_text?: string | null;
            negotiation_succeeded?: boolean | null;
            unsuccessful_negotiation_solution?: string | null;
        } = {},
    ): Promise<{ incident: TechnicianIncidentDetail; statusOptions: TechnicianIncidentStatusOption[] }> {
        const response = await fetch(`${base}/${id}/verify`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(data),
        });

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as
                | { message?: string; errors?: Record<string, string[]> }
                | null;
            const firstError = payload?.errors
                ? Object.values(payload.errors).flat()[0]
                : payload?.message;

            throw new Error(firstError || `Request failed (${response.status})`);
        }

        return (await response.json()) as {
            incident: TechnicianIncidentDetail;
            statusOptions: TechnicianIncidentStatusOption[];
        };
    },
};
