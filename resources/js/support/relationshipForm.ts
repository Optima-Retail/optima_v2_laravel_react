import type { CompanyRelationshipFormData } from '@/support/types/domain/company-relationship';

export type RelationshipProfileValues = {
    delegation_id: string;
    billing_language_id: string;
    series_id: string;
    priority_ids: string[];
    collaborator_ids: string[];
    service_type_ids: string[];
    global_service_type_ids: string[];
    alternative_delegation_ids: string[];
    integration_id: string;
    integration_external_id: string;
    corrective_work_order_owner_id: string;
    preventive_work_order_owner_id: string;
    quality_owner_id: string;
    account_owner_id: string;
    commercial_owner_id: string;
    sourced_by_user_id: string;
    internal_notes: string;
    notes_alert: boolean;
    internal_notes_alert: boolean;
    onboarding_notes: string;
    billing_comments: string;
    rates_notes: string;
    archetype: string;
    tax_rate: string;
    is_reviewed: boolean;
    is_email_reviewed: boolean;
    is_invoicing_reviewed: boolean;
    invoicing_reviewed_at: string;
    quote_close_days: string;
    recurring_meeting_frequency: string;
    sales_feedback_meeting_frequency: string;
    group_zero_cost_work_orders: boolean;
    load_materials_on_corrective: boolean;
    group_preventive_and_corrective: boolean;
    group_preventives_by: string;
    group_correctives_by: string;
    invoice_at_month_end: boolean;
    requires_purchase_order: boolean;
    requires_requester: boolean;
    is_franchise: boolean;
    requires_justification: boolean;
    auto_send_invoices: boolean;
    send_invoices_individually: boolean;
    send_debt_reminders: boolean;
    is_quality_control_contactable: boolean;
    requires_client_informed_check: boolean;
    requires_intervention_scheduled_check: boolean;
    requires_budget_approval_limit: boolean;
    is_intercompany: boolean;
    optima_score: string;
    customer_score: string;
    average_score: string;
    optima_score_count: string;
    customer_score_count: string;
    has_health_and_safety: boolean;
    is_field_technician: boolean;
    is_creditor: boolean;
    is_vip: boolean;
    is_available_24h: boolean;
    day_start_at: string;
    day_end_at: string;
    has_garnishment: boolean;
    whatsapp_messaging_authorized: boolean;
    registered_at: string;
    legacy_status_id: string;
};

export const emptyRelatedCompany = {
    name: '',
    tradename: '',
    tax_id: '',
    email: '',
    phone: '',
};

export const defaultRelationshipProfileValues = (): RelationshipProfileValues => ({
    delegation_id: '',
    billing_language_id: '',
    series_id: '',
    priority_ids: [],
    collaborator_ids: [],
    service_type_ids: [],
    global_service_type_ids: [],
    alternative_delegation_ids: [],
    integration_id: '',
    integration_external_id: '',
    corrective_work_order_owner_id: '',
    preventive_work_order_owner_id: '',
    quality_owner_id: '',
    account_owner_id: '',
    commercial_owner_id: '',
    sourced_by_user_id: '',
    internal_notes: '',
    notes_alert: false,
    internal_notes_alert: false,
    onboarding_notes: '',
    billing_comments: '',
    rates_notes: '',
    archetype: '',
    tax_rate: '',
    is_reviewed: false,
    is_email_reviewed: false,
    is_invoicing_reviewed: false,
    invoicing_reviewed_at: '',
    quote_close_days: '',
    recurring_meeting_frequency: '',
    sales_feedback_meeting_frequency: '',
    group_zero_cost_work_orders: false,
    load_materials_on_corrective: false,
    group_preventive_and_corrective: false,
    group_preventives_by: '',
    group_correctives_by: '',
    invoice_at_month_end: false,
    requires_purchase_order: false,
    requires_requester: false,
    is_franchise: false,
    requires_justification: false,
    auto_send_invoices: false,
    send_invoices_individually: false,
    send_debt_reminders: false,
    is_quality_control_contactable: false,
    requires_client_informed_check: false,
    requires_intervention_scheduled_check: false,
    requires_budget_approval_limit: false,
    is_intercompany: false,
    optima_score: '',
    customer_score: '',
    average_score: '',
    optima_score_count: '',
    customer_score_count: '',
    has_health_and_safety: false,
    is_field_technician: false,
    is_creditor: false,
    is_vip: false,
    is_available_24h: false,
    day_start_at: '',
    day_end_at: '',
    has_garnishment: false,
    whatsapp_messaging_authorized: false,
    registered_at: '',
    legacy_status_id: '',
});

function id(value: number | null | undefined): string {
    return value ? String(value) : '';
}

function num(value: number | string | null | undefined): string {
    return value === null || value === undefined || value === '' ? '' : String(value);
}

function bool(value: boolean | null | undefined): boolean {
    return Boolean(value);
}

export function relationshipFormValuesFromData(
    relationship: CompanyRelationshipFormData,
    relatedMode: 'existing' | 'new' = 'existing',
) {
    return {
        related_mode: relatedMode,
        related_company_id: String(relationship.related_company_id),
        related_company: emptyRelatedCompany,
        kind: relationship.kind,
        status: relationship.status,
        classification: relationship.classification,
        owner_reference: relationship.owner_reference ?? '',
        related_reference: relationship.related_reference ?? '',
        brand_id: id(relationship.brand_id),
        external_code: relationship.external_code ?? '',
        notes: relationship.notes ?? '',
        starts_at: relationship.starts_at ?? '',
        ends_at: relationship.ends_at ?? '',
        delegation_id: id(relationship.delegation_id),
        billing_language_id: id(relationship.billing_language_id),
        series_id: id(relationship.series_id),
        priority_ids: (relationship.priority_ids ?? []).map(String),
        collaborator_ids: (relationship.collaborator_ids ?? []).map(String),
        service_type_ids: (relationship.service_type_ids ?? []).map(String),
        global_service_type_ids: (relationship.global_service_type_ids ?? []).map(String),
        alternative_delegation_ids: (relationship.alternative_delegation_ids ?? []).map(String),
        integration_id: id(relationship.integration_id),
        integration_external_id: relationship.integration_external_id ?? '',
        corrective_work_order_owner_id: id(relationship.corrective_work_order_owner_id),
        preventive_work_order_owner_id: id(relationship.preventive_work_order_owner_id),
        quality_owner_id: id(relationship.quality_owner_id),
        account_owner_id: id(relationship.account_owner_id),
        commercial_owner_id: id(relationship.commercial_owner_id),
        sourced_by_user_id: id(relationship.sourced_by_user_id),
        internal_notes: relationship.internal_notes ?? '',
        notes_alert: bool(relationship.notes_alert),
        internal_notes_alert: bool(relationship.internal_notes_alert),
        onboarding_notes: relationship.onboarding_notes ?? '',
        billing_comments: relationship.billing_comments ?? '',
        rates_notes: relationship.rates_notes ?? '',
        archetype: relationship.archetype ?? '',
        tax_rate: num(relationship.tax_rate),
        is_reviewed: bool(relationship.is_reviewed),
        is_email_reviewed: bool(relationship.is_email_reviewed),
        is_invoicing_reviewed: bool(relationship.is_invoicing_reviewed),
        invoicing_reviewed_at: relationship.invoicing_reviewed_at ?? '',
        quote_close_days: num(relationship.quote_close_days),
        recurring_meeting_frequency: num(relationship.recurring_meeting_frequency),
        sales_feedback_meeting_frequency: num(relationship.sales_feedback_meeting_frequency),
        group_zero_cost_work_orders: bool(relationship.group_zero_cost_work_orders),
        load_materials_on_corrective: bool(relationship.load_materials_on_corrective),
        group_preventive_and_corrective: bool(relationship.group_preventive_and_corrective),
        group_preventives_by: relationship.group_preventives_by ?? '',
        group_correctives_by: relationship.group_correctives_by ?? '',
        invoice_at_month_end: bool(relationship.invoice_at_month_end),
        requires_purchase_order: bool(relationship.requires_purchase_order),
        requires_requester: bool(relationship.requires_requester),
        is_franchise: bool(relationship.is_franchise),
        requires_justification: bool(relationship.requires_justification),
        auto_send_invoices: bool(relationship.auto_send_invoices),
        send_invoices_individually: bool(relationship.send_invoices_individually),
        send_debt_reminders: bool(relationship.send_debt_reminders),
        is_quality_control_contactable: bool(relationship.is_quality_control_contactable),
        requires_client_informed_check: bool(relationship.requires_client_informed_check),
        requires_intervention_scheduled_check: bool(relationship.requires_intervention_scheduled_check),
        requires_budget_approval_limit: bool(relationship.requires_budget_approval_limit),
        is_intercompany: bool(relationship.is_intercompany),
        optima_score: num(relationship.optima_score),
        customer_score: num(relationship.customer_score),
        average_score: num(relationship.average_score),
        optima_score_count: num(relationship.optima_score_count),
        customer_score_count: num(relationship.customer_score_count),
        has_health_and_safety: bool(relationship.has_health_and_safety),
        is_field_technician: bool(relationship.is_field_technician),
        is_creditor: bool(relationship.is_creditor),
        is_vip: bool(relationship.is_vip),
        is_available_24h: bool(relationship.is_available_24h),
        day_start_at: relationship.day_start_at ?? '',
        day_end_at: relationship.day_end_at ?? '',
        has_garnishment: bool(relationship.has_garnishment),
        whatsapp_messaging_authorized: bool(relationship.whatsapp_messaging_authorized),
        registered_at: relationship.registered_at ?? '',
        legacy_status_id: num(relationship.legacy_status_id),
    };
}
