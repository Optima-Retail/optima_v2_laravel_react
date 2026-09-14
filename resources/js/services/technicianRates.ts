const base = '/technicians';

export type TechnicianRateRow = {
    id: number | null;
    company_relationship_id: number;
    labor_weekday_amount: string;
    labor_night_amount: string;
    labor_weekend_amount: string;
    labor_holiday_amount: string;
    labor_urgent_amount: string;
    travel_weekday_amount: string;
    travel_night_amount: string;
    travel_weekend_amount: string;
    travel_holiday_amount: string;
    travel_urgent_amount: string;
};

export type TechnicianRatesFieldErrors = Record<string, string[]>;

export class TechnicianRatesApiError extends Error {
    readonly status: number;
    readonly errors: TechnicianRatesFieldErrors;

    constructor(message: string, status: number, errors: TechnicianRatesFieldErrors = {}) {
        super(message);
        this.name = 'TechnicianRatesApiError';
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
        let errors: TechnicianRatesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: TechnicianRatesFieldErrors;
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

        throw new TechnicianRatesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchTechnicianRates(relationshipId: number): Promise<TechnicianRateRow> {
    const response = await fetch(`${base}/${relationshipId}/rates`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<{ data: TechnicianRateRow }>(response);

    return payload.data;
}

export async function upsertTechnicianRates(
    relationshipId: number,
    rates: Omit<TechnicianRateRow, 'id' | 'company_relationship_id'>,
): Promise<TechnicianRateRow> {
    const response = await fetch(`${base}/${relationshipId}/rates`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(rates),
    });

    const payload = await parseJson<{ data: TechnicianRateRow }>(response);

    return payload.data;
}
