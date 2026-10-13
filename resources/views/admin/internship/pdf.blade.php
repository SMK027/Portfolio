<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Recherche de stage</title>
    <style>
        @page { margin: 14mm 12mm 16mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #1e293b; line-height: 1.35; }
        h1 { font-size: 17pt; margin: 0; color: #0f172a; }
        h2 { font-size: 10.5pt; margin: 16pt 0 6pt; padding-bottom: 3pt; border-bottom: 1.2pt solid #1e293b; text-transform: uppercase; letter-spacing: 0.5pt; }
        .muted { color: #64748b; }
        .subtitle { margin: 2pt 0 10pt; }

        /* Indicateurs */
        .stats { width: 100%; border-collapse: separate; border-spacing: 5pt 0; margin: 0 -5pt; }
        .stats td { width: 16.6%; border: 0.6pt solid #cbd5e1; border-radius: 4pt; padding: 6pt 8pt; background: #f8fafc; }
        .stats .value { font-size: 15pt; font-weight: bold; color: #0f172a; }
        .stats .label { font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #64748b; }
        .stats .alert .value { color: #b91c1c; }

        /* Remarques */
        .remark { margin: 0 0 6pt; padding: 4pt 0 4pt 8pt; border-left: 2pt solid #cbd5e1; page-break-inside: avoid; }
        .remark.latest { border-left-color: #4f46e5; }
        .remark-date { font-weight: bold; font-size: 7.5pt; color: #475569; }
        .pre { white-space: pre-line; }

        /* Tableau */
        table.contacts { width: 100%; border-collapse: collapse; }
        .contacts th { background: #1e293b; color: #fff; font-size: 7pt; text-align: left; padding: 5pt 4pt; border: 0.5pt solid #1e293b; }
        .contacts td { padding: 4pt; border: 0.5pt solid #cbd5e1; vertical-align: top; }
        .contacts tbody tr:nth-child(even) td { background: #f8fafc; }
        .contacts thead { display: table-header-group; }
        .contacts tr { page-break-inside: avoid; }
        .company { font-weight: bold; color: #0f172a; }
        .nowrap { white-space: nowrap; }
        .center { text-align: center; }
        .pill { display: inline-block; padding: 1.5pt 5pt; border-radius: 6pt; font-size: 7pt; font-weight: bold; white-space: nowrap; }
        .overdue { color: #b91c1c; font-weight: bold; }
        .small { font-size: 6.5pt; }
    </style>
</head>
<body>
    <h1>Suivi de la recherche de stage</h1>
    <p class="muted subtitle">Situation au {{ now()->translatedFormat('j F Y') }}</p>

    <table class="stats">
        <tr>
            @foreach ($summary as $label => $value)
                <td @class(['alert' => $label === 'Relances à faire' && $value > 0])>
                    <div class="value">{{ $value }}</div>
                    <div class="label">{{ $label }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @if ($remarks->isNotEmpty())
        <h2>Remarques sur la recherche</h2>
        @foreach ($remarks as $remark)
            <div @class(['remark', 'latest' => $loop->first])>
                <div class="remark-date">{{ $remark->noted_on->translatedFormat('j F Y') }}</div>
                <div class="pre">{{ $remark->body }}</div>
            </div>
        @endforeach
    @endif

    <h2>Entreprises contactées</h2>
    @if ($contacts->isEmpty())
        <p class="muted">Aucune entreprise contactée pour l'instant.</p>
    @else
        <table class="contacts">
            <thead>
                <tr>
                    <th style="width: 19%">Entreprise / organisme</th>
                    <th style="width: 11%">Interlocuteur</th>
                    <th style="width: 10%">Contact</th>
                    <th style="width: 12%">Résultat</th>
                    <th style="width: 11%">Relance</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($contacts as $contact)
                    @php [, $background, $color] = $contact->statusStyle(); @endphp
                    <tr>
                        <td>
                            <div class="company">{{ $contact->company }}</div>
                            <div class="muted small">{{ $contact->address }}</div>
                        </td>
                        <td>{{ $contact->contact_name ?: '—' }}</td>
                        <td class="nowrap">
                            {{ $contact->contacted_on->format('d/m/Y') }}
                            <div class="muted small">{{ $contact->methodLabel() }}</div>
                        </td>
                        <td><span class="pill" style="background: #{{ $background }}; color: #{{ $color }}">{{ $contact->statusLabel() }}</span></td>
                        <td class="nowrap">
                            @if ($contact->isAwaiting() && $contact->follow_up_on)
                                <span @class(['overdue' => $contact->followUpDue()])>{{ $contact->follow_up_on->format('d/m/Y') }}</span>
                                @if ($contact->followUpMethodLabel())<div class="muted small">{{ $contact->followUpMethodLabel() }}</div>@endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="pre">{{ $contact->notes }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
