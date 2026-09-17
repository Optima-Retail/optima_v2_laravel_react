<table>
    <tr>
        <td class="optima-logo-cabecera">
            @php
                $logoPath = public_path('img/Header_OptimaRetail_Alta.png');
            @endphp
            @if (is_file($logoPath))
                <img src="data:image/png;base64,{{ base64_encode((string) file_get_contents($logoPath)) }}" alt="Optima Retail" />
            @endif
        </td>
        <td class="optima-info-cabecera">
            <p class="t-bold">{{ $issuerName }}</p>
            @if (filled($issuerTaxId))
                <p class="t-small">{{ $issuerTaxId }}</p>
            @endif
            @if (filled($issuerAddress))
                <p class="t-small">{{ $issuerAddress }}</p>
            @endif
        </td>
    </tr>
</table>
