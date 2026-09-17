<html>
@php
    $isGreek = $lang === 'el';
    $labelWidth = $isGreek ? 'width: 20%' : 'width: fit-content';
@endphp
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    @include('pdf.style')
</head>
<body>
    {{-- DomPDF: table cell padding is the reliable way to create page margins --}}
    <table class="page-shell" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td class="page-pad">
                <div class="pdf-top">
                    @include('pdf.componentes.header', [
                        'issuerName' => $issuer['name'] ?? '',
                        'issuerTaxId' => $issuer['tax_id'] ?? '',
                        'issuerAddress' => $issuer['address'] ?? '',
                    ])
                </div>

                <div class="pdf-meta">
                    @include('pdf.componentes.info_estimate', [
                        'lang' => $lang,
                        'billingClient' => $billingClient,
                        'estimateCode' => $estimateCode,
                        'estimateDate' => $estimateDate,
                        'orderType' => $orderType,
                    ])
                </div>

                <section>
                    <p class="optima-header">
                        {{ __('pdf.presupuestos.resumen_presupuesto.resumen_presupuesto', [], $lang) }}
                    </p>
                    <table style="padding: 0; width: 100%;">
                        <tr>
                            <td class="t-bold line-height-s" style="{{ $labelWidth }}">
                                {{ __('pdf.presupuestos.resumen_presupuesto.asunto_resupuesto', [], $lang) }}:
                            </td>
                            <td>{{ $subject }}</td>
                        </tr>
                        <tr>
                            <td class="t-bold line-height-s" style="{{ $labelWidth }}">
                                {{ __('pdf.presupuestos.resumen_presupuesto.nombre_establecimiento', [], $lang) }}:
                            </td>
                            <td>{{ $establishmentName }}</td>
                        </tr>
                        <tr>
                            <td class="t-bold line-height-s" style="{{ $labelWidth }}">
                                {{ __('pdf.presupuestos.resumen_presupuesto.codigo_establecimiento', [], $lang) }}:
                            </td>
                            <td>{{ $establishmentCode }}</td>
                        </tr>
                    </table>
                </section>

                @if ($tasks->isNotEmpty())
                    <section>
                        <p class="optima-header">
                            {{ __('pdf.presupuestos.descripcion.descripcion_presupuesto', [], $lang) }}
                        </p>
                        <div class="description">
                            @foreach ($tasks as $task)
                                <div class="t-bold">{{ $task->title }}</div>
                                @if (filled($task->description))
                                    <div>{{ $task->description }}</div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <p class="optima-header">
                        {{ __('pdf.presupuestos.detalle.detalle_economico', [], $lang) }}
                    </p>
                    <table class="table-data">
                        <thead>
                            <tr class="fade t-white">
                                <th>{{ __('pdf.presupuestos.detalle.codigo', [], $lang) }}</th>
                                <th>{{ __('pdf.presupuestos.detalle.descripcion', [], $lang) }}</th>
                                <th>{{ __('pdf.presupuestos.detalle.cantidad', [], $lang) }}</th>
                                <th>{{ __('pdf.presupuestos.detalle.precio_unidad', [], $lang) }}</th>
                                <th>{{ __('pdf.presupuestos.detalle.importe', [], $lang) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lineRows as $row)
                                <tr>
                                    @if ($row['is_chapter'])
                                        <td colspan="5" class="t-bold t-big" style="padding: 0.75rem; padding-left: 2rem;">
                                            {{ $row['description'] }}
                                        </td>
                                    @else
                                        <td>{{ $row['article_label'] }}</td>
                                        <td style="max-width: 300px; vertical-align: top;">
                                            <pre style="white-space: pre-line; word-wrap: break-word; overflow-wrap: break-word; margin: 0; font-family: inherit;">{{ $row['description'] }}</pre>
                                        </td>
                                        <td>{{ $row['quantity'] }}</td>
                                        <td>{{ $row['unit_price'] }}{{ $currencySymbol }}</td>
                                        <td>{{ $row['amount'] }}{{ $currencySymbol }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <section>
                    <p class="optima-header">
                        {{ __('pdf.presupuestos.resumen_economico.resumen_economico', [], $lang) }}
                    </p>
                    <div class="t-small">{{ __('pdf.presupuestos.resumen_economico.texto', [], $lang) }}</div>
                    <div>
                        <table class="table-data">
                            <thead>
                                <tr class="fade t-white">
                                    <th>{{ __('pdf.presupuestos.resumen_economico.base_imponible', [], $lang) }}</th>
                                    <th>{{ __('pdf.presupuestos.resumen_economico.iva', [], $lang) }}</th>
                                    <th>{{ __('pdf.presupuestos.resumen_economico.total', [], $lang) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ number_format($netAmount, 2, '.', '') }}{{ $currencySymbol }}</td>
                                    <td>{{ number_format($taxRate, 2, '.', '') }}%</td>
                                    <td>{{ number_format($totalAmount, 2, '.', '') }}{{ $currencySymbol }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <table class="table-data border-top" style="border-top-color: #d0d0d0">
                        <tr>
                            <th class="fade t-white" style="width: 25%">
                                {{ __('pdf.presupuestos.responsable', [], $lang) }}
                            </th>
                            <td style="width: 25%">{{ $responsibleName }}</td>
                            <th class="fade t-white" style="width: 25%">
                                {{ __('pdf.presupuestos.cliente', [], $lang) }}
                            </th>
                            <td style="width: 25%">{{ $clientName }}</td>
                        </tr>
                        <tr>
                            <th class="fade t-white t-top" style="height: 50px">
                                {{ __('pdf.presupuestos.firma', [], $lang) }}
                            </th>
                            <td></td>
                            <th class="fade t-white t-top" style="height: 50px">
                                {{ __('pdf.presupuestos.firma', [], $lang) }}
                            </th>
                            <td></td>
                        </tr>
                    </table>
                </section>
            </td>
        </tr>
    </table>

    <footer>
        <div class="footer-description">
            <div style="text-align: center; padding: 2px">
                {{ __('pdf.presupuestos.footer.footer1', [], $lang) }}
            </div>
            <div style="text-align: justify; padding: 2px">
                {{ __('pdf.presupuestos.footer.footer2', [], $lang) }}
            </div>
        </div>
    </footer>
</body>
</html>
