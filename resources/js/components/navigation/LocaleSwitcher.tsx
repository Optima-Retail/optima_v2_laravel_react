import { useEffect, useId, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Check, ChevronDown } from 'lucide-react';
import { localeService } from '@/services';
import { cn } from '@/support/cn';
import type { SharedPageProps } from '@/types';
import flagEn from '../../../img/flags/en.svg';
import flagEs from '../../../img/flags/es.svg';

type LocaleSwitcherProps = {
    compact?: boolean;
};

const flagByLocale: Record<string, string> = {
    en: flagEn,
    es: flagEs,
};

function LocaleFlag({ code, className }: { code: string; className?: string }) {
    const { t } = useTranslation();
    const src = flagByLocale[code];

    if (!src) {
        return null;
    }

    return (
        <img
            src={src}
            alt=""
            aria-hidden
            title={t(`locale.${code}`)}
            className={cn('h-4 w-[1.333rem] shrink-0 object-contain', className)}
        />
    );
}

export function LocaleSwitcher({ compact = false }: LocaleSwitcherProps) {
    const { t } = useTranslation();
    const { locale, supportedLocales = [] } = usePage<SharedPageProps>().props;
    const [open, setOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);
    const listId = useId();
    const current = supportedLocales.find((item) => item.code === locale) ?? supportedLocales[0];

    useEffect(() => {
        function onPointerDown(event: MouseEvent) {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    function select(code: string) {
        setOpen(false);

        if (code === locale) {
            return;
        }

        localeService.update(code);
    }

    return (
        <div ref={rootRef} className="relative">
            <button
                type="button"
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-controls={listId}
                aria-label={t('locale.label')}
                onClick={() => setOpen((currentOpen) => !currentOpen)}
                className={cn(
                    'inline-flex h-8 items-center gap-1.5 rounded-lg border border-line bg-surface px-2.5 text-sm font-semibold text-ink transition-colors hover:border-brand/40 hover:text-brand',
                )}
            >
                {current ? <LocaleFlag code={current.code} /> : null}
                <span className={cn(compact && 'uppercase')}>
                    {compact ? current?.code : t(`locale.${current?.code}`)}
                </span>
                <ChevronDown className={cn('size-3.5 text-ink-muted transition-transform', open && 'rotate-180')} aria-hidden />
            </button>

            {open ? (
                <ul
                    id={listId}
                    role="listbox"
                    aria-label={t('locale.label')}
                    className="absolute right-0 top-full z-50 mt-1 min-w-40 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-lg"
                >
                    {supportedLocales.map((option) => {
                        const active = option.code === locale;

                        return (
                            <li key={option.code} role="option" aria-selected={active}>
                                <button
                                    type="button"
                                    onClick={() => select(option.code)}
                                    className={cn(
                                        'flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors',
                                        active ? 'bg-brand-soft text-brand' : 'text-ink hover:bg-canvas',
                                    )}
                                >
                                    <LocaleFlag code={option.code} />
                                    <span className="flex-1">{t(`locale.${option.code}`)}</span>
                                    {active ? <Check className="size-3.5 shrink-0" aria-hidden /> : null}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : null}
        </div>
    );
}
