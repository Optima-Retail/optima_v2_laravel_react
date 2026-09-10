export type TaskDocumentType = 'work_order' | 'estimate';

export type TaskDocumentTypeOption = {
    value: TaskDocumentType;
    label: string;
};

export type TaskToPerformListItem = {
    id: number;
    title: string | null;
    description: string | null;
    is_completed: boolean;
    document_type: TaskDocumentType;
    document_id: number | null;
    created_at: string | null;
};

export type TaskToPerformFormData = {
    id: number;
    title: string | null;
    description: string | null;
    is_completed: boolean;
    document_type: TaskDocumentType;
    document_id: number | null;
};
