const base = '/technicians';

export type TechnicianRatingSource = 'optima' | 'customer';

export type TechnicianRatingRow = {
    id: number;
    company_relationship_id: number;
    work_order_id: number | null;
    score: number;
    notes: string | null;
    source: TechnicianRatingSource | string;
    created_at: string | null;
};

export type TechnicianRatingAggregates = {
    optima_score: string | null;
    customer_score: string | null;
    average_score: string | null;
    optima_score_count: number;
    customer_score_count: number;
};

export type TechnicianRatingStoreResult = TechnicianRatingRow & {
    aggregates: TechnicianRatingAggregates;
};

export type TechnicianRatingsFieldErrors = Record<string, string[]>;

export class TechnicianRatingsApiError extends Error {
    readonly status: number;
    readonly errors: TechnicianRatingsFieldErrors;

    constructor(message: string, status: number, errors: TechnicianRatingsFieldErrors = {}) {
        super(message);
        this.name = 'TechnicianRatingsApiError';
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
        let errors: TechnicianRatingsFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: TechnicianRatingsFieldErrors;
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

        throw new TechnicianRatingsApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchTechnicianRatings(relationshipId: number): Promise<TechnicianRatingRow[]> {
    const response = await fetch(`${base}/${relationshipId}/ratings`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<{ data: TechnicianRatingRow[] }>(response);

    return payload.data ?? [];
}

export async function storeTechnicianRating(
    relationshipId: number,
    payload: {
        score: number;
        notes?: string | null;
        source: TechnicianRatingSource;
        work_order_id?: number | null;
    },
): Promise<TechnicianRatingStoreResult> {
    const response = await fetch(`${base}/${relationshipId}/ratings`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(payload),
    });

    const body = await parseJson<{ data: TechnicianRatingStoreResult }>(response);

    return body.data;
}
