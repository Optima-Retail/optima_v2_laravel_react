import { useEffect, useId, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Building2, Check, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { companiesService } from '@/services';
import { cn } from '@/support/cn';
import type { SharedPageProps } from '@/types';

export function CompanySwitcher() {
    const { t } = useTranslation();
    const { auth } = usePage<SharedPageProps>().props;
    const companies = auth.companies ?? [];
    const active = auth.company;
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);
    const listId = useId();

    useEffect(() => {
        function onPointerDown(event: MouseEvent) {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    if (companies.length === 0) {
        return null;
    }

    function select(id: number) {
        setOpen(false);

        if (id === active?.id) {
            return;
        }

        companiesService.switchTo(id);
    }

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listId}
                aria-label={t('companies.switcher')}
                onClick={() => setOpen((currentOpen) => !currentOpen)}
                className="inline-flex h-8 max-w-48 items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 text-sm font-semibold text-ink transition-colors hover:border-brand/40 hover:text-brand"
            >
                <Building2 className="size-3.5 shrink-0 text-ink-muted" aria-hidden />
                <span className="truncate">{active?.name ?? t('companies.switcher')}</span>
                <ChevronDown className={cn('size-3.5 shrink-0 text-ink-muted transition-transform', open && 'rotate-180')} aria-hidden />
            </button>

            {open ? (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label={t('companies.switcher')}
                    className="absolute right-0 top-full z-50 mt-1 min-w-48 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-lg"
                >
                    {companies.map((company) => {
                        const selected = company.id === active?.id;

                        return (
                            <li key={company.id} role="option" aria-selected={selected}>
                                <button
                                    type="button"
                                    onClick={() => select(company.id)}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors',
                                        selected ? 'bg-brand-soft text-brand' : 'text-ink hover:bg-canvas',
                                    )}
                                >
                                    <span className="flex-1 truncate">{company.name}</span>
                                    {selected ? <Check className="size-3.5 shrink-0" aria-hidden /> : null}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
        </div>
    );
}
