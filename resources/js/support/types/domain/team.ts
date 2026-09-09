export type TeamListItem = {
    id: number;
    code: string;
    name: string;
    manager_id: number | null;
    controller_id: number | null;
    created_at: string | null;
};

export type TeamFormData = {
    id: number;
    code: string;
    name: string;
    manager_id: number | null;
    controller_id: number | null;
};
