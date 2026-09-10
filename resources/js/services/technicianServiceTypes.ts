const base = '/suppliers';

export type TechnicianServiceTypeRow = {
    id: number;
    service_type_id: number;
    service_type_label: string;
    service_type_color: string | null;
};

export type TechnicianServiceTypeOption = {
    id: number;
    label: string;
    color: string | null;
};

export type TechnicianServiceTypesPayload = {
    data: TechnicianServiceTypeRow[];
    service_type_options: TechnicianServiceTypeOption[];
};

export type TechnicianServiceTypesFieldErrors = Record<string, string[]>;

export class TechnicianServiceTypesApiError extends Error {
    readonly status: number;
    readonly errors: TechnicianServiceTypesFieldErrors;

    constructor(message: string, status: number, errors: TechnicianServiceTypesFieldErrors = {}) {
        super(message);
        this.name = 'TechnicianServiceTypesApiError';
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
        let errors: TechnicianServiceTypesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: TechnicianServiceTypesFieldErrors;
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

        throw new TechnicianServiceTypesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchTechnicianServiceTypes(
    relationshipId: number,
): Promise<TechnicianServiceTypesPayload> {
    const response = await fetch(`${base}/${relationshipId}/service-types`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<TechnicianServiceTypesPayload>(response);

    return {
        data: payload.data ?? [],
        service_type_options: payload.service_type_options ?? [],
    };
}

export async function syncTechnicianServiceTypes(
    relationshipId: number,
    serviceTypeIds: number[],
): Promise<TechnicianServiceTypeRow[]> {
    const response = await fetch(`${base}/${relationshipId}/service-types`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ service_type_ids: serviceTypeIds }),
    });

    const payload = await parseJson<{ data: TechnicianServiceTypeRow[] }>(response);

    return payload.data ?? [];
}
