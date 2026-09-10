import { useEffect, useMemo, useState } from 'react';
import { ArrowDown, ChevronLeft, ChevronRight, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import {
    ClientRatesApiError,
    fetchClientRates,
    syncClientRates,
    type ClientRatePriorityOption,
    type ClientRateRow,
    type ClientRateWorkOrderTypeOption,
} from '@/services/clientRates';
import { cn } from '@/support/cn';
import { useToastStore } from '@/stores/toastStore';

type AmountField =
    | 'travel_amount'
    | 'extra_travel_amount'
    | 'labor_amount'
    | 'extra_labor_amount'
    | 'due_hours'
    | 'sla_hours';

type RateCell = {
    id: number | null;
    travel_amount: string;
    extra_travel_amount: string;
    labor_amount: string;
    extra_labor_amount: string;
    due_hours: string;
    sla_hours: string;
    is_urgent: boolean;
};

type ApplyAllValues = {
    travel_amount: string;
    extra_travel_amount: string;
    labor_amount: string;
    extra_labor_amount: string;
    due_hours: string;
    sla_hours: string;
    is_urgent: '' | 'true' | 'false';
};

/** matrix[priorityId][workOrderTypeId] */
type RateMatrix = Record<number, Record<number, RateCell>>;

type ClientRatesPanelProps = {
    relationshipId: number;
    canEdit: boolean;
    embedded?: boolean;
};

function emptyCell(): RateCell {
    return {
        id: null,
        travel_amount: '0',
        extra_travel_amount: '0',
        labor_amount: '0',
        extra_labor_amount: '0',
        due_hours: '0',
        sla_hours: '0',
        is_urgent: false,
    };
}

function emptyApplyAll(): ApplyAllValues {
    return {
        travel_amount: '',
        extra_travel_amount: '',
        labor_amount: '',
        extra_labor_amount: '',
        due_hours: '',
        sla_hours: '',
        is_urgent: '',
    };
}

function cellFromRow(row: ClientRateRow): RateCell {
    return {
        id: row.id,
        travel_amount: row.travel_amount,
        extra_travel_amount: row.extra_travel_amount,
        labor_amount: row.labor_amount,
        extra_labor_amount: row.extra_labor_amount,
        due_hours: String(row.due_hours),
        sla_hours: String(row.sla_hours),
        is_urgent: row.is_urgent,
    };
}

function buildMatrix(
    priorities: ClientRatePriorityOption[],
    workOrderTypes: ClientRateWorkOrderTypeOption[],
    rows: ClientRateRow[],
): RateMatrix {
    const byKey = new Map<string, ClientRateRow>();
    for (const row of rows) {
        byKey.set(`${row.client_priority_id}:${row.work_order_type_id}`, row);
    }

    const matrix: RateMatrix = {};
    for (const priority of priorities) {
        matrix[priority.id] = {};
        for (const type of workOrderTypes) {
            const existing = byKey.get(`${priority.id}:${type.id}`);
            matrix[priority.id][type.id] = existing ? cellFromRow(existing) : emptyCell();
        }
    }

    return matrix;
}

export function ClientRatesPanel({
    relationshipId,
    canEdit,
    embedded = false,
}: ClientRatesPanelProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const [priorities, setPriorities] = useState<ClientRatePriorityOption[]>([]);
    const [workOrderTypes, setWorkOrderTypes] = useState<ClientRateWorkOrderTypeOption[]>([]);
    const [matrix, setMatrix] = useState<RateMatrix>({});
    const [activeIndex, setActiveIndex] = useState(0);
    const [applyAll, setApplyAll] = useState<ApplyAllValues>(emptyApplyAll());
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    const activePriority = priorities[activeIndex] ?? null;

    const activeCells = useMemo(() => {
        if (!activePriority) {
            return [];
        }

        const byType = matrix[activePriority.id] ?? {};

        return workOrderTypes.map((type) => ({
            type,
            cell: byType[type.id] ?? emptyCell(),
        }));
    }, [activePriority, matrix, workOrderTypes]);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setActiveIndex(0);
        setApplyAll(emptyApplyAll());

        void fetchClientRates(relationshipId)
            .then((payload) => {
                if (cancelled) {
                    return;
                }

                setPriorities(payload.selected_priorities);
                setWorkOrderTypes(payload.work_order_types);
                setMatrix(
                    buildMatrix(
                        payload.selected_priorities,
                        payload.work_order_types,
                        payload.data,
                    ),
                );
            })
            .catch(() => {
                if (!cancelled) {
                    pushToast(t('clients.ratesLoadFailed'), 'error');
                    setPriorities([]);
                    setWorkOrderTypes([]);
                    setMatrix({});
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

    function goPrev() {
        if (priorities.length === 0) {
            return;
        }

        setActiveIndex((current) => (current === 0 ? priorities.length - 1 : current - 1));
        setApplyAll(emptyApplyAll());
    }

    function goNext() {
        if (priorities.length === 0) {
            return;
        }

        setActiveIndex((current) => (current === priorities.length - 1 ? 0 : current + 1));
        setApplyAll(emptyApplyAll());
    }

    function updateCell(
        priorityId: number,
        workOrderTypeId: number,
        patch: Partial<Omit<RateCell, 'id'>>,
    ) {
        setMatrix((current) => {
            const priorityRows = current[priorityId] ?? {};
            const existing = priorityRows[workOrderTypeId] ?? emptyCell();

            return {
                ...current,
                [priorityId]: {
                    ...priorityRows,
                    [workOrderTypeId]: { ...existing, ...patch },
                },
            };
        });
    }

    function applyColumn(field: AmountField | 'is_urgent') {
        if (!activePriority || !canEdit) {
            return;
        }

        if (field === 'is_urgent') {
            if (applyAll.is_urgent !== 'true' && applyAll.is_urgent !== 'false') {
                return;
            }

            const value = applyAll.is_urgent === 'true';
            setMatrix((current) => {
                const priorityRows = { ...(current[activePriority.id] ?? {}) };
                for (const type of workOrderTypes) {
                    const existing = priorityRows[type.id] ?? emptyCell();
                    priorityRows[type.id] = { ...existing, is_urgent: value };
                }

                return { ...current, [activePriority.id]: priorityRows };
            });
            setApplyAll((current) => ({ ...current, is_urgent: '' }));

            return;
        }

        const raw = applyAll[field].trim();
        if (raw === '') {
            return;
        }

        setMatrix((current) => {
            const priorityRows = { ...(current[activePriority.id] ?? {}) };
            for (const type of workOrderTypes) {
                const existing = priorityRows[type.id] ?? emptyCell();
                priorityRows[type.id] = { ...existing, [field]: raw };
            }

            return { ...current, [activePriority.id]: priorityRows };
        });
        setApplyAll((current) => ({ ...current, [field]: '' }));
    }

    async function save() {
        const payload = priorities.flatMap((priority) =>
            workOrderTypes.map((type) => {
                const cell = matrix[priority.id]?.[type.id] ?? emptyCell();

                return {
                    id: cell.id,
                    client_priority_id: priority.id,
                    work_order_type_id: type.id,
                    travel_amount: cell.travel_amount.trim() || '0',
                    extra_travel_amount: cell.extra_travel_amount.trim() || '0',
                    labor_amount: cell.labor_amount.trim() || '0',
                    extra_labor_amount: cell.extra_labor_amount.trim() || '0',
                    due_hours: cell.due_hours.trim() || '0',
                    sla_hours: cell.sla_hours.trim() || '0',
                    is_urgent: cell.is_urgent,
                };
            }),
        );

        setSaving(true);

        try {
            const saved = await syncClientRates(relationshipId, payload);
            setMatrix(buildMatrix(priorities, workOrderTypes, saved));
            pushToast(t('clients.ratesSaved'), 'success');
        } catch (caught) {
            if (caught instanceof ClientRatesApiError) {
                pushToast(caught.message || t('clients.ratesSaveFailed'), 'error');
            } else {
                pushToast(
                    caught instanceof Error ? caught.message : t('clients.ratesSaveFailed'),
                    'error',
                );
            }
        } finally {
            setSaving(false);
        }
    }

    const amountColumns: { field: AmountField; labelKey: string; step: string }[] = [
        { field: 'travel_amount', labelKey: 'clients.ratesTravel', step: '0.01' },
        { field: 'extra_travel_amount', labelKey: 'clients.ratesExtraTravel', step: '0.01' },
        { field: 'labor_amount', labelKey: 'clients.ratesLabor', step: '0.01' },
        { field: 'extra_labor_amount', labelKey: 'clients.ratesExtraLabor', step: '0.01' },
        { field: 'due_hours', labelKey: 'clients.ratesDueHours', step: '1' },
        { field: 'sla_hours', labelKey: 'clients.ratesSlaHours', step: '1' },
    ];

    return (
        <div
            className={cn(
                'space-y-5',
                !embedded && 'rounded-2xl border border-line bg-surface p-6 sm:p-8',
            )}
        >
            {!embedded ? (
                <div>
                    <h2 className="text-base font-semibold text-ink">{t('clients.ratesTabTitle')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('clients.ratesModalDescription')}</p>
                </div>
            ) : (
                <p className="text-sm text-ink-muted">{t('clients.ratesModalDescription')}</p>
            )}

            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : priorities.length === 0 ? (
                <p className="rounded-lg border border-dashed border-line px-3 py-8 text-center text-sm text-ink-muted">
                    {t('clients.ratesSelectPriorities')}
                </p>
            ) : (
                <div className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={saving || priorities.length < 2}
                            onClick={goPrev}
                            aria-label={t('clients.ratesPreviousPriority')}
                        >
                            <ChevronLeft className="size-4" aria-hidden />
                        </Button>
                        <h3
                            className="text-center text-lg font-semibold"
                            style={{ color: activePriority?.color || undefined }}
                        >
                            {activePriority?.name} {activeIndex + 1}/{priorities.length}
                        </h3>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={saving || priorities.length < 2}
                            onClick={goNext}
                            aria-label={t('clients.ratesNextPriority')}
                        >
                            <ChevronRight className="size-4" aria-hidden />
                        </Button>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[56rem] w-full border-collapse text-sm">
                            <thead>
                                <tr className="border-b border-line text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                    <th className="px-2 py-2 text-left font-semibold">
                                        {t('clients.ratesTypology')}
                                    </th>
                                    {amountColumns.map((column) => (
                                        <th key={column.field} className="px-2 py-2 text-center font-semibold">
                                            {t(column.labelKey)}
                                        </th>
                                    ))}
                                    <th className="px-2 py-2 text-center font-semibold">
                                        {t('clients.ratesUrgent')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {canEdit ? (
                                    <tr className="border-b border-line bg-brand-soft/40">
                                        <td className="px-2 py-2" />
                                        {amountColumns.map((column) => (
                                            <td key={column.field} className="px-2 py-2">
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        disabled={saving || applyAll[column.field].trim() === ''}
                                                        onClick={() => applyColumn(column.field)}
                                                        className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand disabled:opacity-40"
                                                        title={t('clients.ratesApplyColumn')}
                                                        aria-label={t('clients.ratesApplyColumn')}
                                                    >
                                                        <ArrowDown className="size-3.5" aria-hidden />
                                                    </button>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step={column.step}
                                                        value={applyAll[column.field]}
                                                        disabled={saving}
                                                        onChange={(event) =>
                                                            setApplyAll((current) => ({
                                                                ...current,
                                                                [column.field]: event.target.value,
                                                            }))
                                                        }
                                                        className="min-w-0"
                                                    />
                                                </div>
                                            </td>
                                        ))}
                                        <td className="px-2 py-2">
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    disabled={saving || applyAll.is_urgent === ''}
                                                    onClick={() => applyColumn('is_urgent')}
                                                    className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand/40 hover:text-brand disabled:opacity-40"
                                                    title={t('clients.ratesApplyColumn')}
                                                    aria-label={t('clients.ratesApplyColumn')}
                                                >
                                                    <ArrowDown className="size-3.5" aria-hidden />
                                                </button>
                                                <Select
                                                    value={applyAll.is_urgent}
                                                    disabled={saving}
                                                    onChange={(event) =>
                                                        setApplyAll((current) => ({
                                                            ...current,
                                                            is_urgent: event.target.value as ApplyAllValues['is_urgent'],
                                                        }))
                                                    }
                                                >
                                                    <option value="" hidden>
                                                        —
                                                    </option>
                                                    <option value="true">{t('common.yes')}</option>
                                                    <option value="false">{t('common.no')}</option>
                                                </Select>
                                            </div>
                                        </td>
                                    </tr>
                                ) : null}

                                {activeCells.map(({ type, cell }) => (
                                    <tr key={type.id} className="border-b border-line">
                                        <td className="px-2 py-2">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="size-8 shrink-0 rounded-md border border-line"
                                                    style={{
                                                        backgroundColor: type.color || 'transparent',
                                                    }}
                                                    aria-hidden
                                                />
                                                <span className="truncate font-medium text-ink">
                                                    {type.name}
                                                </span>
                                            </div>
                                        </td>
                                        {amountColumns.map((column) => (
                                            <td key={column.field} className="px-2 py-2">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step={column.step}
                                                    value={cell[column.field]}
                                                    disabled={!canEdit || saving}
                                                    onChange={(event) =>
                                                        updateCell(activePriority!.id, type.id, {
                                                            [column.field]: event.target.value,
                                                        })
                                                    }
                                                />
                                            </td>
                                        ))}
                                        <td className="px-2 py-2">
                                            <Select
                                                value={cell.is_urgent ? 'true' : 'false'}
                                                disabled={!canEdit || saving}
                                                onChange={(event) =>
                                                    updateCell(activePriority!.id, type.id, {
                                                        is_urgent: event.target.value === 'true',
                                                    })
                                                }
                                            >
                                                <option value="true">{t('common.yes')}</option>
                                                <option value="false">{t('common.no')}</option>
                                            </Select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {canEdit && priorities.length > 0 ? (
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
