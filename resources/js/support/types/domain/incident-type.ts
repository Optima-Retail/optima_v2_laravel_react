export type IncidentTypeListItem = {
    id: number;
    name: string;
    color: string | null;
    default_priority_id: number | null;
    default_priority_name: string | null;
    default_priority_color: string | null;
    origin_required: boolean;
    created_at: string | null;
};

export type IncidentTypeFormData = {
    id: number;
    name: string;
    color: string | null;
    default_priority_id: number | null;
    origin_selectable: boolean;
    origin_options: string[];
    default_origin_type: string | null;
    origin_required: boolean;
    related_type: string | null;
    show_related: boolean;
};
