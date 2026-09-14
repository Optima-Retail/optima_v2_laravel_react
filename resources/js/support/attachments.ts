/** Detect image attachments from MIME type or filename extension. */
export function isImageAttachment(mimeType: string | null | undefined, filename?: string | null): boolean {
    if (typeof mimeType === 'string' && mimeType.toLowerCase().startsWith('image/')) {
        return true;
    }

    if (!filename) {
        return false;
    }

    const extension = filename.split('.').pop()?.toLowerCase() ?? '';

    return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'].includes(extension);
}

export function attachmentViewUrl(downloadUrl: string, isImage: boolean): string {
    if (!isImage) {
        return downloadUrl;
    }

    return `${downloadUrl}${downloadUrl.includes('?') ? '&' : '?'}inline=1`;
}
