import { useEffect, useId, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import {
    ProvincesApiError,
    provincesService,
    type CountryProvinceRow,
} from '@/services/provinces';
import { cn } from '@/support/cn';

type DraftRow = {
    key: string;
    id: number | null;
    name: string;
    code: string;
};

type RowFieldErrors = {
    name?: string;
    code?: string;
};

type CountryProvincesModalProps = {
    open: boolean;
    countryId: number;
    countryName: string;
    canEdit: boolean;
    onClose: () => void;
    onSaved: (count: number) => void;
};

function newRowKey(): string {
    return `new-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function toDraft(rows: CountryProvinceRow[]): DraftRow[] {
    return rows.map((row) => ({
        key: `id-${row.id}`,
        id: row.id,
        name: row.name,
        code: row.code ?? '',
    }));
}

function mapPayloadErrorsToRows(
    payloadKeys: string[],
    errors: Record<string, string[]>,
): Record<string, RowFieldErrors> {
    const byKey: Record<string, RowFieldErrors> = {};

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^provinces\.(\d+)\.(name|code)$/.exec(field);
        if (!match) {
            continue;
        }

        const index = Number(match[1]);
        const attribute = match[2] as 'name' | 'code';
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

export function CountryProvincesModal({
    open,
    countryId,
    countryName,
    canEdit,
    onClose,
    onSaved,
}: CountryProvincesModalProps) {
    const { t } = useTranslation();
    const searchId = useId();
    const [rows, setRows] = useState<DraftRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, RowFieldErrors>>({});
    const [search, setSearch] = useState('');

    const visibleRows = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase();
        if (needle === '') {
            return rows;
        }

        return rows.filter((row) => {
            const name = row.name.toLocaleLowerCase();
            const code = row.code.toLocaleLowerCase();

            return name.includes(needle) || code.includes(needle);
        });
    }, [rows, search]);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);
        setFieldErrors({});
        setSearch('');

        void provincesService
            .forCountry(countryId)
            .then((data) => {
                if (!cancelled) {
                    setRows(toDraft(data));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('provinces.loadFailed'));
                    setRows([]);
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
    }, [countryId, open, t]);

    function addRow() {
        setRows((current) => [
            ...current,
            {
                key: newRowKey(),
                id: null,
                name: '',
                code: '',
            },
        ]);
    }

    function updateRow(key: string, patch: Partial<Pick<DraftRow, 'name' | 'code'>>) {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
        setFieldErrors((current) => {
            if (!current[key]) {
                return current;
            }

            const next = { ...current[key] };
            if (patch.name !== undefined) {
                delete next.name;
            }
            if (patch.code !== undefined) {
                delete next.code;
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

    async function save() {
        const filled = rows.filter((row) => row.name.trim() !== '');
        const payload = filled.map((row) => ({
            id: row.id,
            name: row.name.trim(),
            code: row.code.trim() === '' ? null : row.code.trim(),
        }));
        const payloadKeys = filled.map((row) => row.key);

        setSaving(true);
        setError(null);
        setFieldErrors({});

        try {
            const saved = await provincesService.syncForCountry(countryId, payload);
            setRows(toDraft(saved));
            onSaved(saved.length);
            onClose();
        } catch (caught) {
            if (caught instanceof ProvincesApiError) {
                const mapped = mapPayloadErrorsToRows(payloadKeys, caught.errors);
                setFieldErrors(mapped);
                if (Object.keys(mapped).length === 0) {
                    setError(caught.message || t('provinces.saveFailed'));
                }
            } else {
                setError(caught instanceof Error ? caught.message : t('provinces.saveFailed'));
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <BaseModal
            open={open}
            onClose={onClose}
            closeDisabled={saving}
            size="lg"
            title={t('provinces.modalTitle', { country: countryName })}
            description={t('provinces.modalDescription')}
            footer={
                <>
                    <Button type="button" variant="secondary" disabled={saving} onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    {canEdit ? (
                        <Button type="button" loading={saving} disabled={loading} onClick={() => void save()}>
                            {t('common.save')}
                        </Button>
                    ) : null}
                </>
            }
        >
            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : (
                <div className="space-y-3">
                    <div className="mb-4 space-y-1.5">
                        <label htmlFor={searchId} className="text-sm font-semibold text-ink">
                            {t('common.search')}
                        </label>
                        <Input
                            id={searchId}
                            value={search}
                            disabled={saving}
                            placeholder={t('provinces.searchPlaceholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="grid grid-cols-[1fr_8rem_2.5rem] gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <span>{t('common.name')}</span>
                        <span>{t('provinces.code')}</span>
                        <span className="sr-only">{t('common.actions')}</span>
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('provinces.emptyForCountry')}
                        </p>
                    ) : visibleRows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('provinces.noSearchResults')}
                        </p>
                    ) : (
                        visibleRows.map((row) => {
                            const rowError = fieldErrors[row.key];

                            return (
                                <div key={row.key} className="space-y-1">
                                    <div className="grid grid-cols-[1fr_8rem_2.5rem] items-start gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.name}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.name)}
                                                placeholder={t('provinces.namePlaceholder')}
                                                onChange={(event) =>
                                                    updateRow(row.key, { name: event.target.value })
                                                }
                                            />
                                            {rowError?.name ? (
                                                <p className="text-xs text-danger">{rowError.name}</p>
                                            ) : null}
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.code}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.code)}
                                                placeholder={t('provinces.codePlaceholder')}
                                                onChange={(event) =>
                                                    updateRow(row.key, { code: event.target.value })
                                                }
                                            />
                                            {rowError?.code ? (
                                                <p className="text-xs text-danger">{rowError.code}</p>
                                            ) : null}
                                        </div>
                                        {canEdit ? (
                                            <button
                                                type="button"
                                                disabled={saving}
                                                onClick={() => removeRow(row.key)}
                                                className="inline-flex size-9 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-danger/40 hover:text-danger disabled:opacity-50"
                                                aria-label={t('common.remove', {
                                                    label: row.name || t('provinces.resource'),
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
                            {t('provinces.addRow')}
                        </Button>
                    ) : null}
                </div>
            )}

            {error ? <p className={cn('mt-3 text-sm text-danger')}>{error}</p> : null}
        </BaseModal>
    );
}
