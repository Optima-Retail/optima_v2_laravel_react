export type EstablishmentOption = {
    id: number;
    label: string;
    company_id: number;
    company_name?: string | null;
    company_logo_url?: string | null;
    brand_name?: string | null;
    currency_id?: number | null;
    currency_label?: string | null;
};

export type EstablishmentListItem = {
    id: number;
    name: string;
    code: string | null;
    city: string | null;
    company_name: string | null;
    delegation_name: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type EstablishmentFormData = {
    id: number;
    company_id: number;
    name: string;
    code: string | null;
    store_code: string | null;
    alternate_store_code: string | null;
    phone: string | null;
    email: string | null;
    emails: string | null;
    recipient_emails: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    province_id: number | null;
    province_name?: string | null;
    postal_code: string | null;
    country_id: number | null;
    timezone_id: number | null;
    language_id: number | null;
    establishment_type_id: number | null;
    delegation_id: number | null;
    series_id: number | null;
    billing_company_id: number | null;
    responsible_user_id: number | null;
    collaborator_ids: number[];
    blocked_technician_ids: number[];
    favorite_technician_ids: number[];
    form_template_links?: EstablishmentFormTemplateLinkItem[];
    is_active: boolean;
    is_client_priority: boolean | null;
    is_reviewed: boolean | null;
    is_email_reviewed: boolean | null;
    has_site_health_and_safety: boolean | null;
    has_customer_health_and_safety: boolean | null;
    is_quality_control_contactable: boolean | null;
    has_parking: boolean | null;
    is_ulez_zone: boolean | null;
    latitude: string | null;
    longitude: string | null;
    tax_rate: number | string | null;
    tax_included: boolean | null;
    integration_external_id: string | null;
    notes: string | null;
    notes_alert: boolean | null;
    internal_notes: string | null;
    internal_notes_alert: boolean | null;
    voicebot_time_slots: unknown[] | null;
};

export type EstablishmentAttachmentItem = {
    id: number;
    name: string;
    mime_type: string | null;
    size_bytes: number | null;
    is_private: boolean;
    uploaded_by_name: string | null;
    download_url: string;
    view_url: string;
    is_image: boolean;
    created_at: string | null;
};

export type EstablishmentFormTemplateLinkItem = {
    id: number;
    form_template_id: number;
    work_order_type_id: number;
};

export type EstablishmentFormTemplateLinkValues = {
    id: number | null;
    temp_key: string;
    form_template_id: string;
    work_order_type_id: string;
};
