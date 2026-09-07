import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react';
import { Check, ChevronDown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

export type SelectOption = {
    value: string;
    label: string;
};

type SearchableSelectProps = {
    id?: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    emptyLabel?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
};

export function SearchableSelect({
    id,
    options,
    value,
    onChange,
    placeholder,
    emptyLabel,
    invalid = false,
    disabled = false,
    className,
}: SearchableSelectProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const rootRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const listId = useId();
    const resolvedPlaceholder = placeholder ?? t('common.select');

    const selected = useMemo(
        () => options.find((option) => option.value === value) ?? null,
        [options, value],
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return options;
        }

        return options.filter((option) => option.label.toLowerCase().includes(needle));
    }, [options, query]);

    const items = useMemo(() => {
        if (!emptyLabel) {
            return filtered;
        }

        const emptyItem = { value: '', label: emptyLabel };
        const needle = query.trim().toLowerCase();

        if (needle && !emptyLabel.toLowerCase().includes(needle)) {
            return filtered;
        }

        return [emptyItem, ...filtered];
    }, [emptyLabel, filtered, query]);

    useEffect(() => {
        function onPointerDown(event: MouseEvent) {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        }

        document.addEventListener('mousedown', onPointerDown);
        return () => document.removeEventListener('mousedown', onPointerDown);
    }, []);

    useEffect(() => {
        setActiveIndex(0);
    }, [query, open]);

    function openList() {
        if (disabled) {
            return;
        }

        setOpen(true);
        setQuery('');
        inputRef.current?.focus();
    }

    function select(optionValue: string) {
        onChange(optionValue);
        setOpen(false);
        setQuery('');
    }

    function clear() {
        onChange('');
        setQuery('');
        inputRef.current?.focus();
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (!open && (event.key === 'ArrowDown' || event.key === 'Enter')) {
            event.preventDefault();
            openList();
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            setQuery('');
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((current) => Math.min(current + 1, Math.max(items.length - 1, 0)));
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((current) => Math.max(current - 1, 0));
            return;
        }

        if (event.key === 'Home') {
            event.preventDefault();
            setActiveIndex(0);
            return;
        }

        if (event.key === 'End') {
            event.preventDefault();
            setActiveIndex(Math.max(items.length - 1, 0));
            return;
        }

        if (event.key === 'Enter' && open && items[activeIndex]) {
            event.preventDefault();
            select(items[activeIndex].value);
        }
    }

    const activeOption = items[activeIndex];
    const displayValue = open ? query : (selected?.label ?? '');
    const canClear = Boolean(emptyLabel) && value !== '' && !disabled;

    return (
        <div ref={rootRef} className={cn('relative', className)}>
            <div
                className={cn(
                    'flex h-8 w-full items-center gap-1.5 rounded-lg border bg-surface px-2.5 text-sm shadow-sm transition',
                    'focus-within:border-brand focus-within:outline-none focus-within:ring-2 focus-within:ring-brand/20',
                    disabled && 'cursor-not-allowed opacity-60',
                    invalid ? 'border-danger focus-within:border-danger focus-within:ring-danger/20' : 'border-line',
                )}
                onClick={() => openList()}
            >
                <input
                    ref={inputRef}
                    id={id}
                    value={displayValue}
                    disabled={disabled}
                    placeholder={selected ? selected.label : resolvedPlaceholder}
                    aria-invalid={invalid}
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    aria-controls={listId}
                    aria-activedescendant={open && activeOption ? `${listId}-${activeOption.value || 'empty'}` : undefined}
                    role="combobox"
                    autoComplete="off"
                    className="min-w-0 flex-1 bg-transparent py-0.5 text-sm text-ink outline-none placeholder:text-ink-muted/70"
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => openList()}
                    onKeyDown={onKeyDown}
                />

                {canClear ? (
                    <button
                        type="button"
                        aria-label={t('common.remove', { label: selected?.label ?? value })}
                        className="rounded text-ink-muted transition-colors hover:text-ink"
                        onClick={(event) => {
                            event.stopPropagation();
                            clear();
                        }}
                    >
                        <X className="size-3.5" aria-hidden />
                    </button>
                ) : null}

                <ChevronDown
                    className={cn('size-4 shrink-0 text-ink-muted transition-transform', open && 'rotate-180')}
                    aria-hidden
                />
            </div>

            {open ? (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-xl border border-line bg-surface py-1 shadow-lg"
                >
                    {items.length === 0 ? (
                        <li className="px-3 py-2 text-sm text-ink-muted">{t('common.noResults')}</li>
                    ) : (
                        items.map((option, index) => {
                            const active = option.value === value;
                            const highlighted = index === activeIndex;

                            return (
                                <li
                                    key={option.value || '__empty'}
                                    id={`${listId}-${option.value || 'empty'}`}
                                    role="option"
                                    aria-selected={active}
                                >
                                    <button
                                        type="button"
                                        onMouseEnter={() => setActiveIndex(index)}
                                        onClick={() => select(option.value)}
                                        className={cn(
                                            'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors',
                                            highlighted ? 'bg-canvas' : '',
                                            active ? 'text-brand' : 'text-ink hover:bg-canvas',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'inline-flex size-4 items-center justify-center rounded border',
                                                active
                                                    ? 'border-brand bg-brand text-white'
                                                    : 'border-line bg-surface',
                                            )}
                                        >
                                            {active ? <Check className="size-3" aria-hidden /> : null}
                                        </span>
                                        {option.label}
                                    </button>
                                </li>
                            );
                        })
                    )}
                </ul>
            ) : null}
        </div>
    );
}
