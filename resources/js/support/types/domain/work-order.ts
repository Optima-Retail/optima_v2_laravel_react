export type WorkOrderStage = 'estimate' | 'work_order';

export type WorkOrderListItem = {
    id: number;
    code: string | null;
    subject: string | null;
    stage: WorkOrderStage;
    establishment_name: string | null;
    status_name: string | null;
    status_color: string | null;
    status_id: number | null;
    priority_name: string | null;
    priority_color: string | null;
    type_name: string | null;
    type_color: string | null;
    responsible_user_name: string | null;
    technician_name: string | null;
    is_urgent: boolean;
    total_euros: number | null;
    cost_amount: number | null;
    margin_percentage: number | null;
    intervention_at: string | null;
    expected_close_at: string | null;
    closed_at: string | null;
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
    quoted_at: string;
    quote_total_euros: string;
};

export type WorkOrderTaskForm = {
    id?: number | null;
    title: string;
    description: string;
    is_completed: boolean;
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
    status_is_open?: boolean;
    work_order_type_id: number | null;
    client_priority_id: number | null;
    is_urgent: boolean;
    establishment_id: number | null;
    contract_id: number | null;
    delegation_id?: number | null;
    currency_id: number | null;
    currency_label?: string | null;
    billing_company_id: number | null;
    responsible_user_id: number | null;
    requester_id: number | null;
    notes: string | null;
    internal_notes: string | null;
    notes_alert?: boolean;
    internal_notes_alert?: boolean;
    received_at: string | null;
    intervention_at: string | null;
    due_at: string | null;
    sent_at?: string | null;
    closed_at?: string | null;
    created_at?: string | null;
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
        quoted_at: string | null;
        quote_total_euros: string | number | null;
    }>;
    tasks?: Array<{
        id: number;
        title: string | null;
        description: string | null;
        is_completed: boolean;
    }>;
};

export type WorkOrderAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    is_private?: boolean;
    uploaded_by_name: string | null;
    download_url: string;
    view_url: string;
    is_image: boolean;
    created_at: string | null;
};
