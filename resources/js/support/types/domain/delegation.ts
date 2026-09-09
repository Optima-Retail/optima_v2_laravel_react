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
