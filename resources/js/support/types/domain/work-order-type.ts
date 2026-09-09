export type WorkOrderTypeListItem = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
    created_at: string | null;
};

export type WorkOrderTypeFormData = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
};
