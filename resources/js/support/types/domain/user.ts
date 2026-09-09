import type { UserOption } from './common';

export type UserListItem = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_active: boolean;
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
