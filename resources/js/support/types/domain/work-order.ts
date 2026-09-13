export type WorkOrderStage = 'estimate' | 'work_order';

export type WorkOrderListItem = {
    id: number;
    code: string | null;
    subject: string | null;
    stage: WorkOrderStage;
    establishment_name: string | null;
    status_name: string | null;
    status_color: string | null;
    type_name: string | null;
    responsible_user_name: string | null;
    is_urgent: boolean;
    created_at: string | null;
};

export type WorkOrderLineForm = {
    id?: number | null;
    article_id: string;
    description: string;
    quantity: string;
    unit_price: string;
};

export type WorkOrderTechnicianForm = {
    id?: number | null;
    company_relationship_id: string;
    is_selected: boolean;
    quote_net_amount: string;
};

export type WorkOrderFormData = {
    id: number;
    public_id: string;
    code: string | null;
    subject: string | null;
    reference: string | null;
    purchase_order: string | null;
    stage: WorkOrderStage;
    confirmed_at: string | null;
    source_work_order_id: number | null;
    source_work_order_label: string | null;
    status_id: number | null;
    work_order_type_id: number | null;
    client_priority_id: number | null;
    is_urgent: boolean;
    establishment_id: number | null;
    billing_company_id: number | null;
    responsible_user_id: number | null;
    requester_id: number | null;
    notes: string | null;
    internal_notes: string | null;
    received_at: string | null;
    intervention_at: string | null;
    due_at: string | null;
    collaborator_ids: number[];
    lines: Array<{
        id: number;
        article_id: number | null;
        description: string | null;
        quantity: string | number | null;
        unit_price: string | number | null;
    }>;
    technicians: Array<{
        id: number;
        company_relationship_id: number;
        is_selected: boolean;
        quote_net_amount: string | number | null;
    }>;
};

export type WorkOrderAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    uploaded_by_name: string | null;
    download_url: string;
    created_at: string | null;
};
