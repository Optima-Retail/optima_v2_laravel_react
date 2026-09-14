import type {
    DocumentChatMessage,
    DocumentChatPayload,
    DocumentChatType,
} from '@/support/types/domain/chat';

function basePath(type: DocumentChatType, documentId: number): string {
    return `/document-chats/${type}/${documentId}`;
}

async function parseJson<T>(response: Response): Promise<T> {
    if (!response.ok) {
        throw new Error(`document_chats_http_${response.status}`);
    }

    return (await response.json()) as T;
}

/** Laravel sets an encrypted XSRF-TOKEN cookie; send it as X-XSRF-TOKEN (decoded). */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : '';
}

function jsonHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function formHeaders(): HeadersInit {
    return {
        Accept: 'application/json',
        'X-XSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export const documentChatsService = {
    async list(type: DocumentChatType, documentId: number): Promise<DocumentChatPayload> {
        const response = await fetch(`${basePath(type, documentId)}/messages`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        return parseJson<DocumentChatPayload>(response);
    },

    async postMessage(
        type: DocumentChatType,
        documentId: number,
        payload: { body: string; is_private?: boolean },
    ): Promise<DocumentChatMessage> {
        const response = await fetch(`${basePath(type, documentId)}/messages`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: jsonHeaders(),
            body: JSON.stringify({
                body: payload.body,
                is_private: payload.is_private ?? false,
            }),
        });

        const json = await parseJson<{ message: DocumentChatMessage }>(response);

        return json.message;
    },

    async postAttachment(
        type: DocumentChatType,
        documentId: number,
        file: File,
        isPrivate = false,
    ): Promise<DocumentChatMessage> {
        const data = new FormData();
        data.append('file', file);
        data.append('is_private', isPrivate ? '1' : '0');

        const response = await fetch(`${basePath(type, documentId)}/messages/attachments`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: formHeaders(),
            body: data,
        });

        const json = await parseJson<{ message: DocumentChatMessage }>(response);

        return json.message;
    },

    async markRead(type: DocumentChatType, documentId: number): Promise<void> {
        const response = await fetch(`${basePath(type, documentId)}/read`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`document_chats_http_${response.status}`);
        }
    },

    async mute(type: DocumentChatType, documentId: number): Promise<void> {
        const response = await fetch(`${basePath(type, documentId)}/mute`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`document_chats_http_${response.status}`);
        }
    },

    async unmute(type: DocumentChatType, documentId: number): Promise<void> {
        const response = await fetch(`${basePath(type, documentId)}/mute`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`document_chats_http_${response.status}`);
        }
    },
};
