export type PaymentMethodListItem = {
    id: number;
    name: string;
    due_count: number | null;
    days: number | null;
    code: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type PaymentMethodFormData = {
    id: number;
    name: string;
    due_count: number | null;
    days: number | null;
    code: string | null;
    is_active: boolean;
};
