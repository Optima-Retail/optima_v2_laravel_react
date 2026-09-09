export type ClientPriorityListItem = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
    level: number;
    created_at: string | null;
};

export type ClientPriorityFormData = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
    level: number;
};
