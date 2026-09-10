const base = '/clients';

export type ClientRateRow = {
    id: number;
    client_priority_id: number;
    client_priority_label: string;
    work_order_type_id: number;
    work_order_type_label: string;
    travel_amount: string;
    extra_travel_amount: string;
    labor_amount: string;
    extra_labor_amount: string;
    due_hours: number;
    sla_hours: number;
    is_urgent: boolean;
};

export type ClientRatePriorityOption = {
    id: number;
    name: string;
    color: string | null;
};

export type ClientRateWorkOrderTypeOption = {
    id: number;
    name: string;
    color: string | null;
    label: string;
};

export type ClientRatesPayload = {
    data: ClientRateRow[];
    selected_priorities: ClientRatePriorityOption[];
    work_order_types: ClientRateWorkOrderTypeOption[];
};

export type ClientRatesFieldErrors = Record<string, string[]>;

export class ClientRatesApiError extends Error {
    readonly status: number;
    readonly errors: ClientRatesFieldErrors;

    constructor(message: string, status: number, errors: ClientRatesFieldErrors = {}) {
        super(message);
        this.name = 'ClientRatesApiError';
        this.status = status;
        this.errors = errors;
    }
}

export type ClientRateSyncRow = {
    id: number | null;
    client_priority_id: number;
    work_order_type_id: number;
    travel_amount: number | string;
    extra_travel_amount: number | string;
    labor_amount: number | string;
    extra_labor_amount: number | string;
    due_hours: number | string;
    sla_hours: number | string;
    is_urgent: boolean;
};

function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

async function parseJson<T>(response: Response): Promise<T> {
    if (!response.ok) {
        let message = `Request failed (${response.status})`;
        let errors: ClientRatesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: ClientRatesFieldErrors;
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

        throw new ClientRatesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchClientRates(relationshipId: number): Promise<ClientRatesPayload> {
    const response = await fetch(`${base}/${relationshipId}/rates`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<ClientRatesPayload>(response);

    return {
        data: payload.data ?? [],
        selected_priorities: payload.selected_priorities ?? [],
        work_order_types: payload.work_order_types ?? [],
    };
}

export async function syncClientRates(
    relationshipId: number,
    rates: ClientRateSyncRow[],
): Promise<ClientRateRow[]> {
    const response = await fetch(`${base}/${relationshipId}/rates`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ rates }),
    });

    const payload = await parseJson<{ data: ClientRateRow[] }>(response);

    return payload.data ?? [];
}
