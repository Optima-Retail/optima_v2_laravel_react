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
