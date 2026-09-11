<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Incidencias</title>
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
    table.data td.num { text-align: center; }
    .total { font-weight: bold; background: #F9FAFB; }
    .empty { color: #9CA3AF; font-style: italic; }
</style>
</head>
<body>
    <h1>Hogar de Ancianos — Reporte de Incidencias (Errores y Omisiones)</h1>
    <p class="subtitle">
        Periodo: {{ $periodLabels[$period] }} ({{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }})
        @if($filterResident) — Residente: {{ $filterResident->full_name }} @endif
        @if($filterTypeLabel) — Tipo: {{ $filterTypeLabel }} @endif
    </p>
    <p class="meta">
        Generado el {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy->full_name ?: $generatedBy->email }}.
        Incluye las incidencias que el personal registró al confirmar u omitir una dosis. Una dosis
        programada que nadie registró no aparece aquí (no hay quién la haya reportado): esas se
        detectan en el Reporte de Residente como "dosis sin registro".
    </p>

    <h2>Incidencias por Tipo</h2>
    <table class="data">
        <thead>
            <tr><th>Tipo de incidencia</th><th style="width: 90px;">Cantidad</th></tr>
        </thead>
        <tbody>
            @foreach($byType as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ $row['count'] }}</td>
            </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="num">{{ $incidents->count() }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Detalle de Incidencias</h2>
    @if($incidents->isEmpty())
        <p class="empty">No se registraron incidencias en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora programada</th>
                    <th>Hora registrada</th>
                    <th>Residente</th>
                    <th>Medicamento</th>
                    <th>Tipo de incidencia</th>
                    <th>Descripción / observación</th>
                    <th>Usuario que registra</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incidents as $row)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['scheduled_at'])->format('d/m/Y') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['scheduled_at'])->format('H:i') }}</td>
                    <td>{{ $row['registered_at'] ? \Illuminate\Support\Carbon::parse($row['registered_at'])->format('d/m/Y H:i') : '—' }}</td>
                    <td>{{ $row['resident']?->full_name ?? 'Residente eliminado' }}</td>
                    <td>{{ $row['medication'] ?: '—' }}</td>
                    <td>{{ $row['type_label'] }}</td>
                    <td>{{ $row['description'] ?: '—' }}</td>
                    <td>{{ $row['registered_by']?->full_name ?: ($row['registered_by']?->email ?? '—') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
