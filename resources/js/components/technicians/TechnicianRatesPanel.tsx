import { useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import {
    TechnicianRatesApiError,
    fetchTechnicianRates,
    upsertTechnicianRates,
    type TechnicianRateRow,
} from '@/services/technicianRates';
import { cn } from '@/support/cn';
import { useToastStore } from '@/stores/toastStore';

type AmountKey = Exclude<keyof TechnicianRateRow, 'id' | 'company_relationship_id'>;
type RateType = 'weekday' | 'night' | 'weekend' | 'holiday' | 'urgent';
type RateCategory = 'labor' | 'travel';

type TechnicianRatesPanelProps = {
    relationshipId: number;
    canEdit: boolean;
    embedded?: boolean;
};

const emptyRates = (): Omit<TechnicianRateRow, 'id' | 'company_relationship_id'> => ({
    labor_weekday_amount: '0',
    labor_night_amount: '0',
    labor_weekend_amount: '0',
    labor_holiday_amount: '0',
    labor_urgent_amount: '0',
    travel_weekday_amount: '0',
    travel_night_amount: '0',
    travel_weekend_amount: '0',
    travel_holiday_amount: '0',
    travel_urgent_amount: '0',
});

const rateTypes: { type: RateType; labelKey: string }[] = [
    { type: 'weekday', labelKey: 'technicians.rates.typeWeekday' },
    { type: 'night', labelKey: 'technicians.rates.typeNight' },
    { type: 'weekend', labelKey: 'technicians.rates.typeWeekend' },
    { type: 'holiday', labelKey: 'technicians.rates.typeHoliday' },
    { type: 'urgent', labelKey: 'technicians.rates.typeUrgent' },
];

const rateRows: { category: RateCategory; labelKey: string }[] = [
    { category: 'labor', labelKey: 'technicians.rates.laborSection' },
    { category: 'travel', labelKey: 'technicians.rates.travelSection' },
];

function fieldKey(category: RateCategory, type: RateType): AmountKey {
    return `${category}_${type}_amount` as AmountKey;
}

export function TechnicianRatesPanel({
    relationshipId,
    canEdit,
    embedded = false,
}: TechnicianRatesPanelProps) {
    const { t } = useTranslation();
    const pushToast = useToastStore((state) => state.push);
    const [values, setValues] = useState(emptyRates());
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        void fetchTechnicianRates(relationshipId)
            .then((row) => {
                if (cancelled) {
                    return;
                }

                setValues({
                    labor_weekday_amount: row.labor_weekday_amount,
                    labor_night_amount: row.labor_night_amount,
                    labor_weekend_amount: row.labor_weekend_amount,
                    labor_holiday_amount: row.labor_holiday_amount,
                    labor_urgent_amount: row.labor_urgent_amount,
                    travel_weekday_amount: row.travel_weekday_amount,
                    travel_night_amount: row.travel_night_amount,
                    travel_weekend_amount: row.travel_weekend_amount,
                    travel_holiday_amount: row.travel_holiday_amount,
                    travel_urgent_amount: row.travel_urgent_amount,
                });
            })
            .catch(() => {
                if (!cancelled) {
                    pushToast(t('technicians.rates.loadFailed'), 'error');
                    setValues(emptyRates());
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

    function update(key: AmountKey, value: string) {
        setValues((current) => ({ ...current, [key]: value }));
    }

    async function save() {
        setSaving(true);

        try {
            const saved = await upsertTechnicianRates(relationshipId, {
                labor_weekday_amount: values.labor_weekday_amount.trim() || '0',
                labor_night_amount: values.labor_night_amount.trim() || '0',
                labor_weekend_amount: values.labor_weekend_amount.trim() || '0',
                labor_holiday_amount: values.labor_holiday_amount.trim() || '0',
                labor_urgent_amount: values.labor_urgent_amount.trim() || '0',
                travel_weekday_amount: values.travel_weekday_amount.trim() || '0',
                travel_night_amount: values.travel_night_amount.trim() || '0',
                travel_weekend_amount: values.travel_weekend_amount.trim() || '0',
                travel_holiday_amount: values.travel_holiday_amount.trim() || '0',
                travel_urgent_amount: values.travel_urgent_amount.trim() || '0',
            });
            setValues({
                labor_weekday_amount: saved.labor_weekday_amount,
                labor_night_amount: saved.labor_night_amount,
                labor_weekend_amount: saved.labor_weekend_amount,
                labor_holiday_amount: saved.labor_holiday_amount,
                labor_urgent_amount: saved.labor_urgent_amount,
                travel_weekday_amount: saved.travel_weekday_amount,
                travel_night_amount: saved.travel_night_amount,
                travel_weekend_amount: saved.travel_weekend_amount,
                travel_holiday_amount: saved.travel_holiday_amount,
                travel_urgent_amount: saved.travel_urgent_amount,
            });
            pushToast(t('technicians.rates.saved'), 'success');
        } catch (caught) {
            if (caught instanceof TechnicianRatesApiError) {
                pushToast(caught.message || t('technicians.rates.saveFailed'), 'error');
            } else {
                pushToast(
                    caught instanceof Error ? caught.message : t('technicians.rates.saveFailed'),
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
                    <h2 className="text-base font-semibold text-ink">{t('technicians.rates.title')}</h2>
                    <p className="mt-1 text-sm text-ink-muted">{t('technicians.rates.description')}</p>
                </div>
            ) : (
                <p className="text-sm text-ink-muted">{t('technicians.rates.description')}</p>
            )}

            {loading ? (
                <p className="text-sm text-ink-muted">{t('common.loading')}</p>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-line">
                    <table className="min-w-[40rem] w-full border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-line bg-canvas text-xs font-semibold uppercase tracking-wide text-ink-muted">
                                <th className="px-3 py-2.5 text-left font-semibold">
                                    {t('technicians.rates.concept')}
                                </th>
                                {rateTypes.map((column) => (
                                    <th key={column.type} className="px-3 py-2.5 text-center font-semibold">
                                        {t(column.labelKey)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rateRows.map((row) => (
                                <tr key={row.category} className="border-b border-line last:border-b-0">
                                    <th
                                        scope="row"
                                        className="whitespace-nowrap px-3 py-2.5 text-left text-sm font-semibold text-ink"
                                    >
                                        {t(row.labelKey)}
                                    </th>
                                    {rateTypes.map((column) => {
                                        const key = fieldKey(row.category, column.type);

                                        return (
                                            <td key={key} className="px-2 py-2 align-middle">
                                                <Input
                                                    id={key}
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    aria-label={`${t(row.labelKey)} — ${t(column.labelKey)}`}
                                                    value={values[key]}
                                                    disabled={!canEdit || saving}
                                                    className="text-center tabular-nums"
                                                    onChange={(event) => update(key, event.target.value)}
                                                />
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
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
