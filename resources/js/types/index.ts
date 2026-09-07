export type AuthCompany = {
    id: number;
    name: string;
};

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    locale: string;
    roles: string[];
    permissions: string[];
};

export type FlashMessages = {
    success?: string | null;
    error?: string | null;
};

export type SupportedLocale = {
    code: string;
    label: string;
};

export type SharedPageProps = {
    auth: {
        user: AuthUser | null;
        company: AuthCompany | null;
        companies: AuthCompany[];
    };
    flash: FlashMessages;
    locale: string;
    supportedLocales: SupportedLocale[];
    app: {
        name: string;
    };
};
