export type FieldHelpListItem = {
    id: number;
    key: string;
    context: string | null;
    is_active: boolean;
    title: string | null;
    created_at: string | null;
};

export type FieldHelpFormData = {
    id: number;
    key: string;
    table: string | null;
    column: string | null;
    context: string | null;
    is_active: boolean;
    translations: Array<{ locale: string; title: string; description: string }>;
};
