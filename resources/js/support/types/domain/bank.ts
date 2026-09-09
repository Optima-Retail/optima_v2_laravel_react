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
