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
