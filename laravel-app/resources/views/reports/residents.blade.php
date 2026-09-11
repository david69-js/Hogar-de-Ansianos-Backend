<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Listado de Residentes</title>
<style>
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
    .summary-cell { display: table-cell; width: 25%; text-align: center; border: 1px solid #E5E7EB; padding: 8px 4px; }
    .summary-cell .n { font-size: 16px; font-weight: bold; display: block; }
    .summary-cell .l { font-size: 9px; color: #6B7280; }
    .meds { color: #6B7280; font-size: 8.5px; }
    .none { color: #991B1B; font-weight: bold; }
    .empty { color: #9CA3AF; font-style: italic; }
</style>
</head>
<body>
    @php
        $statusLabels = ['active' => 'Solo activos', 'inactive' => 'Solo inactivos'];
        $treatmentLabels = ['yes' => 'Con tratamiento activo', 'no' => 'Sin tratamiento activo'];
    @endphp

    <h1>Hogar de Ancianos — Listado General de Residentes y Tratamientos Activos</h1>
    <p class="subtitle">
        Situación al {{ $generatedAt->format('d/m/Y') }}
        — Estado: {{ $statusLabels[$filterStatus] ?? 'Todos' }}
        — Tratamiento: {{ $treatmentLabels[$filterTreatment] ?? 'Todos' }}
    </p>
    <p class="meta">
        Generado el {{ $generatedAt->format('d/m/Y H:i') }} por {{ $generatedBy->full_name ?: $generatedBy->email }}.
        Un tratamiento activo es una prescripción no descontinuada cuya fecha de fin no ha pasado.
        La enfermera responsable la asigna la administradora; el encargado es el contacto de emergencia
        registrado en la ficha del residente.
    </p>

    <h2>Resumen</h2>
    <div class="summary">
        <div class="summary-cell"><span class="n">{{ $summary['total'] }}</span><span class="l">Residentes en el listado</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['active'] }}</span><span class="l">Activos</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['inactive'] }}</span><span class="l">Inactivos</span></div>
        <div class="summary-cell"><span class="n">{{ $summary['withTreatment'] }}</span><span class="l">Con tratamiento activo</span></div>
    </div>

    <h2>Residentes</h2>
    @if($residents->isEmpty())
        <p class="empty">No hay residentes que cumplan los filtros.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Expediente</th>
                    <th>Residente</th>
                    <th>DPI</th>
                    <th>Habitación</th>
                    <th>Fecha de ingreso</th>
                    <th>Estado</th>
                    <th class="num">Tratamientos activos</th>
                    <th>Enfermera responsable</th>
                    <th>Encargado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($residents as $resident)
                <tr>
                    <td>{{ str_pad((string) $resident->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>
                        {{ $resident->full_name ?: '—' }}
                        @if($resident->prescriptions->isNotEmpty())
                            <br><span class="meds">{{ $resident->prescriptions->map(fn ($p) => trim(($p->medication?->name ?? 'Medicamento') . ' ' . ($p->dosage ?? '')))->join(', ') }}</span>
                        @endif
                    </td>
                    <td>{{ $resident->dpi ?: '—' }}</td>
                    <td>{{ $resident->room_number ?: '—' }}</td>
                    <td>{{ $resident->admission_date ? \Illuminate\Support\Carbon::parse($resident->admission_date)->format('d/m/Y') : '—' }}</td>
                    <td>{{ $resident->trashed() ? 'Inactivo' : 'Activo' }}</td>
                    <td class="num {{ $resident->prescriptions->isEmpty() && !$resident->trashed() ? 'none' : '' }}">{{ $resident->prescriptions->count() }}</td>
                    <td>{{ $resident->assignedNurse?->full_name ?: 'Sin asignar' }}</td>
                    <td>
                        @if($resident->emergency_contact_name)
                            {{ $resident->emergency_contact_name }}@if($resident->emergency_contact_relation) ({{ $resident->emergency_contact_relation }})@endif
                            @if($resident->emergency_contact_phone)<br>{{ $resident->emergency_contact_phone }}@endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
