import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState, type CSSProperties, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';
import { optionColorStyle } from '@/support/color';

export type SelectOption = {
    value: string;
    label: string;
    /** Catalog color (statuses, priorities, types, …) — paints the option row. */
    color?: string | null;
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

const LIST_MAX_HEIGHT = 224;

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
    const [menuStyle, setMenuStyle] = useState<CSSProperties>({});
    const rootRef = useRef<HTMLDivElement>(null);
    const listRef = useRef<HTMLUListElement>(null);
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

        const emptyItem: SelectOption = { value: '', label: emptyLabel };
        const needle = query.trim().toLowerCase();

        if (needle && !emptyLabel.toLowerCase().includes(needle)) {
            return filtered;
        }

        return [emptyItem, ...filtered];
    }, [emptyLabel, filtered, query]);

    function updateMenuPosition() {
        const rect = rootRef.current?.getBoundingClientRect();
        if (!rect) {
            return;
        }

        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        const openUpward = spaceBelow < LIST_MAX_HEIGHT && spaceAbove > spaceBelow;
        const maxHeight = Math.min(
            LIST_MAX_HEIGHT,
            Math.max(openUpward ? spaceAbove - 8 : spaceBelow - 8, 96),
        );

        setMenuStyle({
            position: 'fixed',
            left: rect.left,
            width: rect.width,
            zIndex: 100,
            maxHeight,
            ...(openUpward
                ? { bottom: window.innerHeight - rect.top + 4, top: 'auto' }
                : { top: rect.bottom + 4, bottom: 'auto' }),
        });
    }

    useLayoutEffect(() => {
        if (!open) {
            return;
        }

        updateMenuPosition();
        window.addEventListener('resize', updateMenuPosition);
        window.addEventListener('scroll', updateMenuPosition, true);

        return () => {
            window.removeEventListener('resize', updateMenuPosition);
            window.removeEventListener('scroll', updateMenuPosition, true);
        };
    }, [open, items.length]);

    useEffect(() => {
        function onPointerDown(event: MouseEvent) {
            const target = event.target as Node;
            if (rootRef.current?.contains(target) || listRef.current?.contains(target)) {
                return;
            }

            setOpen(false);
            setQuery('');
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
    const triggerColorStyle = !open ? optionColorStyle(selected?.color) : undefined;

    const list = open
        ? createPortal(
              <ul
                  ref={listRef}
                  id={listId}
                  role="listbox"
                  style={menuStyle}
                  className="overflow-auto rounded-xl border border-line bg-surface py-1 shadow-lg"
              >
                  {items.length === 0 ? (
                      <li className="px-3 py-2 text-sm text-ink-muted">{t('common.noResults')}</li>
                  ) : (
                      items.map((option, index) => {
                          const active = option.value === value;
                          const highlighted = index === activeIndex;
                          const colorStyle = optionColorStyle(option.color);

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
                                      style={colorStyle}
                                      className={cn(
                                          'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors',
                                          colorStyle
                                              ? highlighted
                                                  ? 'ring-2 ring-inset ring-brand/50'
                                                  : ''
                                              : cn(
                                                    highlighted ? 'bg-canvas' : '',
                                                    active ? 'text-brand' : 'text-ink hover:bg-canvas',
                                                ),
                                      )}
                                  >
                                      <span
                                          className={cn(
                                              'inline-flex size-4 items-center justify-center rounded border',
                                              active
                                                  ? 'border-brand bg-brand text-white'
                                                  : colorStyle
                                                    ? 'border-black/20 bg-white/70'
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
              </ul>,
              document.body,
          )
        : null;

    return (
        <div ref={rootRef} className={cn('relative', className)}>
            <div
                className={cn(
                    'flex h-8 w-full items-center gap-1.5 rounded-lg border px-2.5 text-sm shadow-sm transition',
                    'focus-within:border-brand focus-within:outline-none focus-within:ring-2 focus-within:ring-brand/20',
                    !triggerColorStyle && 'bg-surface',
                    disabled && 'cursor-not-allowed opacity-60',
                    invalid ? 'border-danger focus-within:border-danger focus-within:ring-danger/20' : 'border-line',
                )}
                style={triggerColorStyle}
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
                    className={cn(
                        'min-w-0 flex-1 bg-transparent py-0.5 text-sm outline-none placeholder:opacity-70',
                        !triggerColorStyle && 'text-ink placeholder:text-ink-muted/70',
                    )}
                    style={triggerColorStyle ? { color: 'inherit' } : undefined}
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
                        className="rounded opacity-70 transition-opacity hover:opacity-100"
                        onClick={(event) => {
                            event.stopPropagation();
                            clear();
                        }}
                    >
                        <X className="size-3.5" aria-hidden />
                    </button>
                ) : null}

                <ChevronDown
                    className={cn('size-4 shrink-0 opacity-70 transition-transform', open && 'rotate-180')}
                    aria-hidden
                />
            </div>

            {list}
        </div>
    );
}
