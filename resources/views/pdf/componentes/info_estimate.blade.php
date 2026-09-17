<table class="meta-table">
    <tr>
        <td class="meta-col">
            <div class="optima-header">{{ __('pdf.presupuestos.cabecera.cliente', [], $lang) }}</div>
            <div class="t-bold">{{ $billingClient['name'] ?? '' }}</div>
            <div class="line-height-s t-bold">{{ $billingClient['tax_id'] ?? '' }}</div>
            <div class="line-height-s">{{ $billingClient['address'] ?? '' }}</div>
            <div class="line-height-s">
                @if (filled($billingClient['postal_city'] ?? null))
                    {{ $billingClient['postal_city'] }}
                @endif
            </div>
            <div class="line-height-s">{{ $billingClient['country'] ?? '' }}</div>
        </td>
        <td class="meta-gap"></td>
        <td class="meta-col">
            <div class="optima-header">{{ __('pdf.presupuestos.cabecera.datos_presupuesto', [], $lang) }}</div>
            <table class="meta-kv">
                <tr>
                    <td class="t-bold line-height-s meta-kv-k">
                        {{ __('pdf.presupuestos.cabecera.n_presupuesto', [], $lang) }}
                    </td>
                    <td class="line-height-s">{{ $estimateCode }}</td>
                </tr>
                <tr>
                    <td class="t-bold line-height-s meta-kv-k">
                        {{ __('pdf.presupuestos.cabecera.fecha', [], $lang) }}
                    </td>
                    <td class="line-height-s">{{ $estimateDate }}</td>
                </tr>
                <tr>
                    <td class="t-bold line-height-s meta-kv-k">
                        {{ __('pdf.presupuestos.cabecera.tipo_orden', [], $lang) }}
                    </td>
                    <td class="line-height-s">{{ $orderType }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
