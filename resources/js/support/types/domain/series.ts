export type SeriesListItem = {
    id: number;
    key: string;
    color: string;
    is_selectable: boolean;
    credit_note_series_id: number | null;
    credit_note_series_key: string | null;
    created_at: string | null;
};

export type SeriesFormData = {
    id: number;
    key: string;
    color: string;
    is_selectable: boolean;
    credit_note_series_id: number | null;
};

export type SeriesOption = {
    id: number;
    label: string;
};
