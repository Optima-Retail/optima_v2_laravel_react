export type IncidentStatusListItem = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    created_at: string | null;
};

export type IncidentStatusFormData = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    excluded_type_ids: number[];
};
