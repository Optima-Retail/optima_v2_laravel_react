<html>
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
                    <p class="optima-header">{{ $form->name ?: $subjectLabel }}</p>
                </div>

                <section>
                    <table style="padding: 0; width: 100%;">
                        <tr>
                            <td class="t-bold line-height-s" style="width: fit-content;">Type:</td>
                            <td>{{ $form->type?->name ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="t-bold line-height-s" style="width: fit-content;">Status:</td>
                            <td>{{ $form->status?->name ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="t-bold line-height-s" style="width: fit-content;">Subject:</td>
                            <td>{{ $subjectLabel }}</td>
                        </tr>
                        <tr>
                            <td class="t-bold line-height-s" style="width: fit-content;">Occurred on:</td>
                            <td>{{ $form->occurred_on?->format('d/m/Y') ?? '' }}</td>
                        </tr>
                    </table>
                </section>

                @foreach ($sections as $section)
                    @if (! empty($section['fields']))
                        <section>
                            <p class="optima-header">{{ $section['label'] ?: 'Section' }}</p>
                            <table class="table-data">
                                <tbody>
                                    @foreach ($section['fields'] as $field)
                                        <tr>
                                            <td class="t-bold" style="width: 30%; vertical-align: top;">
                                                {{ $field['label'] ?: '' }}
                                            </td>
                                            <td>
                                                @if ($field['kind'] === 'heading')
                                                    {{-- heading fields carry no value --}}
                                                @elseif ($field['kind'] === 'image')
                                                    @if ($field['image'])
                                                        <img src="{{ $field['image'] }}" style="max-width: 220px; max-height: 220px;" />
                                                    @endif
                                                @elseif ($field['kind'] === 'table')
                                                    <table class="table-data">
                                                        <thead>
                                                            <tr class="fade t-white">
                                                                @foreach ($field['columns'] as $column)
                                                                    <th>{{ $column }}</th>
                                                                @endforeach
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($field['rows'] as $row)
                                                                <tr>
                                                                    @foreach ($row as $cell)
                                                                        <td>{{ $cell }}</td>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @elseif ($field['kind'] === 'work')
                                                    <div>{{ $field['note'] }}</div>
                                                    <table style="width: 100%; margin-top: 6px;">
                                                        <tr>
                                                            <td style="width: 50%;">
                                                                @if ($field['before'])
                                                                    <img src="{{ $field['before'] }}" style="max-width: 200px; max-height: 200px;" />
                                                                @endif
                                                            </td>
                                                            <td style="width: 50%;">
                                                                @if ($field['after'])
                                                                    <img src="{{ $field['after'] }}" style="max-width: 200px; max-height: 200px;" />
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    </table>
                                                @else
                                                    <pre style="white-space: pre-line; word-wrap: break-word; margin: 0; font-family: inherit;">{{ $field['value'] ?? '' }}</pre>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @endif
                @endforeach
            </td>
        </tr>
    </table>
</body>
</html>
