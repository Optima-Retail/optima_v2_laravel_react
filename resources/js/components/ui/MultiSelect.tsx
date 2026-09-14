import {
    useEffect,
    useId,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type CSSProperties,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';
import { Check, ChevronDown, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CompanyMark, CompanyOptionLabel } from '@/components/companies/CompanyOptionLabel';
import { cn } from '@/support/cn';
import { optionColorStyle } from '@/support/color';
import type { SelectOption } from '@/components/ui/SearchableSelect';

export type MultiSelectOption = SelectOption;

function optionContent(option: SelectOption): ReactNode {
    if ('logo_url' in option) {
        return <CompanyOptionLabel name={option.label} logoUrl={option.logo_url} size="sm" />;
    }

    return option.label;
}

type MultiSelectProps = {
    id?: string;
    options: MultiSelectOption[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    invalid?: boolean;
    disabled?: boolean;
    className?: string;
};

const LIST_MAX_HEIGHT = 224;

export function MultiSelect({
    id,
    options,
    value,
    onChange,
    placeholder,
    invalid = false,
    disabled = false,
    className,
}: MultiSelectProps) {
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
        () => options.filter((option) => value.includes(option.value)),
        [options, value],
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        if (!needle) {
            return options;
        }

        return options.filter((option) => option.label.toLowerCase().includes(needle));
    }, [options, query]);

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
    }, [open, filtered.length, selected.length]);

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
        inputRef.current?.focus();
    }

    function toggle(optionValue: string) {
        if (value.includes(optionValue)) {
            onChange(value.filter((item) => item !== optionValue));
            return;
        }

        onChange([...value, optionValue]);
        setQuery('');
        inputRef.current?.focus();
    }

    function remove(optionValue: string) {
        onChange(value.filter((item) => item !== optionValue));
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
            setActiveIndex((current) => Math.min(current + 1, Math.max(filtered.length - 1, 0)));
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
            setActiveIndex(Math.max(filtered.length - 1, 0));
            return;
        }

        if (event.key === 'Enter' && open && filtered[activeIndex]) {
            event.preventDefault();
            toggle(filtered[activeIndex].value);
            return;
        }

        if (event.key === 'Backspace' && query === '' && value.length > 0) {
            remove(value[value.length - 1]);
        }
    }

    const activeOption = filtered[activeIndex];

    const list = open
        ? createPortal(
              <ul
                  ref={listRef}
                  id={listId}
                  role="listbox"
                  aria-multiselectable="true"
                  style={menuStyle}
                  className="overflow-auto rounded-xl border border-line bg-surface py-1 shadow-lg"
              >
                  {filtered.length === 0 ? (
                      <li className="px-3 py-2 text-sm text-ink-muted">{t('common.noResults')}</li>
                  ) : (
                      filtered.map((option, index) => {
                          const active = value.includes(option.value);
                          const highlighted = index === activeIndex;
                          const colorStyle = optionColorStyle(option.color);

                          return (
                              <li
                                  key={option.value}
                                  id={`${listId}-${option.value}`}
                                  role="option"
                                  aria-selected={active}
                              >
                                  <button
                                      type="button"
                                      onMouseEnter={() => setActiveIndex(index)}
                                      onClick={() => toggle(option.value)}
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
                                              'inline-flex size-4 shrink-0 items-center justify-center rounded border',
                                              active
                                                  ? 'border-brand bg-brand text-white'
                                                  : colorStyle
                                                    ? 'border-black/20 bg-white/70'
                                                    : 'border-line bg-surface',
                                          )}
                                      >
                                          {active ? <Check className="size-3" aria-hidden /> : null}
                                      </span>
                                      {optionContent(option)}
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
                    'flex min-h-8 w-full flex-wrap items-center gap-1.5 rounded-lg border bg-surface px-2.5 py-1.5 text-sm shadow-sm transition',
                    'focus-within:border-brand focus-within:outline-none focus-within:ring-2 focus-within:ring-brand/20',
                    disabled && 'cursor-not-allowed opacity-60',
                    invalid ? 'border-danger focus-within:border-danger focus-within:ring-danger/20' : 'border-line',
                )}
                onClick={() => openList()}
            >
                {selected.map((option) => {
                    const colorStyle = optionColorStyle(option.color);

                    return (
                        <span
                            key={option.value}
                            style={colorStyle}
                            className={cn(
                                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-semibold',
                                !colorStyle && 'bg-brand-soft text-brand',
                            )}
                        >
                            {'logo_url' in option ? (
                                <CompanyMark name={option.label} logoUrl={option.logo_url} size="xs" />
                            ) : null}
                            <span className="max-w-40 truncate">{option.label}</span>
                            <button
                                type="button"
                                disabled={disabled}
                                aria-label={t('common.remove', { label: option.label })}
                                className={cn(
                                    'rounded transition-opacity hover:opacity-80',
                                    !colorStyle && 'text-brand hover:text-brand-strong',
                                )}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    remove(option.value);
                                }}
                            >
                                <X className="size-3" aria-hidden />
                            </button>
                        </span>
                    );
                })}

                <input
                    ref={inputRef}
                    id={id}
                    value={query}
                    disabled={disabled}
                    placeholder={selected.length === 0 ? resolvedPlaceholder : t('common.searchOptions')}
                    aria-invalid={invalid}
                    aria-expanded={open}
                    aria-haspopup="listbox"
                    aria-controls={listId}
                    aria-activedescendant={open && activeOption ? `${listId}-${activeOption.value}` : undefined}
                    role="combobox"
                    autoComplete="off"
                    className="min-w-24 flex-1 bg-transparent py-0.5 text-sm text-ink outline-none placeholder:text-ink-muted/70"
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    onKeyDown={onKeyDown}
                />

                <ChevronDown
                    className={cn('size-4 shrink-0 text-ink-muted transition-transform', open && 'rotate-180')}
                    aria-hidden
                />
            </div>

            {list}
        </div>
    );
}
