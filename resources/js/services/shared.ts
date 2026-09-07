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

export function cleanQuery(params: Record<string, string | undefined>) {
    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== undefined && value.trim() !== ''),
    );
}

/** Update the address bar without an Inertia/network request (for remote tables). */
export function replaceListQueryUrl(path: string, params: Record<string, string | undefined>) {
    const cleaned = cleanQuery(params) as Record<string, string>;
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
