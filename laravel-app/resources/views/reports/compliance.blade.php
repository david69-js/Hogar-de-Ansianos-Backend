<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Cumplimiento</title>
<style>
    @php $periodLabels = ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año', 'range' => 'Rango personalizado']; @endphp
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #D1D5DB; padding-bottom: 4px; }
    .subtitle { color: #4B5563; margin: 0 0 14px 0; }
    .meta { color: #6B7280; font-size: 10px; margin-bottom: 14px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data th, table.data td { border: 1px solid #E5E7EB; padding: 5px 6px; font-size: 9.5px; text-align: left; vertical-align: top; }
    table.data th { background: #F3F4F6; }
    table.data th.num, table.data td.num { text-align: center; }
    .summary { display: table; width: 100%; margin-bottom: 6px; }
    .summary-cell { display: table-cell; text-align: center; border: 1px solid #E5E7EB; padding: 8px 4px; }
    .summary-cell .n { font-size: 16px; font-weight: bold; display: block; }
    .summary-cell .l { font-size: 9px; color: #6B7280; }
    .low { color: #991B1B; font-weight: bold; }
    .empty { color: #9CA3AF; font-style: italic; }
</style>
</head>
<body>
    @php
        // Menos de 90 % se resalta: es el umbral usual de adherencia aceptable.
        $pct = fn ($row) => $row['adherence'] !== null ? $row['adherence'] . '%' : '—';
        $isLow = fn ($row) => $row['adherence'] !== null && $row['adherence'] < 90;
    @endphp

    <h1>Hogar de Ancianos — Reporte de Administraciones y Cumplimiento</h1>
    <p class="subtitle">
        Periodo: {{ $periodLabels[$period] }} ({{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }})
        @if($filterResident) — Residente: {{ $filterResident->full_name }} @endif
        @if($filterMedication) — Medicamento: {{ $filterMedication->name }} @endif
    </p>
    <p class="meta">
        Generado el {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy->full_name ?: $generatedBy->email }}.
        Programadas = administradas + omitidas + sin registro. "Sin registro" es una dosis ya vencida de
        una prescripción vigente que nadie marcó (ni administrada ni omitida). "A tiempo" = registrada a
        la hora programada; "con retraso" = dentro de la ventana de 15 minutos. Las dosis futuras no se cuentan.
    </p>

    <h2>Resumen General</h2>
    <div class="summary">
        <div class="summary-cell"><span class="n">{{ $summary['expected'] }}</span><span class="l">Total programadas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['administered'] }}</span><span class="l">Total confirmadas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['onTime'] }}</span><span class="l">A tiempo</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['late'] }}</span><span class="l">Con retraso</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['missed'] + $summary['missing'] }}</span><span class="l">Omitidas (incluye sin registro)</span></div>
        <div class="summary-cell"><span class="n {{ $isLow($summary) ? 'low' : '' }}">{{ $pct($summary) }}</span><span class="l">% cumplimiento</span></div>
    </div>

    @foreach([['Cumplimiento por Residente', 'Residente', $byResident], ['Cumplimiento por Medicamento', 'Medicamento', $byMedication]] as [$title, $column, $rows])
        <h2>{{ $title }}</h2>
        @if($rows->isEmpty())
            <p class="empty">No hay dosis programadas en este periodo.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ $column }}</th>
                        <th class="num">Programadas</th>
                        <th class="num">Confirmadas</th>
                        <th class="num">A tiempo</th>
                        <th class="num">Con retraso</th>
                        <th class="num">Omitidas</th>
                        <th class="num">Sin registro</th>
                        <th class="num">% cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ $row['expected'] }}</td>
                        <td class="num">{{ $row['administered'] }}</td>
                        <td class="num">{{ $row['onTime'] }}</td>
                        <td class="num">{{ $row['late'] }}</td>
                        <td class="num">{{ $row['missed'] }}</td>
                        <td class="num">{{ $row['missing'] }}</td>
                        <td class="num {{ $isLow($row) ? 'low' : '' }}">{{ $pct($row) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
