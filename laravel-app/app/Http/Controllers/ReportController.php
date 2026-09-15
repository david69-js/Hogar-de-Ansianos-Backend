<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\Prescription;
use App\Models\Resident;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Genera los reportes en PDF (dompdf): por residente (medicación, omisiones y
 * responsable de cada dosis), por enfermera (a quién atendió, a quién omitió),
 * de incidencias, de cumplimiento (todo el hogar) y el listado de residentes con
 * sus tratamientos activos. Todos menos el listado aceptan el mismo filtro de
 * periodo — día/semana/mes/año o rango — resuelto en resolveDateRange().
 * Protegido por `view_reports` (Admin y Enfermera); nursePdf() además exige que
 * solo Admin pueda pedir el
 * reporte de otra persona (una enfermera solo ve el suyo). No persiste nada:
 * cada llamada arma el PDF al vuelo y lo devuelve como stream.
 */
class ReportController extends Controller
{
    // GET /api/reports/residents/{id}/medications
    public function residentMedicationPdf(Request $request, $id)
    {
        $resident = Resident::with('assignedNurse')->findOrFail($id);
        [$start, $end, $period] = $this->resolveDateRange($request);

        $prescriptions = $resident->prescriptions()
            ->with(['medication', 'creator', 'schedules'])
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $end))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->get();

        $logs = MedicationLog::whereHas('schedule.prescription', fn ($q) => $q->where('resident_id', $resident->id))
            ->whereBetween('scheduled_time', [$start, $end])
            ->with(['schedule.prescription.medication', 'administeredBy'])
            ->orderBy('scheduled_time')
            ->get();

        // Solo las prescripciones vigentes generan "dosis faltante": destroy() de
        // PrescriptionController descontinúa con is_active=false SIN tocar end_date
        // (no registra en qué fecha exacta se descontinuó), así que una prescripción
        // inactiva sin end_date seguiría contando como vigente hasta $end si no se
        // excluye aquí — inventando omisiones de un medicamento que ya se detuvo.
        // $prescriptions (con las inactivas) se sigue mostrando completo en la tabla
        // de medicación del PDF: eso sí debe reflejar el historial real.
        $missingDoses = $this->findMissingDoses($prescriptions->where('is_active', true), $logs, $start, $end);

        $administeredLogs = $logs->where('status', 'administered');
        $administeredCount = $administeredLogs->count();
        $missedCount = $logs->where('status', 'missed')->count();
        $totalExpected = $administeredCount + $missedCount + $missingDoses->count();

        // "A tiempo" = administrada en el horario programado (delay_minutes <= 0);
        // "con retraso" = administrada después, pero siempre dentro de la ventana de
        // quince minutos que permite el sistema (después de eso solo se puede omitir).
        $onTimeCount = $administeredLogs->where('delay_minutes', '<=', 0)->count();
        $lateCount = $administeredCount - $onTimeCount;

        $pdf = Pdf::loadView('reports.resident', [
            'resident' => $resident,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'prescriptions' => $prescriptions,
            'logs' => $logs,
            'missingDoses' => $missingDoses,
            'summary' => [
                'expected' => $totalExpected,
                'administered' => $administeredCount,
                'onTime' => $onTimeCount,
                'late' => $lateCount,
                'missed' => $missedCount,
                'missing' => $missingDoses->count(),
                'adherence' => $totalExpected > 0 ? round($administeredCount / $totalExpected * 100, 1) : null,
            ],
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ]);

        return $pdf->stream("reporte-residente-{$resident->id}.pdf");
    }

    // GET /api/reports/nurses/{id}/activity
    public function nursePdf(Request $request, $id)
    {
        $nurse = User::findOrFail($id);

        // Hoy la ruta ya exige view_management_reports (solo Admin), pero el resguardo se
        // mantiene: si ese permiso se le diera a una supervisora que no es Admin, seguiría
        // sin poder pedir el reporte de otra persona, solo el propio.
        if (!$request->user()->hasRole('Admin') && (int) $request->user()->id !== (int) $id) {
            abort(403, 'No autorizado para ver el reporte de otro usuario.');
        }

        [$start, $end, $period] = $this->resolveDateRange($request);

        $logs = MedicationLog::where('administered_by', $nurse->id)
            ->whereBetween('scheduled_time', [$start, $end])
            ->with(['schedule.prescription.resident', 'schedule.prescription.medication'])
            ->orderBy('scheduled_time')
            ->get();

        $residentSummaries = $logs
            ->groupBy(fn ($log) => $log->schedule?->prescription?->resident_id)
            ->filter(fn ($_, $residentId) => $residentId !== null)
            ->map(function (Collection $residentLogs) {
                return [
                    'resident' => $residentLogs->first()->schedule?->prescription?->resident,
                    'administered' => $residentLogs->where('status', 'administered')->count(),
                    'missed' => $residentLogs->where('status', 'missed')->count(),
                ];
            })
            ->values();

        $missedDetails = $logs->where('status', 'missed')->map(fn ($log) => [
            'scheduled_at' => $log->scheduled_time,
            'resident' => $log->schedule?->prescription?->resident,
            'medication' => $log->schedule?->prescription?->medication?->name,
            'reason' => $log->reason_for_omission,
        ])->values();

        // Asignación vigente (no histórica: la tabla guarda solo la actual).
        $assignedResidents = Resident::where('assigned_nurse_id', $nurse->id)
            ->orderBy('first_name')->orderBy('last_name')->get();

        $pdf = Pdf::loadView('reports.nurse', [
            'nurse' => $nurse,
            'assignedResidents' => $assignedResidents,
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'residentSummaries' => $residentSummaries,
            'missedDetails' => $missedDetails,
            'summary' => [
                'administered' => $logs->where('status', 'administered')->count(),
                'missed' => $logs->where('status', 'missed')->count(),
                'residentsAttended' => $residentSummaries->where('administered', '>', 0)->count(),
                'residentsWithOmissions' => $residentSummaries->where('missed', '>', 0)->count(),
            ],
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ]);

        return $pdf->stream("reporte-enfermeria-{$nurse->id}.pdf");
    }

    // GET /api/reports/incidents?period=month&date=...&[resident_id]&[incident_type]
    //
    // Reporte Mensual de Incidencias (Errores y Omisiones) de la tesis: todas las
    // incidencias del periodo clasificadas por tipo, con filtros opcionales por
    // residente y por tipo. Acepta cualquier periodo (no solo mes) reutilizando
    // resolveDateRange(), igual que los otros dos reportes.
    public function incidentsPdf(Request $request)
    {
        [$start, $end, $period] = $this->resolveDateRange($request);

        $filters = $request->validate([
            'resident_id' => 'nullable|exists:residents,id',
            'incident_type' => ['nullable', Rule::in(array_keys(MedicationLog::INCIDENT_TYPES))],
        ]);

        $query = MedicationLog::query()
            // Una omisión registrada antes de que existiera incident_type también
            // es una incidencia: se incluye por status aunque la columna esté vacía.
            ->where(fn ($q) => $q->whereNotNull('incident_type')->orWhere('status', 'missed'))
            ->whereBetween('scheduled_time', [$start, $end])
            ->with(['schedule.prescription.resident', 'schedule.prescription.medication', 'administeredBy'])
            ->orderBy('scheduled_time');

        if (!empty($filters['incident_type'])) {
            $type = $filters['incident_type'];
            $query->where(fn ($q) => $type === 'omision'
                ? $q->where('incident_type', 'omision')->orWhere('status', 'missed')
                : $q->where('incident_type', $type));
        }

        if (!empty($filters['resident_id'])) {
            $query->whereHas('schedule.prescription', fn ($q) => $q->where('resident_id', $filters['resident_id']));
        }

        $incidents = $query->get()->map(function (MedicationLog $log) {
            $type = $log->incident_type ?? 'omision';
            $prescription = $log->schedule?->prescription;

            return [
                'type' => $type,
                'type_label' => MedicationLog::INCIDENT_TYPES[$type] ?? $type,
                'scheduled_at' => $log->scheduled_time,
                // "Hora registrada": cuándo se aplicó la dosis o, si se omitió,
                // cuándo se dejó constancia de la omisión.
                'registered_at' => $log->administered_time ?? $log->created_at,
                'resident' => $prescription?->resident,
                'medication' => trim(($prescription?->medication?->name ?? '') . ' ' . ($prescription?->dosage ?? '')),
                'description' => $log->status === 'missed' ? $log->reason_for_omission : $log->notes,
                'registered_by' => $log->administeredBy,
            ];
        });

        $byType = collect(MedicationLog::INCIDENT_TYPES)
            ->map(fn ($label, $key) => ['label' => $label, 'count' => $incidents->where('type', $key)->count()])
            ->values();

        $pdf = Pdf::loadView('reports.incidents', [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'incidents' => $incidents,
            'byType' => $byType,
            'filterResident' => !empty($filters['resident_id']) ? Resident::find($filters['resident_id']) : null,
            'filterTypeLabel' => !empty($filters['incident_type']) ? MedicationLog::INCIDENT_TYPES[$filters['incident_type']] : null,
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ]);

        return $pdf->stream('reporte-incidencias.pdf');
    }

    // GET /api/reports/compliance?period=month&date=...&[resident_id]&[medication_id]
    //
    // Reporte Mensual de Administraciones y Cumplimiento de la tesis: programadas
    // vs. realizadas, a tiempo / con retraso / omitidas y % de cumplimiento,
    // consolidado por residente y por medicamento. Mismas reglas de conteo que el
    // Reporte de Residente (programadas = administradas + omitidas + sin registro),
    // pero para todo el hogar a la vez.
    public function compliancePdf(Request $request)
    {
        [$start, $end, $period] = $this->resolveDateRange($request);

        $filters = $request->validate([
            'resident_id' => 'nullable|exists:residents,id',
            'medication_id' => 'nullable|exists:medications,id',
        ]);

        $scope = function ($q) use ($filters) {
            if (!empty($filters['resident_id'])) {
                $q->where('resident_id', $filters['resident_id']);
            }
            if (!empty($filters['medication_id'])) {
                $q->where('medication_id', $filters['medication_id']);
            }
        };

        // Solo vigentes para "sin registro" (ver el comentario equivalente en
        // residentMedicationPdf sobre las descontinuadas sin end_date).
        $prescriptions = Prescription::with(['medication', 'resident', 'schedules'])
            ->where($scope)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $end))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->get();

        $logs = MedicationLog::whereHas('schedule.prescription', $scope)
            ->whereBetween('scheduled_time', [$start, $end])
            ->with(['schedule.prescription.medication', 'schedule.prescription.resident'])
            ->get();

        $missingDoses = $this->findMissingDoses($prescriptions, $logs, $start, $end);

        $residentNames = $prescriptions->pluck('resident')
            ->merge($logs->map(fn ($log) => $log->schedule?->prescription?->resident))
            ->filter()
            ->mapWithKeys(fn (Resident $r) => [$r->id => $r->full_name . ($r->trashed() ? ' (inactivo)' : '')]);

        $medicationNames = $prescriptions->pluck('medication')
            ->merge($logs->map(fn ($log) => $log->schedule?->prescription?->medication))
            ->filter()
            ->mapWithKeys(fn ($m) => [$m->id => $m->name]);

        $group = function (string $key, Collection $names) use ($logs, $missingDoses) {
            $logsByKey = $logs->groupBy(fn ($log) => $log->schedule?->prescription?->{$key});
            $missingByKey = $missingDoses->groupBy($key);

            return $logsByKey->keys()->merge($missingByKey->keys())
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->unique()
                ->map(fn ($id) => ['name' => $names[$id] ?? "#{$id}"] + $this->tally(
                    $logsByKey->get($id, collect()),
                    $missingByKey->get($id, collect())
                ))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
        };

        $pdf = Pdf::loadView('reports.compliance', [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'summary' => $this->tally($logs, $missingDoses),
            'byResident' => $group('resident_id', $residentNames),
            'byMedication' => $group('medication_id', $medicationNames),
            'filterResident' => !empty($filters['resident_id']) ? Resident::withTrashed()->find($filters['resident_id']) : null,
            'filterMedication' => !empty($filters['medication_id']) ? Medication::withTrashed()->find($filters['medication_id']) : null,
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ]);

        return $pdf->stream('reporte-cumplimiento.pdf');
    }

    // GET /api/reports/residents?[status=active|inactive]&[with_treatment=yes|no]
    //
    // Listado General de Residentes y Tratamientos Activos de la tesis. No
    // depende de un periodo: es la foto de hoy. "Tratamiento activo" = misma
    // regla que las alertas (CheckPendingMedications): prescripción con
    // is_active y sin end_date vencida.
    public function residentsPdf(Request $request)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:active,inactive',
            'with_treatment' => 'nullable|in:yes,no',
        ]);

        $today = now()->toDateString();
        $activeScope = fn ($q) => $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today));

        $query = Resident::withTrashed()
            ->with([
                'prescriptions' => fn ($q) => $activeScope($q)->with(['medication' => fn ($m) => $m->withTrashed()]),
                'assignedNurse',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if (($filters['status'] ?? null) === 'active') {
            $query->whereNull('deleted_at');
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->whereNotNull('deleted_at');
        }

        if (($filters['with_treatment'] ?? null) === 'yes') {
            $query->whereHas('prescriptions', $activeScope);
        } elseif (($filters['with_treatment'] ?? null) === 'no') {
            $query->whereDoesntHave('prescriptions', $activeScope);
        }

        $residents = $query->get();

        $pdf = Pdf::loadView('reports.residents', [
            'residents' => $residents,
            'summary' => [
                'total' => $residents->count(),
                'active' => $residents->whereNull('deleted_at')->count(),
                'inactive' => $residents->whereNotNull('deleted_at')->count(),
                'withTreatment' => $residents->filter(fn ($r) => $r->prescriptions->isNotEmpty())->count(),
            ],
            'filterStatus' => $filters['status'] ?? null,
            'filterTreatment' => $filters['with_treatment'] ?? null,
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ]);

        return $pdf->stream('listado-residentes.pdf');
    }

    // Indicadores de cumplimiento de un conjunto de dosis (registradas + sin
    // registro). "A tiempo" = delay_minutes <= 0, igual que en residentMedicationPdf.
    private function tally(Collection $logs, Collection $missing): array
    {
        $administered = $logs->where('status', 'administered');
        $administeredCount = $administered->count();
        $missedCount = $logs->where('status', 'missed')->count();
        $expected = $administeredCount + $missedCount + $missing->count();
        $onTime = $administered->where('delay_minutes', '<=', 0)->count();

        return [
            'expected' => $expected,
            'administered' => $administeredCount,
            'onTime' => $onTime,
            'late' => $administeredCount - $onTime,
            'missed' => $missedCount,
            'missing' => $missing->count(),
            'adherence' => $expected > 0 ? round($administeredCount / $expected * 100, 1) : null,
        ];
    }

    // Traduce period+date (o from/to) a un rango [inicio, fin] concreto en Carbon.
    private function resolveDateRange(Request $request): array
    {
        $period = $request->validate([
            'period' => 'required|in:day,week,month,year,range',
        ])['period'];

        if ($period === 'range') {
            $data = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);

            return [Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay(), $period];
        }

        $date = Carbon::parse($request->validate(['date' => 'required|date'])['date']);

        return match ($period) {
            'day' => [$date->copy()->startOfDay(), $date->copy()->endOfDay(), $period],
            'week' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek(), $period],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth(), $period],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear(), $period],
        };
    }

    // Dosis que debieron administrarse (según el horario de una prescripción vigente) y
    // para las que nadie generó ningún medication_log (ni administrado ni omitido). Es la
    // única forma de detectar una omisión cuando nadie la registró explícitamente: no hay
    // ningún proceso automático en el sistema que marque una dosis como "missed" sola.
    private function findMissingDoses(Collection $prescriptions, Collection $logs, Carbon $start, Carbon $end): Collection
    {
        $now = Carbon::now();

        $loggedKeys = $logs->mapWithKeys(function ($log) {
            $day = Carbon::parse($log->scheduled_time)->toDateString();
            return ["{$log->schedule_id}|{$day}" => true];
        });

        $missing = collect();

        foreach ($prescriptions as $prescription) {
            $prescriptionStart = $prescription->start_date
                ? Carbon::parse($prescription->start_date)->startOfDay()
                : $start->copy();
            $prescriptionEnd = $prescription->end_date
                ? Carbon::parse($prescription->end_date)->endOfDay()
                : $end->copy();

            // Un residente desactivado (egreso, fallecimiento) deja de recibir
            // dosis desde ese momento: sin este tope, sus prescripciones que nadie
            // descontinuó seguirían sumando "dosis sin registro" para siempre.
            $deactivatedAt = $prescription->resident?->deleted_at;
            if ($deactivatedAt && $prescriptionEnd->greaterThan($deactivatedAt)) {
                $prescriptionEnd = Carbon::parse($deactivatedAt);
            }

            $rangeStart = $start->greaterThan($prescriptionStart) ? $start->copy() : $prescriptionStart;
            $rangeEnd = $end->lessThan($prescriptionEnd) ? $end->copy() : $prescriptionEnd;

            if ($rangeStart->greaterThan($rangeEnd)) {
                continue;
            }

            foreach ($prescription->schedules as $schedule) {
                $cursor = $rangeStart->copy()->startOfDay();

                while ($cursor->lessThanOrEqualTo($rangeEnd)) {
                    $scheduledAt = $cursor->copy()->setTimeFromTimeString($schedule->scheduled_time);

                    if ($scheduledAt->lessThan($now) && $scheduledAt->between($start, $end)) {
                        $key = "{$schedule->id}|{$cursor->toDateString()}";

                        if (!$loggedKeys->has($key)) {
                            $missing->push([
                                'scheduled_at' => $scheduledAt->copy(),
                                'medication' => $prescription->medication?->name,
                                'dosage' => $prescription->dosage,
                                'resident_id' => $prescription->resident_id,
                                'medication_id' => $prescription->medication_id,
                            ]);
                        }
                    }

                    $cursor->addDay();
                }
            }
        }

        return $missing->sortBy('scheduled_at')->values();
    }
}
