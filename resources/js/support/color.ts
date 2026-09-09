import type { CSSProperties } from 'react';

/** Relative luminance → dark or light ink for readable text on a hex background. */
export function contrastingInk(hex: string | null | undefined): string {
    if (!hex) {
        return 'inherit';
    }

    const normalized = hex.trim().replace(/^#/, '');
    const full =
        normalized.length === 3
            ? normalized
                  .split('')
                  .map((char) => char + char)
                  .join('')
            : normalized;

    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return '#111827';
    }

    const r = Number.parseInt(full.slice(0, 2), 16);
    const g = Number.parseInt(full.slice(2, 4), 16);
    const b = Number.parseInt(full.slice(4, 6), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

    return luminance > 0.6 ? '#111827' : '#ffffff';
}

export function optionColorStyle(color: string | null | undefined): CSSProperties | undefined {
    if (!color) {
        return undefined;
    }

    return {
        backgroundColor: color,
        color: contrastingInk(color),
    };
}
