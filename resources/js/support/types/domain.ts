export type UserListItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string | null;
};

export type UserFormData = {
    id: number;
    name: string;
    email: string;
    username: string | null;
    locale: string | null;
    manager_id: number | null;
    team_leader_id: number | null;
    team_id: number | null;
    timezone_id: number | null;
    brand_id: number | null;
    phone: string | null;
    telephony_phone_number: string | null;
    pbx_extension: string | null;
    telegram_user_id: string | null;
    external_hr_id: string | null;
    is_active: boolean;
    is_internal_employee: boolean;
    is_team_account: boolean;
    is_preventive_specialist: boolean | null;
    performance_factor: string;
    invoiced_revenue_target: string | null;
    quality_score: string;
    balance: string;
    budget_approval_limit: string;
    sso_only: boolean;
    must_change_password: boolean;
    roles: string[];
    company_ids: number[];
};

export type UserFormOptions = {
    users: UserOption[];
    teams: UserOption[];
    timezones: UserOption[];
    brands: UserOption[];
    companies: UserOption[];
};

export type PermissionGroup = {
    resource: string;
    permissions: string[];
};

export type RoleListItem = {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_system: boolean;
    permissions: string[];
};

export type RoleFormData = {
    id: number;
    name: string;
    permissions: string[];
    is_system: boolean;
};

export type WorkOrderTypeListItem = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
    created_at: string | null;
};

export type WorkOrderTypeFormData = {
    id: number;
    name: string;
    code: string | null;
    color: string | null;
};

export type TeamListItem = {
    id: number;
    code: string;
    name: string;
    manager_id: number | null;
    controller_id: number | null;
    created_at: string | null;
};

export type TeamFormData = {
    id: number;
    code: string;
    name: string;
    manager_id: number | null;
    controller_id: number | null;
};

export type LanguageListItem = {
    id: number;
    name: string;
    code: string;
    created_at: string | null;
};

export type LanguageFormData = {
    id: number;
    name: string;
    code: string;
};

export type IntegrationListItem = {
    id: number;
    name: string;
    code: string;
    created_at: string | null;
};

export type IntegrationFormData = {
    id: number;
    name: string;
    code: string;
};

export type RatingTypeListItem = {
    id: number;
    name: string;
    code: string;
    max_score: number;
    created_at: string | null;
};

export type RatingTypeFormData = {
    id: number;
    name: string;
    code: string;
    max_score: number;
};

export type FieldHelpListItem = {
    id: number;
    key: string;
    context: string | null;
    is_active: boolean;
    title: string | null;
    created_at: string | null;
};

export type FieldHelpFormData = {
    id: number;
    key: string;
    table: string | null;
    column: string | null;
    context: string | null;
    is_active: boolean;
    translations: Array<{ locale: string; title: string; description: string }>;
};

export type EstablishmentTypeListItem = {
    id: number;
    name: string;
    code: string;
    health_and_safety_delay_days: number;
    created_at: string | null;
};

export type EstablishmentTypeFormData = {
    id: number;
    name: string;
    code: string;
    health_and_safety_delay_days: number;
};

export type DelegationListItem = {
    id: number;
    name: string;
    tax_id: string | null;
    company_name: string | null;
    currency_code: string | null;
    country_name: string | null;
    series_key: string | null;
    created_at: string | null;
};

export type DelegationFormData = {
    id: number;
    name: string;
    tax_id: string | null;
    company_id: number | null;
    address: string | null;
    currency_id: number | null;
    country_id: number | null;
    series_id: number | null;
    cost_includes_vat: boolean;
    recovers_vat: boolean;
    billing_info: Record<string, unknown> | string | null;
};

export type BankListItem = {
    id: number;
    name: string;
    legal_name: string | null;
    country_id: number | null;
    country_name: string | null;
    swift_bic: string | null;
    national_bank_code: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type BankFormData = {
    id: number;
    name: string;
    legal_name: string | null;
    country_id: number | null;
    swift_bic: string | null;
    national_bank_code: string | null;
    lei: string | null;
    supervisor_code: string | null;
    website: string | null;
    is_active: boolean;
};

export type CountryOption = {
    id: number;
    label: string;
};

export type TimezoneListItem = {
    id: number;
    name: string;
    timezone: string;
    created_at: string | null;
};

export type TimezoneFormData = {
    id: number;
    name: string;
    timezone: string;
};

export type CountryListItem = {
    id: number;
    name: string;
    iso_code: string | null;
    timezone_id: number | null;
    timezone_name: string | null;
    provinces_count: number;
    created_at: string | null;
};

export type CountryFormData = {
    id: number;
    name: string;
    iso_code: string | null;
    timezone_id: number | null;
};

export type ProvinceListItem = {
    id: number;
    name: string;
    code: string | null;
    country_id: number;
    country_name: string | null;
    created_at: string | null;
};

export type ProvinceFormData = {
    id: number;
    name: string;
    code: string | null;
    country_id: number;
};

export type ProvinceOption = {
    id: number;
    label: string;
    country_id: number;
};

export type TimezoneOption = {
    id: number;
    label: string;
};

export type SeriesListItem = {
    id: number;
    key: string;
    color: string;
    is_selectable: boolean;
    credit_note_series_id: number | null;
    credit_note_series_key: string | null;
    created_at: string | null;
};

export type SeriesFormData = {
    id: number;
    key: string;
    color: string;
    is_selectable: boolean;
    credit_note_series_id: number | null;
};

export type SeriesOption = {
    id: number;
    label: string;
};

export type CurrencyListItem = {
    id: number;
    name: string;
    code: string;
    created_at: string | null;
};

export type CurrencyFormData = {
    id: number;
    name: string;
    code: string;
};

export type BrandListItem = {
    id: number;
    name: string;
    account_manager_id: number | null;
    account_manager_name: string | null;
    commercial_manager_id: number | null;
    commercial_manager_name: string | null;
    collaborator_ids: number[];
    loyalty_meeting_frequency: string | null;
    is_quality_control_contactable: boolean;
    send_debt_reminders: boolean;
    created_at: string | null;
};

export type BrandFormData = {
    id: number;
    name: string;
    account_manager_id: number | null;
    commercial_manager_id: number | null;
    collaborator_ids: number[];
    loyalty_meeting_frequency: string | null;
    is_quality_control_contactable: boolean;
    send_debt_reminders: boolean;
};

export type BrandMessageItem = {
    id: number;
    body: string;
    type: string;
    preview_url: string | null;
    download_name: string | null;
    user_id: number | null;
    user_name: string;
    is_mine: boolean;
    time: string;
    date: string;
    date_label: string;
    created_at: string | null;
};

export type UserOption = {
    id: number;
    label: string;
};

export type CompanyListItem = {
    id: number;
    name: string;
    tradename: string | null;
    tax_id: string | null;
    kind: string;
    country_name: string | null;
    is_active: boolean;
    created_at: string | null;
};

export type CompanyFormData = {
    id: number;
    name: string;
    tradename: string | null;
    slug: string;
    tax_id: string | null;
    kind: string;
    country_id: number | null;
    residence_country_id: number | null;
    person_type: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    address_line_1: string | null;
    address_line_2: string | null;
    city: string | null;
    province_id: number | null;
    province_name?: string | null;
    postal_code: string | null;
    employee_count: number | null;
    is_active: boolean;
    brand_id: number | null;
    language_id: number | null;
    latitude: string | null;
    longitude: string | null;
    legacy_erp_id: number | null;
};

export type CompanyRelationshipListItem = {
    id: number;
    related_company_id: number;
    related_company_name: string | null;
    kind: string;
    status: string;
    classification: string;
    brand_name: string | null;
    owner_reference: string | null;
    created_at: string | null;
};

export type CompanyRelationshipFormData = {
    id: number;
    owner_company_id: number;
    related_company_id: number;
    related_company_name: string | null;
    kind: string;
    status: string;
    classification: string;
    owner_reference: string | null;
    related_reference: string | null;
    brand_id: number | null;
    external_code: string | null;
    notes: string | null;
    starts_at: string | null;
    ends_at: string | null;
    delegation_id: number | null;
    billing_language_id: number | null;
    series_id: number | null;
    rating_type_id: number | null;
    integration_id: number | null;
    integration_external_id: string | null;
    corrective_work_order_owner_id: number | null;
    preventive_work_order_owner_id: number | null;
    quality_owner_id: number | null;
    account_owner_id: number | null;
    commercial_owner_id: number | null;
    sourced_by_user_id: number | null;
    internal_notes: string | null;
    notes_alert: boolean | null;
    internal_notes_alert: boolean | null;
    onboarding_notes: string | null;
    billing_comments: string | null;
    rates_notes: string | null;
    archetype: string | null;
    tax_rate: number | string | null;
    is_reviewed: boolean | null;
    is_email_reviewed: boolean | null;
    is_invoicing_reviewed: boolean | null;
    invoicing_reviewed_at: string | null;
    quote_close_days: number | null;
    recurring_meeting_frequency: number | null;
    sales_feedback_meeting_frequency: number | null;
    group_zero_cost_work_orders: boolean | null;
    load_materials_on_corrective: boolean | null;
    group_preventive_and_corrective: boolean | null;
    group_preventives_by: string | null;
    group_correctives_by: string | null;
    invoice_at_month_end: boolean | null;
    requires_purchase_order: boolean | null;
    requires_requester: boolean | null;
    is_franchise: boolean | null;
    requires_justification: boolean | null;
    auto_send_invoices: boolean | null;
    send_invoices_individually: boolean | null;
    send_debt_reminders: boolean | null;
    is_quality_control_contactable: boolean | null;
    requires_client_informed_check: boolean | null;
    requires_intervention_scheduled_check: boolean | null;
    requires_budget_approval_limit: boolean | null;
    is_intercompany: boolean | null;
    optima_score: number | string | null;
    customer_score: number | string | null;
    average_score: number | string | null;
    optima_score_count: number | null;
    customer_score_count: number | null;
    has_health_and_safety: boolean | null;
    is_field_technician: boolean | null;
    is_creditor: boolean | null;
    is_vip: boolean | null;
    is_available_24h: boolean | null;
    day_start_at: string | null;
    day_end_at: string | null;
    has_garnishment: boolean | null;
    whatsapp_messaging_authorized: boolean | null;
    registered_at: string | null;
    legacy_status_id: number | null;
};

export type RelationshipFormOptions = {
    brandOptions: UserOption[];
    delegationOptions: UserOption[];
    languageOptions: UserOption[];
    seriesOptions: UserOption[];
    ratingTypeOptions: UserOption[];
    integrationOptions: UserOption[];
    userOptions: UserOption[];
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
    legacy_erp_id: number | null;
    integration_external_id: string | null;
    notes: string | null;
    notes_alert: boolean | null;
    internal_notes: string | null;
    internal_notes_alert: boolean | null;
    voicebot_time_slots: unknown[] | null;
};
