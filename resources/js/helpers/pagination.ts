import type { PaginationLink, Paginated } from '@/support/pagination';

export type PageWindowItem = number | 'ellipsis';

/**
 * Compact page list: first, last, current ± siblings, with ellipsis for gaps.
 * For small page counts, shows every page (no ellipsis noise).
 */
export function buildPageWindow(
    current: number,
    last: number,
    siblings = 1,
): PageWindowItem[] {
    if (last <= 1) {
        return [];
    }

    const safeCurrent = Math.min(Math.max(1, current), last);

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const pages = new Set<number>([1, last]);

    for (let page = safeCurrent - siblings; page <= safeCurrent + siblings; page += 1) {
        if (page >= 1 && page <= last) {
            pages.add(page);
        }
    }

    const sorted = [...pages].sort((a, b) => a - b);
    const items: PageWindowItem[] = [];
    let previous = 0;

    for (const page of sorted) {
        if (previous > 0 && page - previous > 1) {
            if (page - previous === 2) {
                items.push(previous + 1);
            } else {
                items.push('ellipsis');
            }
        }

        items.push(page);
        previous = page;
    }

    return items;
}

export function usablePaginationLinks(links: PaginationLink[]): PaginationLink[] {
    return links.filter(
        (link) => link.url !== null || link.active || isEllipsisLabel(link.label),
    );
}

export function isEllipsisLabel(label: string): boolean {
    const plain = label.replace(/&hellip;|&#8230;/gi, '…').trim();

    return plain === '...' || plain === '…';
}

export function isPreviousLabel(label: string): boolean {
    return /previous|anterior|«|&laquo;/i.test(label);
}

export function isNextLabel(label: string): boolean {
    return /next|siguiente|»|&raquo;/i.test(label);
}

/** Resolve a page number to a visit URL from Laravel-style links, else the page as string. */
export function resolvePageUrl<T>(paginator: Paginated<T>, page: number): string | null {
    const match = paginator.links.find(
        (link) => link.url !== null && link.label.trim() === String(page),
    );

    if (match?.url) {
        return match.url;
    }

    if (page < 1 || page > paginator.last_page) {
        return null;
    }

    return String(page);
}

export function resolveAdjacentUrl<T>(
    paginator: Paginated<T>,
    direction: 'prev' | 'next',
): string | null {
    const current = paginator.current_page ?? 1;
    const target = direction === 'prev' ? current - 1 : current + 1;

    if (target < 1 || target > paginator.last_page) {
        return null;
    }

    const byRole = paginator.links.find((link) =>
        direction === 'prev' ? isPreviousLabel(link.label) : isNextLabel(link.label),
    );

    if (byRole?.url) {
        return byRole.url;
    }

    return resolvePageUrl(paginator, target);
}
