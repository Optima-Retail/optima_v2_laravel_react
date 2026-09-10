const base = '/suppliers';

export type TechnicianVehicleRow = {
    id: number;
    brand: string | null;
    model: string | null;
    license_plate: string | null;
};

export type TechnicianVehiclesPayload = {
    data: TechnicianVehicleRow[];
};

export type TechnicianVehiclesFieldErrors = Record<string, string[]>;

export class TechnicianVehiclesApiError extends Error {
    readonly status: number;
    readonly errors: TechnicianVehiclesFieldErrors;

    constructor(message: string, status: number, errors: TechnicianVehiclesFieldErrors = {}) {
        super(message);
        this.name = 'TechnicianVehiclesApiError';
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
        let errors: TechnicianVehiclesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: TechnicianVehiclesFieldErrors;
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

        throw new TechnicianVehiclesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchTechnicianVehicles(relationshipId: number): Promise<TechnicianVehiclesPayload> {
    const response = await fetch(`${base}/${relationshipId}/vehicles`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<TechnicianVehiclesPayload>(response);

    return {
        data: payload.data ?? [],
    };
}

export async function syncTechnicianVehicles(
    relationshipId: number,
    vehicles: Array<{
        id: number | null;
        brand: string | null;
        model: string | null;
        license_plate: string | null;
    }>,
): Promise<TechnicianVehicleRow[]> {
    const response = await fetch(`${base}/${relationshipId}/vehicles`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ vehicles }),
    });

    const payload = await parseJson<{ data: TechnicianVehicleRow[] }>(response);

    return payload.data ?? [];
}
