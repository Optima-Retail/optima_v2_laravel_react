import { useTranslation } from 'react-i18next';

export type EstablishmentDocumentTotalsData = {
    count: number;
    total_amount: number;
    cost_amount: number;
    margin_percentage: number;
};

type EstablishmentDocumentTotalsProps = {
    totals: EstablishmentDocumentTotalsData;
    showFinancials?: boolean;
};

function formatAmount(value: number, locale: string): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

function formatPercent(value: number, locale: string): string {
    return `${new Intl.NumberFormat(locale, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value)}%`;
}

export function EstablishmentDocumentTotals({
    totals,
    showFinancials = true,
}: EstablishmentDocumentTotalsProps) {
    const { t, i18n } = useTranslation();

    const items = [
        {
            key: 'count',
            label: t('establishments.documentTotals.count'),
            value: String(totals.count),
        },
        {
            key: 'total_amount',
            label: t('establishments.documentTotals.totalAmount'),
            value: formatAmount(totals.total_amount, i18n.language),
        },
        ...(showFinancials
            ? [
                  {
                      key: 'cost_amount',
                      label: t('establishments.documentTotals.totalCost'),
                      value: formatAmount(totals.cost_amount, i18n.language),
                  },
                  {
                      key: 'margin_percentage',
                      label: t('establishments.documentTotals.margin'),
                      value: formatPercent(totals.margin_percentage, i18n.language),
                  },
              ]
            : []),
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            {items.map((item) => (
                <div
                    key={item.key}
                    className="rounded-xl border border-line bg-surface px-4 py-3 shadow-sm"
                >
                    <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">
                        {item.label}
                    </p>
                    <p className="mt-1 text-lg font-semibold text-ink">{item.value}</p>
                </div>
            ))}
        </div>
    );
}
