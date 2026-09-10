export type FormStatusListItem = {
    id: number;
    name: string;
    next_status_id: number | null;
    next_status_name: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type FormStatusFormData = {
    id: number;
    name: string;
    next_status_id: number | null;
    is_active: boolean;
};
