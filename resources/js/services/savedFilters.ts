export type SavedFilterItem = {
    id: number;
    name: string;
    page_key: string;
    filters: Record<string, string>;
    is_default: boolean;
};

async function parseJson<T>(response: Response): Promise<T> {
    if (!response.ok) {
        throw new Error(`saved_filters_http_${response.status}`);
    }

    return (await response.json()) as T;
}

/** Laravel sets an encrypted XSRF-TOKEN cookie; send it as X-XSRF-TOKEN (decoded). */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export const savedFiltersService = {
    async list(pageKey: string): Promise<SavedFilterItem[]> {
        const response = await fetch(`/saved-filters?page_key=${encodeURIComponent(pageKey)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const json = await parseJson<{ data: SavedFilterItem[] }>(response);

        return json.data;
    },

    async save(payload: {
        name: string;
        page_key: string;
        filters: Record<string, string>;
        is_default?: boolean;
    }): Promise<SavedFilterItem> {
        const response = await fetch('/saved-filters', {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const json = await parseJson<{ data: SavedFilterItem }>(response);

        return json.data;
    },

    async destroy(id: number): Promise<void> {
        const response = await fetch(`/saved-filters/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`saved_filters_http_${response.status}`);
        }
    },

    async setDefault(id: number, isDefault: boolean): Promise<SavedFilterItem> {
        const response = await fetch(`/saved-filters/${id}/default`, {
            method: 'PATCH',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ is_default: isDefault }),
        });

        const json = await parseJson<{ data: SavedFilterItem }>(response);

        return json.data;
    },
};
