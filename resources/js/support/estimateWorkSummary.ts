export type EstimateSummaryLine = {
    quantity: string;
    unit_price: string;
};

export type EstimateSummaryTechnician = {
    is_selected: boolean;
    quote_net_amount: string;
    quote_total_euros: string;
};

export type EstimateWorkSummaryValues = {
    baseImponible: number;
    coste: number;
    margen: number;
    porcentaje: number;
};

function parseAmount(value: string | number | null | undefined): number {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const parsed = typeof value === 'number' ? value : Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

/** Sum of billing lines (qty × unit price). Matches optima_prod base_imponible. */
export function estimateBaseImponible(lines: EstimateSummaryLine[]): number {
    return round2(
        lines.reduce((sum, line) => sum + parseAmount(line.quantity) * parseAmount(line.unit_price), 0),
    );
}

/**
 * Sum of selected technicians' Total € (fallback Base presupuesto).
 * Matches optima_prod precio_compra_euro from presupuestos_solicitados.
 */
export function estimateCoste(technicians: EstimateSummaryTechnician[]): number {
    return round2(
        technicians.reduce((sum, technician) => {
            if (!technician.is_selected) {
                return sum;
            }

            const euros = parseAmount(technician.quote_total_euros);
            if (euros !== 0) {
                return sum + euros;
            }

            return sum + parseAmount(technician.quote_net_amount);
        }, 0),
    );
}

/** Margin % like optima_prod ResumenDeTrabajo: (1 - coste/base) × 100, clamped to ±100. */
export function estimateMarginPercentage(baseImponible: number, coste: number): number {
    if (baseImponible === 0 && coste !== 0) {
        return -100;
    }

    if (baseImponible === 0 && coste === 0) {
        return 0;
    }

    const percentage = round2((1 - coste / baseImponible) * 100);

    if (percentage > 100) {
        return 100;
    }

    if (percentage < -100) {
        return -100;
    }

    return percentage;
}

export function computeEstimateWorkSummary(
    lines: EstimateSummaryLine[],
    technicians: EstimateSummaryTechnician[],
): EstimateWorkSummaryValues {
    const baseImponible = estimateBaseImponible(lines);
    const coste = estimateCoste(technicians);
    const margen = round2(baseImponible - coste);

    return {
        baseImponible,
        coste,
        margen,
        porcentaje: estimateMarginPercentage(baseImponible, coste),
    };
}

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

export function formatEuroAmount(value: number): string {
    return value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
