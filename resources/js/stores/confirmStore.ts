import { create } from 'zustand';
import i18n from '@/i18n';

export type ConfirmTone = 'danger' | 'primary';

export type ConfirmOptions = {
    title?: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    tone?: ConfirmTone;
    requireComment?: boolean;
    minCommentLength?: number;
    commentLabel?: string;
    commentPlaceholder?: string;
};

export type ConfirmResult = {
    confirmed: boolean;
    comment: string;
};

type NormalizedConfirmOptions = Required<
    Pick<
        ConfirmOptions,
        | 'title'
        | 'message'
        | 'confirmLabel'
        | 'cancelLabel'
        | 'tone'
        | 'requireComment'
        | 'minCommentLength'
        | 'commentLabel'
        | 'commentPlaceholder'
    >
>;

type ConfirmState = NormalizedConfirmOptions & {
    open: boolean;
    resolve: ((result: ConfirmResult) => void) | null;
    ask: (options: string | ConfirmOptions) => Promise<ConfirmResult>;
    close: (confirmed: boolean, comment?: string) => void;
};

function normalizeOptions(options: string | ConfirmOptions): NormalizedConfirmOptions {
    const confirmLabel = i18n.t('common.confirm');
    const cancelLabel = i18n.t('common.cancel');
    const title = i18n.t('confirm.title');
    const commentLabel = i18n.t('confirm.commentLabel');
    const commentPlaceholder = i18n.t('confirm.commentPlaceholder');

    if (typeof options === 'string') {
        return {
            title,
            message: options,
            confirmLabel,
            cancelLabel,
            tone: 'danger',
            requireComment: false,
            minCommentLength: 0,
            commentLabel,
            commentPlaceholder,
        };
    }

    return {
        title: options.title ?? title,
        message: options.message,
        confirmLabel: options.confirmLabel ?? confirmLabel,
        cancelLabel: options.cancelLabel ?? cancelLabel,
        tone: options.tone ?? 'danger',
        requireComment: Boolean(options.requireComment),
        minCommentLength: options.minCommentLength ?? (options.requireComment ? 10 : 0),
        commentLabel: options.commentLabel ?? commentLabel,
        commentPlaceholder: options.commentPlaceholder ?? commentPlaceholder,
    };
}

export const useConfirmStore = create<ConfirmState>((set, get) => ({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    tone: 'danger',
    requireComment: false,
    minCommentLength: 0,
    commentLabel: '',
    commentPlaceholder: '',
    resolve: null,
    ask: (options) =>
        new Promise<ConfirmResult>((resolve) => {
            const current = get().resolve;
            if (current) {
                current({ confirmed: false, comment: '' });
            }

            const normalized = normalizeOptions(options);

            set({
                open: true,
                ...normalized,
                resolve,
            });
        }),
    close: (confirmed, comment = '') => {
        const { resolve } = get();
        set({
            open: false,
            resolve: null,
        });
        resolve?.({
            confirmed,
            comment: confirmed ? comment : '',
        });
    },
}));
