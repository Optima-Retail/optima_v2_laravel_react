export type UserOption = {
    id: number;
    label: string;
    /** Present for catalogs that store a display color (status, priority, type, …). */
    color?: string | null;
    /** When false, option is inactive (kept only to display a saved value). */
    is_open?: boolean;
};

/** Articles for estimate/work-order billing lines (scoped via article_clients). */
export type WorkOrderArticleOption = UserOption & {
    code?: string;
    description?: string | null;
    unit_price?: string | null;
};

/** Option shape for company pickers (SearchableSelect / MultiSelect). */
export type CompanyOption = UserOption & {
    logo_url?: string | null;
};
