{{-- RF_70 · PDF layout of every report (dompdf: plain HTML + CSS 2.1, DejaVu Sans for Spanish accents). --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 90px 32px 50px 32px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #18181b; }
        header { position: fixed; top: -70px; left: 0; right: 0; }
        footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #71717a; }
        h1 { font-size: 15px; margin: 0 0 4px 0; }
        .meta { color: #52525b; margin: 0; }
        .brand { color: #2563eb; font-weight: bold; font-size: 10px; margin: 0 0 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f4f4f5; text-align: left; font-weight: bold; }
        th, td { border-bottom: 1px solid #e4e4e7; padding: 4px 5px; vertical-align: top; }
        tbody tr:nth-child(even) td { background: #f4f4f5; }
        tr { page-break-inside: avoid; }
        .filters { margin: 0 0 10px 0; color: #3f3f46; }
        .empty { color: #71717a; padding: 12px 0; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <header>
        <p class="brand">{{ config('app.name') }}</p>
        <h1>{{ $document->title }}</h1>
        <p class="meta">
            {{ __('reports.document.generated_at') }}: {{ $document->generatedAt->format('d/m/Y H:i') }}
            · {{ __('reports.document.generated_by') }}: {{ $document->generatedBy }}
            · {{ __('reports.document.records') }}: {{ count($document->rows) }}
        </p>
    </header>

    <footer>
        {{ config('app.name') }} · {{ $document->title }} · {{ __('reports.document.page') }} <span class="page-number"></span>
    </footer>

    <p class="filters">
        <strong>{{ __('reports.document.filters') }}:</strong>
        @forelse ($document->filters as $label => $value)
            {{ $label }}: {{ $value }}@if (! $loop->last); @endif
        @empty
            {{ __('reports.document.all_records') }}
        @endforelse
    </p>

    @if ($document->rows === [])
        <p class="empty">{{ __('reports.document.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($document->headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($document->rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
