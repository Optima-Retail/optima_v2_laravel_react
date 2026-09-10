export type UserOption = {
    id: number;
    label: string;
    /** Present for catalogs that store a display color (status, priority, type, …). */
    color?: string | null;
    /** When false, option is inactive (kept only to display a saved value). */
    is_open?: boolean;
};
