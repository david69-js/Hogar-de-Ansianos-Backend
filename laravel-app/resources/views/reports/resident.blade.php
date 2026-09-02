<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte de Residente</title>
<style>
    @php $periodLabels = ['day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año', 'range' => 'Rango personalizado']; @endphp
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 18px; margin: 0 0 4px 0; }
    h2 { font-size: 13px; margin: 18px 0 6px 0; border-bottom: 1px solid #D1D5DB; padding-bottom: 4px; }
    .subtitle { color: #4B5563; margin: 0 0 14px 0; }
    .meta { color: #6B7280; font-size: 10px; margin-bottom: 14px; }
    .info-box { background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 6px; padding: 10px 14px; margin-bottom: 6px; }
    .info-box table { width: 100%; }
    .info-box td { padding: 2px 6px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    table.data th, table.data td { border: 1px solid #E5E7EB; padding: 5px 6px; font-size: 10px; text-align: left; }
    table.data th { background: #F3F4F6; }
    .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: bold; }
    .badge-ok { background: #ECFDF5; color: #065F46; }
    .badge-bad { background: #FEF2F2; color: #991B1B; }
    .badge-warn { background: #FEF3C7; color: #92400E; }
    .summary { display: table; width: 100%; margin-bottom: 6px; }
    .summary-cell { display: table-cell; width: 20%; text-align: center; border: 1px solid #E5E7EB; padding: 8px 4px; }
    .summary-cell .n { font-size: 16px; font-weight: bold; display: block; }
    .summary-cell .l { font-size: 9px; color: #6B7280; }
    .empty { color: #9CA3AF; font-style: italic; }
</style>
</head>
<body>
    <h1>Hogar de Ancianos — Reporte de Medicación por Residente</h1>
    <p class="subtitle">
        Periodo: {{ $periodLabels[$period] }} — del {{ $start->format('d/m/Y') }} al {{ $end->format('d/m/Y') }}
    </p>
    <p class="meta">
        Generado el {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy->full_name ?: $generatedBy->email }}
    </p>

    <h2>Datos del Residente</h2>
    <div class="info-box">
        <table>
            <tr>
                <td><strong>Nombre:</strong> {{ $resident->full_name ?: '—' }}</td>
                <td><strong>DPI:</strong> {{ $resident->dpi ?: '—' }}</td>
            </tr>
            <tr>
                <td><strong>Habitación:</strong> {{ $resident->room_number ?: '—' }}</td>
                <td><strong>Fecha de nacimiento:</strong> {{ $resident->birth_date ? \Illuminate\Support\Carbon::parse($resident->birth_date)->format('d/m/Y') : '—' }}</td>
            </tr>
        </table>
    </div>

    <h2>Resumen del Periodo</h2>
    <div class="summary">
        <div class="summary-cell"><span class="n">{{ $summary['expected'] }}</span><span class="l">Dosis esperadas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['administered'] }}</span><span class="l">Administradas</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['missed'] }}</span><span class="l">Omitidas (registradas)</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['missing'] }}</span><span class="l">Sin registro</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['adherence'] !== null ? $summary['adherence'].'%' : '—' }}</span><span class="l">Adherencia</span></div>
    </div>
    <div class="summary" style="margin-top: 4px;">
        <div class="summary-cell" style="width: 50%;"><span class="n">{{ $summary['onTime'] }}</span><span class="l">Administradas a tiempo (en el horario)</span></div>
        <div class="summary-cell" style="width: 50%;"><span class="n">{{ $summary['late'] }}</span><span class="l">Administradas con retraso (dentro de la ventana de 15 min)</span></div>
    </div>

    <h2>Prescripciones Vigentes en el Periodo</h2>
    @if($prescriptions->isEmpty())
        <p class="empty">No hay prescripciones vigentes en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Medicamento</th>
                    <th>Dosis</th>
                    <th>Frecuencia</th>
                    <th>Vía</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Prescrito por</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prescriptions as $prescription)
                <tr>
                    <td>{{ $prescription->medication?->name ?? '—' }}</td>
                    <td>{{ $prescription->dosage ?: '—' }}</td>
                    <td>{{ $prescription->frequency ?: '—' }}</td>
                    <td>{{ $prescription->administration_route ?: '—' }}</td>
                    <td>{{ $prescription->start_date ? \Illuminate\Support\Carbon::parse($prescription->start_date)->format('d/m/Y') : '—' }}</td>
                    <td>{{ $prescription->end_date ? \Illuminate\Support\Carbon::parse($prescription->end_date)->format('d/m/Y') : 'Indefinido' }}</td>
                    <td>{{ $prescription->creator?->full_name ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Registro de Dosis</h2>
    @if($logs->isEmpty())
        <p class="empty">No hay dosis registradas en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora programada</th>
                    <th>Hora administrada</th>
                    <th>Medicamento</th>
                    <th>Estado</th>
                    <th>Responsable</th>
                    <th>Motivo (si omitida)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                @php $scheduled = \Illuminate\Support\Carbon::parse($log->scheduled_time); @endphp
                <tr>
                    <td>{{ $scheduled->format('d/m/Y') }}</td>
                    <td>{{ $scheduled->format('H:i') }}</td>
                    <td>{{ $log->administered_time ? \Illuminate\Support\Carbon::parse($log->administered_time)->format('H:i') : '—' }}</td>
                    <td>{{ $log->schedule?->prescription?->medication?->name ?? '—' }}</td>
                    <td>
                        @if($log->status === 'administered')
                            <span class="badge badge-ok">Administrada</span>
                        @else
                            <span class="badge badge-bad">Omitida</span>
                        @endif
                    </td>
                    <td>{{ $log->administeredBy?->full_name ?? 'Sin identificar' }}</td>
                    <td>{{ $log->status === 'missed' ? ($log->reason_for_omission ?: '—') : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Dosis Programadas Sin Registro</h2>
    <p class="meta">
        Dosis que ya debieron administrarse según el horario de la prescripción, pero nadie las marcó
        como administradas ni como omitidas — no tienen responsable identificable.
    </p>
    @if($missingDoses->isEmpty())
        <p class="empty">No hay dosis sin registro en este periodo.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora programada</th>
                    <th>Medicamento</th>
                    <th>Dosis</th>
                </tr>
            </thead>
            <tbody>
                @foreach($missingDoses as $dose)
                <tr>
                    <td>{{ $dose['scheduled_at']->format('d/m/Y') }}</td>
                    <td>{{ $dose['scheduled_at']->format('H:i') }}</td>
                    <td>{{ $dose['medication'] ?? '—' }}</td>
                    <td>{{ $dose['dosage'] ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
