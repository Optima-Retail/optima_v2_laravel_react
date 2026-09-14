export type TechnicianIncidentStatusListItem = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    is_default: boolean;
    marks_verified: boolean;
    sets_response_date: boolean;
    created_at: string | null;
};

export type TechnicianIncidentStatusFormData = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    is_default: boolean;
    marks_verified: boolean;
    sets_response_date: boolean;
};
