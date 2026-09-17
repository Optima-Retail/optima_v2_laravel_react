/**
 * Locale-aware date/time formatting for display.
 * Matches the pattern used in IncidentLinesPanel.
 */

export function formatDateTime(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function formatDate(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString(locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}

/** Message bubble time (legacy uses H:i). */
export function formatTime(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleTimeString(locale, {
        hour: '2-digit',
        minute: '2-digit',
    });
}

const RELATIVE_DIVISIONS: Array<{ amount: number; unit: Intl.RelativeTimeFormatUnit }> = [
    { amount: 60, unit: 'second' },
    { amount: 60, unit: 'minute' },
    { amount: 24, unit: 'hour' },
    { amount: 7, unit: 'day' },
    { amount: 4.34524, unit: 'week' },
    { amount: 12, unit: 'month' },
    { amount: Number.POSITIVE_INFINITY, unit: 'year' },
];

/** Relative time like "1 month ago" / "hace 1 mes". */
export function formatRelativeTime(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    let duration = (date.getTime() - Date.now()) / 1000;
    const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });

    for (const division of RELATIVE_DIVISIONS) {
        if (Math.abs(duration) < division.amount) {
            return formatter.format(Math.round(duration), division.unit);
        }

        duration /= division.amount;
    }

    return formatter.format(Math.round(duration), 'year');
}
