export type DocumentChatType =
    | 'work_order'
    | 'incident'
    | 'evaluation'
    | 'technician_request'
    | 'technician';

export type DocumentChatMessageType = 'text' | 'system' | 'file';

export type DocumentChatAttachment = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    download_url: string;
    view_url: string;
    is_image: boolean;
};

export type DocumentChatMessage = {
    id: number;
    type: DocumentChatMessageType | string;
    body: string | null;
    is_private: boolean;
    user_id: number | null;
    user_name: string | null;
    created_at: string | null;
    attachments: DocumentChatAttachment[];
};

export type DocumentChatPayload = {
    chat_id: number;
    name?: string | null;
    is_muted: boolean;
    has_unread: boolean;
    can_see_private: boolean;
    messages: DocumentChatMessage[];
};
