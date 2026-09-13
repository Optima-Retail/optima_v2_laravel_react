export type ChecklistListItem = {
    id: number;
    label: string;
    requires_validation: boolean;
    document_type: string;
    status_label: string;
    status_color: string | null;
    sort_order: number;
    created_at: string | null;
};

export type ChecklistFormData = {
    id: number;
    label: string;
    requires_validation: boolean;
    document_type: string;
    work_order_status_id: number | null;
    sort_order: number;
};

export type ChecklistDocumentTypeOption = {
    value: string;
    label: string;
};

export type ChecklistStatusOption = {
    id: number;
    label: string;
};
