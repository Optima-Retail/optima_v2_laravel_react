export type WorkOrderStatusListItem = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    created_at: string | null;
};

export type WorkOrderStatusFormData = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
};
