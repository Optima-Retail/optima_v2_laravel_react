export type ContractListItem = {
    id: number;
    code: string | null;
    description: string | null;
    company_name: string | null;
    brand_name: string | null;
    status_name: string | null;
    status_color: string | null;
    signed_at: string | null;
    total_amount: string | number | null;
    created_at: string | null;
};

export type ContractFormData = {
    id: number;
    code: string | null;
    company_id: number | null;
    responsible_user_id: number | null;
    contract_status_id: number | null;
    language_id: number | null;
    description: string | null;
    work_order_subject: string | null;
    signed_at: string | null;
    canceled_at: string | null;
    establishment_ids: number[];
};

export type ContractAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    uploaded_by_name: string | null;
    download_url: string;
    created_at: string | null;
};
