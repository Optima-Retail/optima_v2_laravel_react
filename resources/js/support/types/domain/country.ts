export type CountryOption = {
    id: number;
    label: string;
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
