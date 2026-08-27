<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Actividad de Enfermería</title>
<style>
    @php $periodLabels = ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año', 'range' => 'Rango personalizado']; @endphp
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #D1D5DB; padding-bottom: 4px; }
    .subtitle { color: #4B5563; margin: 0 0 14px 0; }
    .meta { color: #6B7280; font-size: 10px; margin-bottom: 14px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data th, table.data td { border: 1px solid #E5E7EB; padding: 5px 6px; font-size: 10px; text-align: left; }
    table.data th { background: #F3F4F6; }
    .summary { display: table; width: 100%; margin-bottom: 6px; }
    .summary-cell { display: table-cell; width: 25%; text-align: center; border: 1px solid #E5E7EB; padding: 8px 4px; }
    .summary-cell .n { font-size: 16px; font-weight: bold; display: block; }
    .summary-cell .l { font-size: 9px; color: #6B7280; }
    .empty { color: #9CA3AF; font-style: italic; }
</style>
</head>
<body>
    <h1>Hogar de Ancianos — Reporte de Actividad de Enfermería</h1>
    <p class="subtitle">
        {{ $nurse->full_name ?: $nurse->email }} — Periodo: {{ $periodLabels[$period] }}
        ({{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }})
    </p>
    <p class="meta">
        Generado el {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy->full_name ?: $generatedBy->email }}.
        Este reporte solo incluye las dosis que {{ $nurse->full_name ?: 'esta persona' }} registró
        personalmente (administradas u omitidas) — el sistema no asigna turnos por residente, así que no
        se le pueden atribuir dosis que otra persona dejó sin registrar.
    </p>

    <h2>Resumen del Periodo</h2>
    <div class="summary">
        <div class="summary-cell"><span class="n">{{ $summary['administered'] }}</span><span class="l">Dosis administradas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['missed'] }}</span><span class="l">Dosis omitidas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['residentsAttended'] }}</span><span class="l">Residentes atendidos</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['residentsWithOmissions'] }}</span><span class="l">Residentes con omisión</span></div>
    </div>

    <h2>Residentes Atendidos</h2>
    @if($residentSummaries->isEmpty())
        <p class="empty">No hay actividad registrada en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Residente</th>
                    <th>Habitación</th>
                    <th>Dosis administradas</th>
                    <th>Dosis omitidas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($residentSummaries as $row)
                <tr>
                    <td>{{ $row['resident']?->full_name ?? 'Residente eliminado' }}</td>
                    <td>{{ $row['resident']?->room_number ?? '—' }}</td>
                    <td>{{ $row['administered'] }}</td>
                    <td>{{ $row['missed'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Detalle de Omisiones</h2>
    @if($missedDetails->isEmpty())
        <p class="empty">No registró ninguna omisión en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Residente</th>
                    <th>Medicamento</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($missedDetails as $detail)
                @php $scheduled = \Illuminate\Support\Carbon::parse($detail['scheduled_at']); @endphp
                <tr>
                    <td>{{ $scheduled->format('d/m/Y') }}</td>
                    <td>{{ $scheduled->format('H:i') }}</td>
                    <td>{{ $detail['resident']?->full_name ?? 'Residente eliminado' }}</td>
                    <td>{{ $detail['medication'] ?? '—' }}</td>
                    <td>{{ $detail['reason'] ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
