import { useEffect, useState } from 'react';
import { Check, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';

export type EstimateClientRateRow = {
    id: number;
    client_priority_id: number;
    client_priority_label: string;
    work_order_type_id: number;
    work_order_type_label: string;
    travel_amount: string;
    extra_travel_amount: string;
    labor_amount: string;
    extra_labor_amount: string;
    is_urgent: boolean;
};

type EstimateRatesPanelProps = {
    establishmentId: number | null;
    workOrderTypeId: number | null;
};

export function EstimateRatesPanel({ establishmentId, workOrderTypeId }: EstimateRatesPanelProps) {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [rates, setRates] = useState<EstimateClientRateRow[]>([]);
    const [clientName, setClientName] = useState<string | null>(null);

    useEffect(() => {
        if (!establishmentId || !workOrderTypeId) {
            setRates([]);
            setClientName(null);
            setError(null);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setError(null);

            try {
                const params = new URLSearchParams({
                    establishment_id: String(establishmentId),
                    work_order_type_id: String(workOrderTypeId),
                });
                const response = await fetch(`/estimates/client-rates?${params.toString()}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data: EstimateClientRateRow[];
                    client_name?: string | null;
                };
                setRates(payload.data ?? []);
                setClientName(payload.client_name ?? null);
            } catch (caught) {
                if ((caught as Error).name === 'AbortError') {
                    return;
                }
                setError(t('estimates.ratesLoadFailed'));
                setRates([]);
            } finally {
                setLoading(false);
            }
        }, 200);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [establishmentId, workOrderTypeId, t]);

    if (!establishmentId || !workOrderTypeId) {
        return (
            <p className="rounded-xl border border-line bg-canvas/50 px-4 py-3 text-sm text-ink-muted">
                {t('estimates.ratesNeedEstablishmentAndType')}
            </p>
        );
    }

    return (
        <div className="space-y-4">
            <div>
                <h2 className="text-sm font-semibold text-ink">{t('estimates.sectionRates')}</h2>
                <p className="mt-1 text-sm text-ink-muted">{t('estimates.sectionRatesDescription')}</p>
            </div>

            {error ? <p className="text-sm text-danger">{error}</p> : null}

            <div className="overflow-hidden rounded-xl border border-line">
                <table className="min-w-full divide-y divide-line text-sm">
                    <thead className="bg-canvas/60">
                        <tr className="text-left text-ink-muted">
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesPriority')}</th>
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesTravel')}</th>
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesExtraTravel')}</th>
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesLabor')}</th>
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesExtraLabor')}</th>
                            <th className="px-3 py-2 font-semibold">{t('clients.ratesUrgent')}</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line bg-surface">
                        {loading ? (
                            <tr>
                                <td colSpan={6} className="px-3 py-8 text-center text-ink-muted">
                                    {t('common.loading')}
                                </td>
                            </tr>
                        ) : rates.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="px-3 py-8 text-center text-ink-muted">
                                    {clientName
                                        ? t('estimates.ratesEmptyForClient', { client: clientName })
                                        : t('estimates.ratesEmpty')}
                                </td>
                            </tr>
                        ) : (
                            rates.map((rate) => (
                                <tr key={rate.id} className="hover:bg-canvas/40">
                                    <td className="px-3 py-2 font-medium text-ink">{rate.client_priority_label}</td>
                                    <td className="px-3 py-2 tabular-nums">{rate.travel_amount}</td>
                                    <td className="px-3 py-2 tabular-nums">{rate.extra_travel_amount}</td>
                                    <td className="px-3 py-2 tabular-nums">{rate.labor_amount}</td>
                                    <td className="px-3 py-2 tabular-nums">{rate.extra_labor_amount}</td>
                                    <td className="px-3 py-2">
                                        {rate.is_urgent ? (
                                            <Check className={cn('size-4 text-emerald-600')} aria-label={t('common.yes')} />
                                        ) : (
                                            <X className={cn('size-4 text-danger')} aria-label={t('common.no')} />
                                        )}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
