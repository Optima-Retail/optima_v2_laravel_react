import DOMPurify from 'isomorphic-dompurify';

/** TipTap empty doc is often `<p></p>` — treat as blank for validation/submit. */
export function isEmptyRichText(html: string | null | undefined): boolean {
    if (html == null || html.trim() === '') {
        return true;
    }

    const text = html
        .replace(/<br\s*\/?>/gi, '')
        .replace(/&nbsp;/gi, ' ')
        .replace(/<[^>]+>/g, '')
        .trim();

    return text === '';
}

export function normalizeRichText(html: string | null | undefined): string {
    if (isEmptyRichText(html)) {
        return '';
    }

    return (html ?? '').trim();
}

export function sanitizeRichText(html: string | null | undefined): string {
    if (isEmptyRichText(html)) {
        return '';
    }

    return DOMPurify.sanitize(html ?? '', {
        USE_PROFILES: { html: true },
        ADD_ATTR: ['style', 'target', 'rel', 'class'],
    });
}
