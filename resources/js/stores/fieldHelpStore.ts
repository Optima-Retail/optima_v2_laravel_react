import { create } from 'zustand';
import { fieldHelpService, type FieldHelpContent, type FieldHelpMap } from '@/services/fieldHelp';

type FieldHelpState = {
    locale: string | null;
    entries: FieldHelpMap;
    /** Keys known to have no help for the current locale. */
    missing: Record<string, true>;
    pending: Record<string, true>;
    error: string | null;
    setLocale: (locale: string) => void;
    ensureKeys: (keys: string[]) => Promise<void>;
    get: (key: string) => FieldHelpContent | null;
    reset: () => void;
};

let flushTimer: ReturnType<typeof setTimeout> | null = null;
const queuedKeys = new Set<string>();
let inflight: Promise<void> = Promise.resolve();

function scheduleFlush(
    get: () => FieldHelpState,
    set: (partial: Partial<FieldHelpState> | ((state: FieldHelpState) => Partial<FieldHelpState>)) => void,
): Promise<void> {
    if (flushTimer) {
        return inflight;
    }

    inflight = new Promise((resolve) => {
        flushTimer = setTimeout(() => {
            flushTimer = null;
            void (async () => {
                const locale = get().locale;
                if (!locale || queuedKeys.size === 0) {
                    resolve();
                    return;
                }

                const keys = [...queuedKeys];
                queuedKeys.clear();

                set((state) => ({
                    pending: {
                        ...state.pending,
                        ...Object.fromEntries(keys.map((key) => [key, true as const])),
                    },
                    error: null,
                }));

                try {
                    const data = await fieldHelpService.resolve(keys, locale);
                    const missing: Record<string, true> = {};
                    for (const key of keys) {
                        if (!data[key]) {
                            missing[key] = true;
                        }
                    }

                    set((state) => {
                        const nextPending = { ...state.pending };
                        for (const key of keys) {
                            delete nextPending[key];
                        }

                        return {
                            entries: { ...state.entries, ...data },
                            missing: { ...state.missing, ...missing },
                            pending: nextPending,
                        };
                    });
                } catch (error) {
                    const missing: Record<string, true> = {};
                    for (const key of keys) {
                        missing[key] = true;
                    }

                    set((state) => {
                        const nextPending = { ...state.pending };
                        for (const key of keys) {
                            delete nextPending[key];
                        }

                        return {
                            missing: { ...state.missing, ...missing },
                            pending: nextPending,
                            error: error instanceof Error ? error.message : 'Field help failed',
                        };
                    });
                } finally {
                    resolve();
                }
            })();
        }, 16);
    });

    return inflight;
}

export const useFieldHelpStore = create<FieldHelpState>((set, get) => ({
    locale: null,
    entries: {},
    missing: {},
    pending: {},
    error: null,

    setLocale: (locale) => {
        if (get().locale === locale) {
            return;
        }

        queuedKeys.clear();
        if (flushTimer) {
            clearTimeout(flushTimer);
            flushTimer = null;
        }
        inflight = Promise.resolve();

        set({
            locale,
            entries: {},
            missing: {},
            pending: {},
            error: null,
        });
    },

    ensureKeys: async (keys) => {
        const { locale, entries, pending } = get();
        if (!locale) {
            return;
        }

        let added = false;
        for (const key of keys) {
            // Do not skip keys previously marked missing: editors may add help
            // after the first visit without a full locale reset.
            if (!key || entries[key] || pending[key] || queuedKeys.has(key)) {
                continue;
            }
            queuedKeys.add(key);
            added = true;
        }

        if (!added) {
            return;
        }

        await scheduleFlush(get, set);
    },

    get: (key) => get().entries[key] ?? null,

    reset: () => {
        queuedKeys.clear();
        if (flushTimer) {
            clearTimeout(flushTimer);
            flushTimer = null;
        }
        inflight = Promise.resolve();
        set({
            locale: null,
            entries: {},
            missing: {},
            pending: {},
            error: null,
        });
    },
}));
