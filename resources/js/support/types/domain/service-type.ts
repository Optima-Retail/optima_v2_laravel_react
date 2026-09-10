export type ServiceTypeListItem = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
    created_at: string | null;
};

export type ServiceTypeFormData = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
};
