export type ArticleTranslationRow = {
    language_id: number | string;
    name: string;
    description: string;
};

export type ArticleClientRow = {
    company_relationship_id: number | string;
    sale_price: string;
};

export type ArticleListItem = {
    id: number;
    code: string;
    name: string | null;
    is_deletable: boolean;
    created_at: string | null;
};

export type ArticleFormData = {
    id: number;
    code: string;
    is_deletable: boolean;
    translations: ArticleTranslationRow[];
    clients: ArticleClientRow[];
};
