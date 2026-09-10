import { useEffect, useMemo, useState } from 'react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { SearchableSelect } from '@/components/ui/SearchableSelect';
import {
    ClientArticlesApiError,
    fetchClientArticles,
    syncClientArticles,
    type ClientArticleOption,
    type ClientArticleRow,
} from '@/services/clientArticles';
import { cn } from '@/support/cn';
import { useToastStore } from '@/stores/toastStore';

type DraftRow = {
    key: string;
    id: number | null;
    article_id: string;
    sale_price: string;
};

type RowFieldErrors = {
    article_id?: string;
    sale_price?: string;
};

type ClientArticlesPanelProps = {
    relationshipId: number;
    canEdit: boolean;
    embedded?: boolean;
};

function newRowKey(): string {
    return `new-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function toDraft(rows: ClientArticleRow[]): DraftRow[] {
    return rows.map((row) => ({
        key: `id-${row.id}`,
        id: row.id,
        article_id: String(row.article_id),
        sale_price: row.sale_price,
    }));
}

function mapPayloadErrorsToRows(
    payloadKeys: string[],
    errors: Record<string, string[]>,
): Record<string, RowFieldErrors> {
    const byKey: Record<string, RowFieldErrors> = {};

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^articles\.(\d+)\.(article_id|sale_price)$/.exec(field);
        if (!match) {
            continue;
        }

        const index = Number(match[1]);
        const attribute = match[2] as 'article_id' | 'sale_price';
        const rowKey = payloadKeys[index];
        const message = messages[0];

        if (!rowKey || !message) {
            continue;
        }

        byKey[rowKey] = {
            ...byKey[rowKey],
            [attribute]: message,
        };
    }

    return byKey;
}

export function ClientArticlesPanel({
    relationshipId,
    canEdit,
    embedded = false,
}: ClientArticlesPanelProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const [rows, setRows] = useState<DraftRow[]>([]);
    const [articleOptions, setArticleOptions] = useState<ClientArticleOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<Record<string, RowFieldErrors>>({});

    const selectOptions = useMemo(
        () => articleOptions.map((option) => ({ value: String(option.id), label: option.label })),
        [articleOptions],
    );

    const optionLabelById = useMemo(() => {
        const map = new Map<string, string>();
        for (const option of articleOptions) {
            map.set(String(option.id), option.label);
        }

        return map;
    }, [articleOptions]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setFieldErrors({});

        void fetchClientArticles(relationshipId)
            .then((payload) => {
                if (!cancelled) {
                    setRows(toDraft(payload.data));
                    setArticleOptions(payload.article_options);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    pushToast(t('clients.articlesLoadFailed'), 'error');
                    setRows([]);
                    setArticleOptions([]);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [relationshipId, pushToast, t]);

    function addRow() {
        setRows((current) => [
            ...current,
            {
                key: newRowKey(),
                id: null,
                article_id: '',
                sale_price: '',
            },
        ]);
    }

    function updateRow(key: string, patch: Partial<Pick<DraftRow, 'article_id' | 'sale_price'>>) {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
        setFieldErrors((current) => {
            if (!current[key]) {
                return current;
            }

            const next = { ...current[key] };
            if (patch.article_id !== undefined) {
                delete next.article_id;
            }
            if (patch.sale_price !== undefined) {
                delete next.sale_price;
            }

            const { [key]: _removed, ...rest } = current;

            return Object.keys(next).length > 0 ? { ...rest, [key]: next } : rest;
        });
    }

    function removeRow(key: string) {
        setRows((current) => current.filter((row) => row.key !== key));
        setFieldErrors((current) => {
            const { [key]: _removed, ...rest } = current;

            return rest;
        });
    }

    function availableOptionsForRow(row: DraftRow) {
        const used = new Set(
            rows
                .filter((item) => item.key !== row.key && item.article_id !== '')
                .map((item) => item.article_id),
        );

        return selectOptions.filter(
            (option) => option.value === row.article_id || !used.has(option.value),
        );
    }

    async function save() {
        const filled = rows.filter((row) => row.article_id !== '' && row.sale_price.trim() !== '');
        const payload = filled.map((row) => ({
            id: row.id,
            article_id: Number(row.article_id),
            sale_price: row.sale_price.trim(),
        }));
        const payloadKeys = filled.map((row) => row.key);

        setSaving(true);
        setFieldErrors({});

        try {
            const saved = await syncClientArticles(relationshipId, payload);
            setRows(toDraft(saved));
            pushToast(t('clients.articlesSaved'), 'success');
        } catch (caught) {
            if (caught instanceof ClientArticlesApiError) {
                const mapped = mapPayloadErrorsToRows(payloadKeys, caught.errors);
                setFieldErrors(mapped);
                if (Object.keys(mapped).length === 0) {
                    pushToast(caught.message || t('clients.articlesSaveFailed'), 'error');
                }
            } else {
                pushToast(
                    caught instanceof Error ? caught.message : t('clients.articlesSaveFailed'),
                    'error',
                );
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <div
            className={cn(
                'space-y-5',
                !embedded && 'rounded-2xl border border-line bg-surface p-6 sm:p-8',
            )}
        >
            {!embedded ? (
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('clients.articlesTabTitle')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('clients.articlesModalDescription')}</p>
                </div>
            ) : (
                <p className="text-sm text-ink-muted">{t('clients.articlesModalDescription')}</p>
            )}

            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : (
                <div className="space-y-3">
                    <div className="grid grid-cols-[1fr_8rem_2.5rem] gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <span>{t('articles.resource')}</span>
                        <span>{t('articles.salePrice')}</span>
                        <span className="sr-only">{t('common.actions')}</span>
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('clients.articlesEmpty')}
                        </p>
                    ) : (
                        rows.map((row) => {
                            const rowError = fieldErrors[row.key];

                            return (
                                <div key={row.key} className="space-y-1">
                                    <div className="grid grid-cols-[1fr_8rem_2.5rem] items-start gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <SearchableSelect
                                                options={availableOptionsForRow(row)}
                                                value={row.article_id}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.article_id)}
                                                placeholder={t('clients.articlesSelectPlaceholder')}
                                                onChange={(value) => updateRow(row.key, { article_id: value })}
                                            />
                                            {rowError?.article_id ? (
                                                <p className="text-xs text-danger">{rowError.article_id}</p>
                                            ) : null}
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={row.sale_price}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.sale_price)}
                                                placeholder="0.00"
                                                onChange={(event) =>
                                                    updateRow(row.key, { sale_price: event.target.value })
                                                }
                                            />
                                            {rowError?.sale_price ? (
                                                <p className="text-xs text-danger">{rowError.sale_price}</p>
                                            ) : null}
                                        </div>
                                        {canEdit ? (
                                            <button
                                                type="button"
                                                disabled={saving}
                                                onClick={() => removeRow(row.key)}
                                                className="inline-flex size-9 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-danger/40 hover:text-danger disabled:opacity-50"
                                                aria-label={t('common.remove', {
                                                    label:
                                                        optionLabelById.get(row.article_id) ||
                                                        t('articles.resource'),
                                                })}
                                            >
                                                <Trash2 className="size-4" aria-hidden />
                                            </button>
                                        ) : (
                                            <span />
                                        )}
                                    </div>
                                </div>
                            );
                        })
                    )}

                    {canEdit ? (
                        <Button type="button" variant="secondary" disabled={saving} onClick={addRow}>
                            <Plus className="size-4" aria-hidden />
                            {t('clients.articlesAddRow')}
                        </Button>
                    ) : null}
                </div>
            )}

            {canEdit ? (
                <div className="flex justify-end border-t border-line pt-4">
                    <Button type="button" loading={saving} disabled={loading} onClick={() => void save()}>
                        <Save className="size-4" aria-hidden />
                        {t('common.save')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
