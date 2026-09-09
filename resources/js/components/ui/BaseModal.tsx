import { useEffect, useId, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

type BaseModalProps = {
    open: boolean;
    title: ReactNode;
    description?: ReactNode;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    /** Disable overlay/Escape close (e.g. while saving). */
    closeDisabled?: boolean;
    size?: 'md' | 'lg' | 'xl';
    className?: string;
};

const sizeClassName: Record<NonNullable<BaseModalProps['size']>, string> = {
    md: 'max-w-md',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

export function BaseModal({
    open,
    title,
    description,
    onClose,
    children,
    footer,
    closeDisabled = false,
    size = 'lg',
    className,
}: BaseModalProps) {
    const { t } = useTranslation();
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => {
        if (!open) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape' && !closeDisabled) {
                onClose();
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [closeDisabled, onClose, open]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                className="absolute inset-0 bg-ink/40"
                aria-label={t('common.close')}
                disabled={closeDisabled}
                onClick={() => {
                    if (!closeDisabled) {
                        onClose();
                    }
                }}
            />

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={description ? descriptionId : undefined}
                className={cn(
                    'relative flex max-h-[min(90vh,40rem)] w-full flex-col rounded-2xl border border-line bg-surface shadow-lg',
                    sizeClassName[size],
                    className,
                )}
            >
                <div className="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div className="min-w-0 space-y-1">
                        <h2 id={titleId} className="font-display text-lg font-semibold text-ink">
                            {title}
                        </h2>
                        {description ? (
                            <p id={descriptionId} className="text-sm text-ink-muted">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        disabled={closeDisabled}
                        onClick={onClose}
                        className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink disabled:opacity-50"
                        aria-label={t('common.close')}
                    >
                        <X className="size-4" aria-hidden />
                    </button>
                </div>

                <div className="app-scroll min-h-0 flex-1 overflow-y-auto px-5 py-4">{children}</div>

                {footer ? (
                    <div className="flex flex-wrap items-center justify-end gap-2 border-t border-line px-5 py-4">
                        {footer}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
