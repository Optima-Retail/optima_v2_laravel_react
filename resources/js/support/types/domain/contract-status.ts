export type ContractStatusListItem = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
    created_at: string | null;
};

export type ContractStatusFormData = {
    id: number;
    name: string;
    color: string | null;
    lifecycle: number | null;
    is_open: boolean;
};
