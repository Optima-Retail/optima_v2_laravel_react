export type ContractListItem = {
    id: number;
    code: string | null;
    description: string | null;
    company_name: string | null;
    brand_name: string | null;
    status_name: string | null;
    status_color: string | null;
    signed_at: string | null;
    total_amount: string | number | null;
    created_at: string | null;
};

export type ContractIterationFormValues = {
    id: number | null;
    temp_key: string | null;
    subject: string;
    work_order_type_id: string;
    starts_on: string;
    ends_on: string;
    periodicity: 'weekly' | 'monthly';
    periodicity_kind: 'basic' | 'complex';
    interval: string;
    weekdays: string[];
    month_days: string[];
    months: string[];
    cost_amount: string;
    establishment_ids: string[];
    form_template_id: string;
    invoicing_aggregation_id: string;
    invoicing_aggregation_temp_key: string;
};

export type ContractInvoicingAggregationFormValues = {
    id: number | null;
    temp_key: string | null;
    subject: string;
    billing_frequency: 'monthly' | 'bimonthly' | 'quarterly' | 'annually' | 'biannually';
    billing_day: string;
    billing_cycle_start: string;
    per_establishment: boolean;
};

export type ContractFormData = {
    id: number;
    code: string | null;
    company_id: number | null;
    responsible_user_id: number | null;
    contract_status_id: number | null;
    language_id: number | null;
    description: string | null;
    work_order_subject: string | null;
    signed_at: string | null;
    canceled_at: string | null;
    establishment_ids: number[];
    iterations: Array<{
        id: number;
        temp_key: string | null;
        subject: string | null;
        work_order_type_id: number;
        starts_on: string | null;
        ends_on: string | null;
        periodicity: string | null;
        periodicity_kind: string | null;
        interval: number | null;
        weekdays: number[];
        month_days: number[];
        months: number[];
        cost_amount: string | number | null;
        establishment_ids: number[];
        form_template_id: number | null;
        invoicing_aggregation_id: number | null;
        invoicing_aggregation_temp_key: string | null;
    }>;
    invoicing_aggregations: Array<{
        id: number;
        temp_key: string | null;
        subject: string | null;
        billing_frequency: string;
        billing_day: number | null;
        billing_cycle_start: string | null;
        per_establishment: boolean;
    }>;
};

export type ContractAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    uploaded_by_name: string | null;
    download_url: string;
    created_at: string | null;
};
