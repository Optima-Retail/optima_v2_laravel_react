import { useEffect } from 'react';
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
    const close = useConfirmStore((state) => state.close);
    const { t } = useTranslation();

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

                <div className="mt-5 flex flex-wrap items-center justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={() => close(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={tone === 'danger' ? 'danger' : 'primary'}
                        onClick={() => close(true)}
                    >
                        {confirmLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
