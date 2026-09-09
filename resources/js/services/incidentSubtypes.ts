const base = '/config/incident-types';

export type IncidentTypeSubtypeRow = {
    id: number;
    name: string;
    incident_type_id: number;
};

export type IncidentSubtypesFieldErrors = Record<string, string[]>;

export class IncidentSubtypesApiError extends Error {
    readonly status: number;
    readonly errors: IncidentSubtypesFieldErrors;

    constructor(message: string, status: number, errors: IncidentSubtypesFieldErrors = {}) {
        super(message);
        this.name = 'IncidentSubtypesApiError';
        this.status = status;
        this.errors = errors;
    }
}

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function parseJson<T>(response: Response): Promise<T> {
    if (!response.ok) {
        let message = `Request failed (${response.status})`;
        let errors: IncidentSubtypesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: IncidentSubtypesFieldErrors;
            };
            if (payload.message) {
                message = payload.message;
            }
            if (payload.errors && typeof payload.errors === 'object') {
                errors = payload.errors;
            }
        } catch {
            // keep default
        }

        throw new IncidentSubtypesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export const incidentSubtypesService = {
    async forType(incidentTypeId: number): Promise<IncidentTypeSubtypeRow[]> {
        const response = await fetch(`${base}/${incidentTypeId}/subtypes`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await parseJson<{ data: IncidentTypeSubtypeRow[] }>(response);

        return payload.data ?? [];
    },

    async syncForType(
        incidentTypeId: number,
        subtypes: Array<{ id: number | null; name: string }>,
    ): Promise<IncidentTypeSubtypeRow[]> {
        const response = await fetch(`${base}/${incidentTypeId}/subtypes`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ subtypes }),
        });

        const payload = await parseJson<{ data: IncidentTypeSubtypeRow[] }>(response);

        return payload.data ?? [];
    },
};
