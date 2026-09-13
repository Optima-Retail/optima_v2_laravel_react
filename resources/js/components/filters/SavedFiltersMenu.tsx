import { Bookmark, Check, Loader2, Save, Star, Trash2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { savedFiltersService, type SavedFilterItem } from '@/services/savedFilters';
import { cn } from '@/support/cn';

type SavedFiltersMenuProps = {
    pageKey: string;
    currentFilters: Record<string, string>;
    onApply: (filters: Record<string, string>) => void;
    fieldNames: string[];
};

function hasMeaningfulFilters(filters: Record<string, string>): boolean {
    return Object.values(filters).some((value) => value.trim() !== '');
}

function emptyFilters(fieldNames: string[]): Record<string, string> {
    return Object.fromEntries(fieldNames.map((name) => [name, '']));
}

function mergeAppliedFilters(
    fieldNames: string[],
    filters: Record<string, unknown>,
): Record<string, string> {
    const next = emptyFilters(fieldNames);

    for (const [key, value] of Object.entries(filters)) {
        if (!(key in next)) {
            continue;
        }

        next[key] = value == null ? '' : String(value);
    }

    return next;
}

export function SavedFiltersMenu({
    pageKey,
    currentFilters,
    onApply,
    fieldNames,
}: SavedFiltersMenuProps) {
    const { t } = useTranslation();
    const rootRef = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [items, setItems] = useState<SavedFilterItem[]>([]);
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const defaultAppliedRef = useRef(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);

        try {
            const data = await savedFiltersService.list(pageKey);
            setItems(data);

            return data;
        } catch {
            setError(t('savedFilters.loadError'));

            return [];
        } finally {
            setLoading(false);
        }
    }, [pageKey, t]);

    useEffect(() => {
        let cancelled = false;

        void (async () => {
            const data = await load();

            if (cancelled || defaultAppliedRef.current) {
                return;
            }

            defaultAppliedRef.current = true;

            if (hasMeaningfulFilters(currentFilters)) {
                return;
            }

            const defaultFilter = data.find((item) => item.is_default);

            if (defaultFilter) {
                onApply(mergeAppliedFilters(fieldNames, defaultFilter.filters));
            }
        })();

        return () => {
            cancelled = true;
        };
        // Apply default once on mount for this page.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageKey]);

    useEffect(() => {
        if (!open) {
            return;
        }

        function onPointerDown(event: MouseEvent) {
            if (!rootRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', onPointerDown);

        return () => document.removeEventListener('mousedown', onPointerDown);
    }, [open]);

    async function handleSave() {
        const trimmed = name.trim();

        if (!trimmed) {
            setError(t('savedFilters.nameRequired'));

            return;
        }

        if (!hasMeaningfulFilters(currentFilters)) {
            setError(t('savedFilters.emptyFilters'));

            return;
        }

        setSaving(true);
        setError(null);

        try {
            await savedFiltersService.save({
                name: trimmed,
                page_key: pageKey,
                filters: currentFilters,
            });
            setName('');
            await load();
        } catch {
            setError(t('savedFilters.saveError'));
        } finally {
            setSaving(false);
        }
    }

    async function handleDelete(id: number) {
        try {
            await savedFiltersService.destroy(id);
            await load();
        } catch {
            setError(t('savedFilters.deleteError'));
        }
    }

    async function handleToggleDefault(item: SavedFilterItem) {
        try {
            await savedFiltersService.setDefault(item.id, !item.is_default);
            await load();
        } catch {
            setError(t('savedFilters.defaultError'));
        }
    }

    return (
        <div ref={rootRef} className="relative">
            <Button
                type="button"
                variant="ghost"
                onClick={() => {
                    setOpen((value) => !value);

                    if (!open) {
                        void load();
                    }
                }}
                title={t('savedFilters.title')}
            >
                <Bookmark className="size-3.5" aria-hidden />
                {t('savedFilters.title')}
            </Button>

            {open ? (
                <div className="absolute right-0 z-30 mt-2 w-80 rounded-xl border border-line bg-surface p-3 shadow-lg">
                    <div className="mb-3 space-y-2">
                        <p className="text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">
                            {t('savedFilters.saveCurrent')}
                        </p>
                        <div className="flex gap-2">
                            <Input
                                value={name}
                                onChange={(event) => setName(event.target.value)}
                                placeholder={t('savedFilters.namePlaceholder')}
                                className="h-8"
                            />
                            <Button type="button" variant="secondary" loading={saving} onClick={() => void handleSave()}>
                                <Save className="size-3.5" aria-hidden />
                            </Button>
                        </div>
                    </div>

                    <div className="border-t border-line pt-3">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.08em] text-ink-muted">
                            {t('savedFilters.saved')}
                        </p>

                        {loading ? (
                            <div className="flex items-center gap-2 py-3 text-sm text-ink-muted">
                                <Loader2 className="size-4 animate-spin" aria-hidden />
                                {t('common.loading')}
                            </div>
                        ) : items.length === 0 ? (
                            <p className="py-2 text-sm text-ink-muted">{t('savedFilters.empty')}</p>
                        ) : (
                            <ul className="max-h-56 space-y-1 overflow-y-auto">
                                {items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center gap-1 rounded-lg px-1.5 py-1 hover:bg-canvas"
                                    >
                                        <button
                                            type="button"
                                            className="min-w-0 flex-1 truncate text-left text-sm text-ink"
                                            onClick={() => {
                                                onApply(mergeAppliedFilters(fieldNames, item.filters));
                                                setOpen(false);
                                            }}
                                        >
                                            <span className="inline-flex items-center gap-1.5">
                                                {item.is_default ? (
                                                    <Check className="size-3.5 shrink-0 text-brand" aria-hidden />
                                                ) : null}
                                                {item.name}
                                            </span>
                                        </button>
                                        <button
                                            type="button"
                                            className={cn(
                                                'rounded p-1 text-ink-muted hover:bg-surface hover:text-ink',
                                                item.is_default && 'text-brand',
                                            )}
                                            title={t('savedFilters.setDefault')}
                                            onClick={() => void handleToggleDefault(item)}
                                        >
                                            <Star className="size-3.5" aria-hidden />
                                        </button>
                                        <button
                                            type="button"
                                            className="rounded p-1 text-ink-muted hover:bg-surface hover:text-danger"
                                            title={t('common.delete')}
                                            onClick={() => void handleDelete(item.id)}
                                        >
                                            <Trash2 className="size-3.5" aria-hidden />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    {error ? <p className="mt-2 text-xs text-danger">{error}</p> : null}
                </div>
            ) : null}
        </div>
    );
}
