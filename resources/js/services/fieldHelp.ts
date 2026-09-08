export type FieldHelpContent = {
    title: string | null;
    description: string | null;
};

export type FieldHelpMap = Record<string, FieldHelpContent>;

type ResolveResponse = {
    data: FieldHelpMap;
    locale: string;
};

export const fieldHelpService = {
    resolvePath: '/field-help',

    async resolve(keys: string[], locale?: string): Promise<FieldHelpMap> {
        const uniqueKeys = [...new Set(keys.filter(Boolean))];
        if (uniqueKeys.length === 0) {
            return {};
        }

        const params = new URLSearchParams();
        for (const key of uniqueKeys) {
            params.append('keys[]', key);
        }
        if (locale) {
            params.set('locale', locale);
        }

        const response = await fetch(`${this.resolvePath}?${params.toString()}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Field help request failed (${response.status})`);
        }

        const payload = (await response.json()) as ResolveResponse;

        return payload.data ?? {};
    },
};
