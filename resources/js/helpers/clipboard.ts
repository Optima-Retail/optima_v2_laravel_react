/**
 * Copy plain text to the clipboard. Falls back to a temporary textarea when
 * the Clipboard API is unavailable (e.g. non-secure contexts).
 */
export async function copyText(value: string): Promise<boolean> {
    const text = value.trim();

    if (text === '') {
        return false;
    }

    try {
        if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // Fall through to execCommand fallback.
    }

    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textarea);

        return ok;
    } catch {
        return false;
    }
}
