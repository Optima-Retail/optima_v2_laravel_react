export type UserOption = {
    id: number;
    label: string;
    /** Present for catalogs that store a display color (status, priority, type, …). */
    color?: string | null;
};
