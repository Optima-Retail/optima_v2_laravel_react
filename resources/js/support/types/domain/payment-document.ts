export type PaymentDocumentListItem = {
    id: number;
    name: string;
    is_active: boolean;
    created_at: string | null;
};

export type PaymentDocumentFormData = {
    id: number;
    name: string;
    is_active: boolean;
};
