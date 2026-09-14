import { useEffect, useState } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { useConfirmStore } from '@/stores/confirmStore';

export function ConfirmModal() {
    const open = useConfirmStore((state) => state.open);
    const title = useConfirmStore((state) => state.title);
    const message = useConfirmStore((state) => state.message);
    const confirmLabel = useConfirmStore((state) => state.confirmLabel);
    const cancelLabel = useConfirmStore((state) => state.cancelLabel);
    const tone = useConfirmStore((state) => state.tone);
    const requireComment = useConfirmStore((state) => state.requireComment);
    const minCommentLength = useConfirmStore((state) => state.minCommentLength);
    const commentLabel = useConfirmStore((state) => state.commentLabel);
    const commentPlaceholder = useConfirmStore((state) => state.commentPlaceholder);
    const close = useConfirmStore((state) => state.close);
    const { t } = useTranslation();
    const [comment, setComment] = useState('');

    useEffect(() => {
        if (open) {
            setComment('');
        }
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                close(false);
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open, close]);

    if (!open) {
        return null;
    }

    const trimmed = comment.trim();
    const commentOk = !requireComment || trimmed.length >= minCommentLength;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                className="absolute inset-0 bg-ink/40"
                aria-label={t('confirm.closeDialog')}
                onClick={() => close(false)}
            />

            <div
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirm-title"
                aria-describedby="confirm-message"
                className="relative w-full max-w-md rounded-2xl border border-line bg-surface p-5 shadow-lg"
            >
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <h2 id="confirm-title" className="font-display text-lg font-semibold text-ink">
                            {title}
                        </h2>
                        <p id="confirm-message" className="text-sm text-ink-muted">
                            {message}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => close(false)}
                        className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                        aria-label={t('common.close')}
                    >
                        <X className="size-4" aria-hidden />
                    </button>
                </div>

                {requireComment ? (
                    <div className="mt-4 space-y-1.5">
                        <label htmlFor="confirm-comment" className="text-sm font-medium text-ink">
                            {commentLabel}
                        </label>
                        <textarea
                            id="confirm-comment"
                            rows={3}
                            value={comment}
                            onChange={(event) => setComment(event.target.value)}
                            placeholder={commentPlaceholder}
                            className="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink shadow-sm transition focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                        />
                        <p className="text-xs text-ink-muted">
                            {t('confirm.commentMin', { count: minCommentLength })}
                        </p>
                    </div>
                ) : null}

                <div className="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={() => close(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={tone === 'danger' ? 'danger' : 'primary'}
                        disabled={!commentOk}
                        onClick={() => close(true, trimmed)}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
