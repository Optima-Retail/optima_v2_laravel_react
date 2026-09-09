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
