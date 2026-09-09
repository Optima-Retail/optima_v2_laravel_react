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

export type TimezoneOption = {
    id: number;
    label: string;
};
