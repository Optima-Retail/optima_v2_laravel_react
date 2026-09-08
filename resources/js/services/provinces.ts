const base = '/config/countries';

export type CountryProvinceRow = {
    id: number;
    name: string;
    code: string | null;
    country_id: number;
};

export type ProvincesFieldErrors = Record<string, string[]>;

export class ProvincesApiError extends Error {
    readonly status: number;
    readonly errors: ProvincesFieldErrors;

    constructor(message: string, status: number, errors: ProvincesFieldErrors = {}) {
        super(message);
        this.name = 'ProvincesApiError';
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
        let errors: ProvincesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: ProvincesFieldErrors;
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

        throw new ProvincesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export const provincesService = {
    async forCountry(countryId: number): Promise<CountryProvinceRow[]> {
        const response = await fetch(`${base}/${countryId}/provinces`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await parseJson<{ data: CountryProvinceRow[] }>(response);

        return payload.data ?? [];
    },

    async syncForCountry(
        countryId: number,
        provinces: Array<{ id: number | null; name: string; code: string | null }>,
    ): Promise<CountryProvinceRow[]> {
        const response = await fetch(`${base}/${countryId}/provinces`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ provinces }),
        });

        const payload = await parseJson<{ data: CountryProvinceRow[] }>(response);

        return payload.data ?? [];
    },
};
