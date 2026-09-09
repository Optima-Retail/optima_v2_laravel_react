export type IncidentPriorityListItem = {
    id: number;
    name: string;
    color: string | null;
    resolution_time_hours: number;
    created_at: string | null;
};

export type IncidentPriorityFormData = {
    id: number;
    name: string;
    color: string | null;
    resolution_time_hours: number;
};
