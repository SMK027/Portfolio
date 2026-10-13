<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Recherche de stage</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #1e293b; }
        h1 { font-size: 15pt; margin: 0 0 2pt; }
        h2 { font-size: 11pt; margin: 14pt 0 6pt; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 0.5pt solid #cbd5e1; padding: 3pt 4pt; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; font-size: 7.5pt; }
        tr { page-break-inside: avoid; }
        .nowrap { white-space: nowrap; }
        .pre { white-space: pre-line; }
        .remark { margin-bottom: 6pt; page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Suivi de la recherche de stage</h1>
    <p class="muted">Édité le {{ now()->format('d/m/Y') }} — {{ $contacts->count() }} {{ \Illuminate\Support\Str::plural('contact', $contacts->count()) }}</p>

    @if ($remarks->isNotEmpty())
        <h2>Remarques</h2>
        @foreach ($remarks as $remark)
            <div class="remark"><strong>{{ $remark->noted_on->format('d/m/Y') }}</strong> — <span class="pre">{{ $remark->body }}</span></div>
        @endforeach
    @endif

    <h2>Entreprises contactées</h2>
    @if ($contacts->isEmpty())
        <p class="muted">Aucun contact.</p>
    @else
        <table>
            <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($contacts as $contact)
                    <tr>
                        <td><strong>{{ $contact->company }}</strong></td>
                        <td>{{ $contact->address }}</td>
                        <td>{{ $contact->contact_name }}</td>
                        <td class="nowrap">{{ $contact->contacted_on->format('d/m/Y') }}</td>
                        <td>{{ $contact->methodLabel() }}</td>
                        <td>{{ $contact->statusLabel() }}</td>
                        <td class="nowrap">{{ $contact->follow_up_on?->format('d/m/Y') }}</td>
                        <td>{{ $contact->followUpMethodLabel() }}</td>
                        <td class="pre" style="width: 25%">{{ $contact->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
