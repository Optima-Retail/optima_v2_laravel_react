import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

export type PageActionsMenuItem = {
    key: string;
    label: string;
    icon?: ReactNode;
    href?: string;
    onClick?: () => void;
    /** Open href in a new tab (e.g. PDF preview). */
    external?: boolean;
    disabled?: boolean;
};

type PageActionsMenuProps = {
    items: PageActionsMenuItem[];
    label?: string;
    className?: string;
};

/**
 * Compact header actions menu (same pattern as list “Actions” dropdowns).
 */
export function PageActionsMenu({ items, label, className }: PageActionsMenuProps) {
    const { t } = useTranslation();
    const menuRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const visibleItems = items.filter((item) => item.href || item.onClick);

    useEffect(() => {
        if (!open) {
            return;
        }

        function handlePointerDown(event: MouseEvent) {
            if (!menuRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handlePointerDown);

        return () => document.removeEventListener('mousedown', handlePointerDown);
    }, [open]);

    if (visibleItems.length === 0) {
        return null;
    }

    const itemClassName =
        'flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-ink transition-colors hover:bg-canvas disabled:cursor-not-allowed disabled:opacity-50';

    return (
        <div className={cn('relative', className)} ref={menuRef}>
            <button
                type="button"
                className="inline-flex h-8 items-center justify-center gap-1.5 rounded-lg border border-line bg-surface px-3 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((current) => !current)}
            >
                {label ?? t('common.actions')}
                <ChevronDown className="size-3.5" aria-hidden />
            </button>
            {open ? (
                <div
                    role="menu"
                    className="absolute right-0 z-20 mt-1 min-w-48 overflow-hidden rounded-lg border border-line bg-surface py-1 shadow-lg"
                >
                    {visibleItems.map((item) => {
                        if (item.href && item.external) {
                            return (
                                <a
                                    key={item.key}
                                    href={item.href}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    role="menuitem"
                                    className={itemClassName}
                                    onClick={() => setOpen(false)}
                                >
                                    {item.icon}
                                    {item.label}
                                </a>
                            );
                        }

                        if (item.href) {
                            return (
                                <Link
                                    key={item.key}
                                    href={item.href}
                                    role="menuitem"
                                    className={itemClassName}
                                    onClick={() => setOpen(false)}
                                >
                                    {item.icon}
                                    {item.label}
                                </Link>
                            );
                        }

                        return (
                            <button
                                key={item.key}
                                type="button"
                                role="menuitem"
                                disabled={item.disabled}
                                className={itemClassName}
                                onClick={() => {
                                    setOpen(false);
                                    item.onClick?.();
                                }}
                            >
                                {item.icon}
                                {item.label}
                            </button>
                        );
                    })}
                </div>
            ) : null}
        </div>
    );
}
