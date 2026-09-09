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
