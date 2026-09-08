import { useEffect, useId, useRef, useState, type KeyboardEvent, type MouseEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';
import { useFieldHelpStore } from '@/stores/fieldHelpStore';

type FieldHelpProps = {
    field: string;
    className?: string;
};

export function FieldHelp({ field, className }: FieldHelpProps) {
    const { t } = useTranslation();
    const tooltipId = useId();
    const rootRef = useRef<HTMLSpanElement>(null);
    const stickyOpen = useRef(false);
    const [open, setOpen] = useState(false);

    const locale = useFieldHelpStore((state) => state.locale);
    const content = useFieldHelpStore((state) => state.entries[field] ?? null);
    const ensureKeys = useFieldHelpStore((state) => state.ensureKeys);

    useEffect(() => {
        // Provider sets locale in an effect; wait for it or the first ensureKeys is a no-op.
        if (!locale) {
            return;
        }

        void ensureKeys([field]);
    }, [ensureKeys, field, locale]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent): void => {
            if (!rootRef.current?.contains(event.target as Node)) {
                stickyOpen.current = false;
                setOpen(false);
            }
        };

        const onKeyDown = (event: globalThis.KeyboardEvent): void => {
            if (event.key === 'Escape') {
                stickyOpen.current = false;
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (!content || (!content.title && !content.description)) {
        return null;
    }

    const label = content.title ?? t('fieldHelp.help');

    const show = (): void => setOpen(true);
    const hideUnlessSticky = (): void => {
        if (!stickyOpen.current) {
            setOpen(false);
        }
    };

    const onClick = (event: MouseEvent<HTMLButtonElement>): void => {
        event.preventDefault();
        event.stopPropagation();
        stickyOpen.current = true;
        setOpen(true);
    };

    const onButtonKeyDown = (event: KeyboardEvent<HTMLButtonElement>): void => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            event.stopPropagation();
            stickyOpen.current = true;
            setOpen(true);
        }
    };

    return (
        <span
            ref={rootRef}
            className={cn('relative ml-1.5 inline-flex items-center self-center', className)}
            onMouseEnter={show}
            onMouseLeave={hideUnlessSticky}
        >
            <button
                type="button"
                className="inline-flex size-4 shrink-0 items-center justify-center rounded-full bg-[#c5ced8] text-[10px] font-bold leading-none text-white transition hover:bg-[#b4bfcb] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-line"
                aria-label={t('fieldHelp.helpFor', { field: label })}
                aria-expanded={open}
                aria-controls={tooltipId}
                onClick={onClick}
                onFocus={show}
                onBlur={(event) => {
                    if (!rootRef.current?.contains(event.relatedTarget as Node) && !stickyOpen.current) {
                        setOpen(false);
                    }
                }}
                onKeyDown={onButtonKeyDown}
            >
                <span aria-hidden>!</span>
            </button>

            {open ? (
                <span
                    id={tooltipId}
                    role="tooltip"
                    className="absolute left-1/2 top-full z-40 mt-1.5 w-72 -translate-x-1/2 overflow-hidden rounded-xl border border-line bg-surface text-left shadow-lg"
                >
                    {content.title ? (
                        <span className="block border-b border-line bg-canvas px-3 py-2 text-sm font-semibold text-ink">
                            {content.title}
                        </span>
                    ) : null}
                    {content.description ? (
                        <span className="block px-3 py-2.5 text-sm leading-relaxed text-ink-muted">
                            {content.description}
                        </span>
                    ) : null}
                </span>
            ) : null}
        </span>
    );
}
