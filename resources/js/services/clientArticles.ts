const base = '/clients';

export type ClientArticleRow = {
    id: number;
    article_id: number;
    article_code: string;
    article_name: string | null;
    sale_price: string;
};

export type ClientArticleOption = {
    id: number;
    label: string;
};

export type ClientArticlesPayload = {
    data: ClientArticleRow[];
    article_options: ClientArticleOption[];
};

export type ClientArticlesFieldErrors = Record<string, string[]>;

export class ClientArticlesApiError extends Error {
    readonly status: number;
    readonly errors: ClientArticlesFieldErrors;

    constructor(message: string, status: number, errors: ClientArticlesFieldErrors = {}) {
        super(message);
        this.name = 'ClientArticlesApiError';
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
        let errors: ClientArticlesFieldErrors = {};

        try {
            const payload = (await response.json()) as {
                message?: string;
                errors?: ClientArticlesFieldErrors;
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

        throw new ClientArticlesApiError(message, response.status, errors);
    }

    return (await response.json()) as T;
}

export async function fetchClientArticles(relationshipId: number): Promise<ClientArticlesPayload> {
    const response = await fetch(`${base}/${relationshipId}/articles`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    const payload = await parseJson<ClientArticlesPayload>(response);

    return {
        data: payload.data ?? [],
        article_options: payload.article_options ?? [],
    };
}

export async function syncClientArticles(
    relationshipId: number,
    articles: Array<{ id: number | null; article_id: number; sale_price: number | string }>,
): Promise<ClientArticleRow[]> {
    const response = await fetch(`${base}/${relationshipId}/articles`, {
        method: 'PUT',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ articles }),
    });

    const payload = await parseJson<{ data: ClientArticleRow[] }>(response);

    return payload.data ?? [];
}
