export type NumberingSegmentFormData = {
    type: string;
    value?: string;
    digit_length?: number;
};

export type NumberingPatternListItem = {
    id: number;
    company_id: number;
    resource: string;
    segments: NumberingSegmentFormData[];
    reset_yearly: boolean;
    is_active: boolean;
    preview: string;
    created_at: string | null;
};

export type NumberingPatternFormData = {
    id: number | null;
    company_id: number;
    resource: string;
    segments: NumberingSegmentFormData[];
    reset_yearly: boolean;
    is_active: boolean;
    last_sequence: number;
    last_year: number | null;
    preview: string;
};
