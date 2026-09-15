import { useTranslation } from 'react-i18next';
import { cn } from '@/support/cn';
import {
    computeEstimateWorkSummary,
    formatEuroAmount,
    type EstimateSummaryLine,
    type EstimateSummaryTechnician,
} from '@/support/estimateWorkSummary';

type EstimateWorkSummaryProps = {
    lines: EstimateSummaryLine[];
    technicians: EstimateSummaryTechnician[];
    className?: string;
};

export function EstimateWorkSummary({ lines, technicians, className }: EstimateWorkSummaryProps) {
    const { t } = useTranslation();
    const summary = computeEstimateWorkSummary(lines, technicians);
    const marginTone =
        summary.porcentaje > 0 ? 'text-success' : summary.porcentaje < 0 ? 'text-danger' : 'text-ink';

    const items = [
        {
            key: 'base',
            label: t('estimates.workSummaryBase'),
            value: `${formatEuroAmount(summary.baseImponible)} €`,
            valueClassName: 'text-ink',
        },
        {
            key: 'cost',
            label: t('estimates.workSummaryCost'),
            value: `${formatEuroAmount(summary.coste)} €`,
            valueClassName: 'text-ink',
        },
        {
            key: 'margin',
            label: t('estimates.workSummaryMargin'),
            value: `${formatEuroAmount(summary.margen)} €`,
            valueClassName: marginTone,
        },
        {
            key: 'percentage',
            label: t('estimates.workSummaryPercentage'),
            value: `${formatEuroAmount(summary.porcentaje)} %`,
            valueClassName: marginTone,
        },
    ] as const;

    return (
        <div
            className={cn(
                'rounded-xl border border-line bg-canvas/60 px-1 py-1 backdrop-blur-sm',
                className,
            )}
            aria-label={t('estimates.workSummaryLabel')}
        >
            <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-lg bg-line sm:grid-cols-4">
                {items.map((item) => (
                    <div key={item.key} className="min-w-[7.5rem] bg-surface px-3.5 py-2.5">
                        <dt className="text-[0.65rem] font-medium uppercase tracking-[0.08em] text-ink-muted">
                            {item.label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-1 font-display text-sm font-semibold tabular-nums tracking-tight',
                                item.valueClassName,
                            )}
                        >
                            {item.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
