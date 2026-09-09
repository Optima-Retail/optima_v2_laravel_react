import {
    useEffect,
    useId,
    useLayoutEffect,
    useRef,
    useState,
    type CSSProperties,
    type KeyboardEvent,
    type MouseEvent,
} from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';
import { useFieldHelpStore } from '@/stores/fieldHelpStore';
import { RichTextHtml } from '@/components/ui/RichTextEditor';

type FieldHelpProps = {
    field: string;
    className?: string;
};

type TooltipCoords = {
    top: number;
    left: number;
};

const TOOLTIP_WIDTH = 288; // w-72
const VIEWPORT_GAP = 8;

function computeTooltipPosition(anchor: DOMRect): TooltipCoords {
    const preferredLeft = anchor.left + anchor.width / 2 - TOOLTIP_WIDTH / 2;
    const maxLeft = window.innerWidth - TOOLTIP_WIDTH - VIEWPORT_GAP;
    const left = Math.min(Math.max(VIEWPORT_GAP, preferredLeft), Math.max(VIEWPORT_GAP, maxLeft));
    const top = anchor.bottom + 6;

    return { top, left };
}

export function FieldHelp({ field, className }: FieldHelpProps) {
    const { t } = useTranslation();
    const tooltipId = useId();
    const rootRef = useRef<HTMLSpanElement>(null);
    const stickyOpen = useRef(false);
    const [open, setOpen] = useState(false);
    const [coords, setCoords] = useState<TooltipCoords | null>(null);

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

    useLayoutEffect(() => {
        if (!open || !rootRef.current) {
            setCoords(null);

            return;
        }

        const update = (): void => {
            if (!rootRef.current) {
                return;
            }

            setCoords(computeTooltipPosition(rootRef.current.getBoundingClientRect()));
        };

        update();
        window.addEventListener('resize', update);
        window.addEventListener('scroll', update, true);

        return () => {
            window.removeEventListener('resize', update);
            window.removeEventListener('scroll', update, true);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onPointerDown = (event: PointerEvent): void => {
            const target = event.target as Node;

            if (rootRef.current?.contains(target)) {
                return;
            }

            const tooltip = document.getElementById(tooltipId);

            if (tooltip?.contains(target)) {
                return;
            }

            stickyOpen.current = false;
            setOpen(false);
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
    }, [open, tooltipId]);

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

    const tooltipStyle: CSSProperties | undefined = coords
        ? {
              top: coords.top,
              left: coords.left,
              width: TOOLTIP_WIDTH,
          }
        : undefined;

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

            {open && coords
                ? createPortal(
                      <span
                          id={tooltipId}
                          role="tooltip"
                          style={tooltipStyle}
                          className="fixed z-[80] overflow-hidden rounded-xl border border-line bg-surface text-left shadow-lg"
                          onMouseEnter={show}
                          onMouseLeave={hideUnlessSticky}
                      >
                          {content.title ? (
                              <span className="block border-b border-line bg-canvas px-3 py-2 text-sm font-semibold text-ink">
                                  {content.title}
                              </span>
                          ) : null}
                          {content.description ? (
                              <span className="block px-3 py-2.5 text-sm leading-relaxed text-ink-muted">
                                  <RichTextHtml html={content.description} className="text-ink-muted" />
                              </span>
                          ) : null}
                      </span>,
                      document.body,
                  )
                : null}
        </span>
    );
}
