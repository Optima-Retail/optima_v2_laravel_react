import { useEffect, useId, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { BaseModal } from '@/components/ui/BaseModal';
import { Input } from '@/components/ui/Input';
import {
    TechnicianVehiclesApiError,
    fetchTechnicianVehicles,
    syncTechnicianVehicles,
    type TechnicianVehicleRow,
} from '@/services/technicianVehicles';
import { cn } from '@/support/cn';

type DraftRow = {
    key: string;
    id: number | null;
    brand: string;
    model: string;
    license_plate: string;
};

type RowFieldErrors = {
    brand?: string;
    model?: string;
    license_plate?: string;
};

type TechnicianVehiclesModalProps = {
    open: boolean;
    relationshipId: number;
    technicianName: string;
    canEdit: boolean;
    onClose: () => void;
};

function newRowKey(): string {
    return `new-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

function toDraft(rows: TechnicianVehicleRow[]): DraftRow[] {
    return rows.map((row) => ({
        key: `id-${row.id}`,
        id: row.id,
        brand: row.brand ?? '',
        model: row.model ?? '',
        license_plate: row.license_plate ?? '',
    }));
}

function mapPayloadErrorsToRows(
    payloadKeys: string[],
    errors: Record<string, string[]>,
): Record<string, RowFieldErrors> {
    const byKey: Record<string, RowFieldErrors> = {};

    for (const [field, messages] of Object.entries(errors)) {
        const match = /^vehicles\.(\d+)\.(brand|model|license_plate)$/.exec(field);
        if (!match) {
            continue;
        }

        const index = Number(match[1]);
        const attribute = match[2] as 'brand' | 'model' | 'license_plate';
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

export function TechnicianVehiclesModal({
    open,
    relationshipId,
    technicianName,
    canEdit,
    onClose,
}: TechnicianVehiclesModalProps) {
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
            const haystack = [row.brand, row.model, row.license_plate].join(' ').toLocaleLowerCase();

            return haystack.includes(needle);
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

        void fetchTechnicianVehicles(relationshipId)
            .then((payload) => {
                if (!cancelled) {
                    setRows(toDraft(payload.data));
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(t('suppliers.vehiclesLoadFailed'));
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
    }, [relationshipId, open, t]);

    function addRow() {
        setRows((current) => [
            ...current,
            {
                key: newRowKey(),
                id: null,
                brand: '',
                model: '',
                license_plate: '',
            },
        ]);
    }

    function updateRow(
        key: string,
        patch: Partial<Pick<DraftRow, 'brand' | 'model' | 'license_plate'>>,
    ) {
        setRows((current) => current.map((row) => (row.key === key ? { ...row, ...patch } : row)));
        setFieldErrors((current) => {
            if (!current[key]) {
                return current;
            }

            const next = { ...current[key] };
            if (patch.brand !== undefined) {
                delete next.brand;
            }
            if (patch.model !== undefined) {
                delete next.model;
            }
            if (patch.license_plate !== undefined) {
                delete next.license_plate;
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
        const filled = rows.filter(
            (row) =>
                row.brand.trim() !== '' || row.model.trim() !== '' || row.license_plate.trim() !== '',
        );
        const payload = filled.map((row) => ({
            id: row.id,
            brand: row.brand.trim() || null,
            model: row.model.trim() || null,
            license_plate: row.license_plate.trim() || null,
        }));
        const payloadKeys = filled.map((row) => row.key);

        setSaving(true);
        setError(null);
        setFieldErrors({});

        try {
            const saved = await syncTechnicianVehicles(relationshipId, payload);
            setRows(toDraft(saved));
            onClose();
        } catch (caught) {
            if (caught instanceof TechnicianVehiclesApiError) {
                const mapped = mapPayloadErrorsToRows(payloadKeys, caught.errors);
                setFieldErrors(mapped);
                if (Object.keys(mapped).length === 0) {
                    setError(caught.message || t('suppliers.vehiclesSaveFailed'));
                }
            } else {
                setError(caught instanceof Error ? caught.message : t('suppliers.vehiclesSaveFailed'));
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
            title={t('suppliers.vehiclesModalTitle', { technician: technicianName })}
            description={t('suppliers.vehiclesModalDescription')}
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
                            placeholder={t('suppliers.vehiclesSearchPlaceholder')}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                    </div>

                    <div className="grid grid-cols-[1fr_1fr_1fr_2.5rem] gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                        <span>{t('vehicles.brand')}</span>
                        <span>{t('vehicles.model')}</span>
                        <span>{t('vehicles.licensePlate')}</span>
                        <span className="sr-only">{t('common.actions')}</span>
                    </div>

                    {rows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('suppliers.vehiclesEmpty')}
                        </p>
                    ) : visibleRows.length === 0 ? (
                        <p className="rounded-lg border border-dashed border-line px-3 py-6 text-center text-sm text-ink-muted">
                            {t('suppliers.vehiclesNoSearchResults')}
                        </p>
                    ) : (
                        visibleRows.map((row) => {
                            const rowError = fieldErrors[row.key];

                            return (
                                <div key={row.key} className="space-y-1">
                                    <div className="grid grid-cols-[1fr_1fr_1fr_2.5rem] items-start gap-2">
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.brand}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.brand)}
                                                onChange={(event) =>
                                                    updateRow(row.key, { brand: event.target.value })
                                                }
                                            />
                                            {rowError?.brand ? (
                                                <p className="text-xs text-danger">{rowError.brand}</p>
                                            ) : null}
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.model}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.model)}
                                                onChange={(event) =>
                                                    updateRow(row.key, { model: event.target.value })
                                                }
                                            />
                                            {rowError?.model ? (
                                                <p className="text-xs text-danger">{rowError.model}</p>
                                            ) : null}
                                        </div>
                                        <div className="min-w-0 space-y-1">
                                            <Input
                                                value={row.license_plate}
                                                disabled={!canEdit || saving}
                                                invalid={Boolean(rowError?.license_plate)}
                                                onChange={(event) =>
                                                    updateRow(row.key, {
                                                        license_plate: event.target.value,
                                                    })
                                                }
                                            />
                                            {rowError?.license_plate ? (
                                                <p className="text-xs text-danger">{rowError.license_plate}</p>
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
                                                        [row.brand, row.model, row.license_plate]
                                                            .filter(Boolean)
                                                            .join(' ') || t('vehicles.resource'),
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
                            {t('suppliers.vehiclesAddRow')}
                        </Button>
                    ) : null}
                </div>
            )}

            {error ? <p className={cn('mt-3 text-sm text-danger')}>{error}</p> : null}
        </BaseModal>
    );
}
