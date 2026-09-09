import { useEffect, useId, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import {
    IncidentSubtypesApiError,
    incidentSubtypesService,
    type IncidentTypeSubtypeRow,
} from '@/services/incidentSubtypes';
import { cn } from '@/support/cn';

type DraftRow = {
    key: string;
    id: number | null;
    name: string;
};

type RowFieldErrors = {
    name?: string;
};

type IncidentTypeSubtypesModalProps = {
    open: boolean;
    incidentTypeId: number;
    incidentTypeName: string;
    canEdit: boolean;
    onClose: () => void;
    onSaved: (count: number) => void;
};

function newRowKey(): string {
    return `new-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function toDraft(rows: IncidentTypeSubtypeRow[]): DraftRow[] {
    return rows.map((row) => ({
        key: `id-${row.id}`,
        id: row.id,
        name: row.name,
    }));
}

function mapPayloadErrorsToRows(
    payloadKeys: string[],
    errors: Record<string, string[]>,
): Record<string, RowFieldErrors> {
    const byKey: Record<string, RowFieldErrors> = {};

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^subtypes\.(\d+)\.name$/.exec(field);
        if (!match) {
            continue;
        }

        const index = Number(match[1]);
        const rowKey = payloadKeys[index];
        const message = messages[0];

        if (!rowKey || !message) {
            continue;
        }

        byKey[rowKey] = {
            ...byKey[rowKey],
            name: message,
        };
    }

    return byKey;
}

export function IncidentTypeSubtypesModal({
    open,
    incidentTypeId,
    incidentTypeName,
    canEdit,
    onClose,
    onSaved,
}: IncidentTypeSubtypesModalProps) {
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

        return rows.filter((row) => row.name.toLocaleLowerCase().includes(needle));
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

        void incidentSubtypesService
            .forType(incidentTypeId)
            .then((data) => {
                if (!cancelled) {
                    setRows(toDraft(data));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('incidentSubtypes.loadFailed'));
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
    }, [incidentTypeId, open, t]);

    function addRow() {
        setRows((current) => [
            ...current,
            {
                key: newRowKey(),
                id: null,
                name: '',
            },
        ]);
    }

    function updateRow(key: string, name: string) {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, name } : row)));
        setFieldErrors((current) => {
            if (!current[key]) {
                return current;
            }

            const { [key]: _removed, ...rest } = current;

            return rest;
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
        }));
        const payloadKeys = filled.map((row) => row.key);

        setSaving(true);
        setError(null);
        setFieldErrors({});

        try {
            const saved = await incidentSubtypesService.syncForType(incidentTypeId, payload);
            setRows(toDraft(saved));
            onSaved(saved.length);
            onClose();
        } catch (caught) {
            if (caught instanceof IncidentSubtypesApiError) {
                const mapped = mapPayloadErrorsToRows(payloadKeys, caught.errors);
                setFieldErrors(mapped);
                if (Object.keys(mapped).length === 0) {
                    setError(caught.message || t('incidentSubtypes.saveFailed'));
                }
            } else {
                setError(caught instanceof Error ? caught.message : t('incidentSubtypes.saveFailed'));
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
            title={t('incidentSubtypes.modalTitle', { type: incidentTypeName })}
            description={t('incidentSubtypes.modalDescription')}
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
                            placeholder={t('incidentSubtypes.searchPlaceholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="grid grid-cols-[1fr_2.5rem] gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <span>{t('common.name')}</span>
                        <span className="sr-only">{t('common.actions')}</span>
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('incidentSubtypes.emptyForType')}
                        </p>
                    ) : visibleRows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('incidentSubtypes.noSearchResults')}
                        </p>
                    ) : (
                        visibleRows.map((row) => {
                            const rowError = fieldErrors[row.key];

                            return (
                                <div key={row.key} className="space-y-1">
                                    <div className="grid grid-cols-[1fr_2.5rem] items-start gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.name}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.name)}
                                                placeholder={t('incidentSubtypes.namePlaceholder')}
                                                onChange={(event) => updateRow(row.key, event.target.value)}
                                            />
                                            {rowError?.name ? (
                                                <p className="text-xs text-danger">{rowError.name}</p>
                                            ) : null}
                                        </div>
                                        {canEdit ? (
                                            <button
                                                type="button"
                                                disabled={saving}
                                                onClick={() => removeRow(row.key)}
                                                className="inline-flex size-9 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-danger/40 hover:text-danger disabled:opacity-50"
                                                aria-label={t('common.remove', {
                                                    label: row.name || t('incidentSubtypes.resource'),
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
                            {t('incidentSubtypes.addRow')}
                        </Button>
                    ) : null}
                </div>
            )}

            {error ? <p className={cn('mt-3 text-sm text-danger')}>{error}</p> : null}
        </BaseModal>
    );
}
