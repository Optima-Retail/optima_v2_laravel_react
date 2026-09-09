export type RatingTypeListItem = {
    id: number;
    name: string;
    code: string;
    max_score: number;
    created_at: string | null;
};

export type RatingTypeFormData = {
    id: number;
    name: string;
    code: string;
    max_score: number;
};
