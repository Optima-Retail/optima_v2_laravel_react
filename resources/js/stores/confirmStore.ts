import { create } from 'zustand';
import i18n from '@/i18n';

export type ConfirmTone = 'danger' | 'primary';

export type ConfirmOptions = {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: ConfirmTone;
};

type ConfirmState = {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    tone: ConfirmTone;
    resolve: ((confirmed: boolean) => void) | null;
    ask: (options: string | ConfirmOptions) => Promise<boolean>;
    close: (confirmed: boolean) => void;
};

function normalizeOptions(options: string | ConfirmOptions): Required<ConfirmOptions> {
    const confirmLabel = i18n.t('common.confirm');
    const cancelLabel = i18n.t('common.cancel');
    const title = i18n.t('confirm.title');

    if (typeof options === 'string') {
        return {
            title,
            message: options,
            confirmLabel,
            cancelLabel,
            tone: 'danger',
        };
    }

    return {
        title: options.title ?? title,
        message: options.message,
        confirmLabel: options.confirmLabel ?? confirmLabel,
        cancelLabel: options.cancelLabel ?? cancelLabel,
        tone: options.tone ?? 'danger',
    };
}

export const useConfirmStore = create<ConfirmState>((set, get) => ({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    tone: 'danger',
    resolve: null,
    ask: (options) =>
        new Promise<boolean>((resolve) => {
            const current = get().resolve;
            if (current) {
                current(false);
            }

            const normalized = normalizeOptions(options);

            set({
                open: true,
                ...normalized,
                resolve,
            });
        }),
    close: (confirmed) => {
        const { resolve } = get();
        set({
            open: false,
            resolve: null,
        });
        resolve?.(confirmed);
    },
}));
