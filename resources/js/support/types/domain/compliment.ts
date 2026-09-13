export type ComplimentAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    uploaded_by_name: string | null;
    download_url: string;
    view_url: string;
    is_image: boolean;
    created_at: string | null;
};
