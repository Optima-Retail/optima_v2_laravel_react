import { router } from '@inertiajs/react';

export type SearchOptions = {
    preserveState?: boolean;
    replace?: boolean;
};

export type InertiaFormPoster = {
    post: (url: string, options?: Record<string, unknown>) => void;
    put: (url: string, options?: Record<string, unknown>) => void;
};

export type ListQuery = {
    search?: string;
    role?: string;
    type?: string;
    status?: string;
    sort?: string;
    direction?: string;
    per_page?: string;
};

export function cleanQuery(params: Record<string, string | number | undefined | null>) {
    return Object.fromEntries(
        Object.entries(params)
            .map(([key, value]) => {
                if (value === undefined || value === null) {
                    return [key, undefined] as const;
                }

                return [key, String(value)] as const;
            })
            .filter((entry): entry is [string, string] => entry[1] !== undefined && entry[1].trim() !== ''),
    );
}

/** Update the address bar without an Inertia/network request (for remote tables). */
export function replaceListQueryUrl(path: string, params: Record<string, string | number | undefined | null>) {
    const cleaned = cleanQuery(params);
    const qs = new URLSearchParams(cleaned).toString();
    const next = qs ? `${path}?${qs}` : path;

    if (`${window.location.pathname}${window.location.search}` === next) {
        return;
    }

    window.history.replaceState(window.history.state, '', next);
}

export function visitPage(url: string) {
    router.get(url);
}
