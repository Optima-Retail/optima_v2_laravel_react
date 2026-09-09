export type IncidentListItem = {
    id: number;
    subject: string;
    establishment_name: string | null;
    status_name: string | null;
    status_color: string | null;
    priority_name: string | null;
    priority_color: string | null;
    type_name: string | null;
    type_color: string | null;
    responsible_user_name: string | null;
    control_at: string | null;
    closed_at: string | null;
    created_at: string | null;
};

export type IncidentFormData = {
    id: number;
    subject: string;
    comment: string | null;
    establishment_id: number | null;
    evaluation_id: number | null;
    incident_status_id: number | null;
    incident_priority_id: number | null;
    incident_type_id: number | null;
    incident_subtype_id: number | null;
    requester_user_id: number | null;
    responsible_user_id: number | null;
    qc_responsible_user_id: number | null;
    control_at: string | null;
    duration_seconds: number | null;
    qc_duration_seconds: number | null;
    closed_at: string | null;
    origin_type: string | null;
    origin_id: number | null;
    related_type: string | null;
    related_id: number | null;
};

export type IncidentSubtypeOption = {
    id: number;
    label: string;
    incident_type_id: number;
};

export type IncidentTypeOption = {
    id: number;
    label: string;
    color?: string | null;
    default_priority_id: number | null;
};

export type IncidentTypeWorkflowConfig = {
    origin_selectable: boolean;
    origin_options: string[];
    default_origin_type: string | null;
    origin_required: boolean;
    related_type: string | null;
    show_related: boolean;
};

export type IncidentTypeWorkflowMap = Record<string, IncidentTypeWorkflowConfig>;
