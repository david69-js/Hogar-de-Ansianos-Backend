<?php

namespace App\Http\Controllers;

use App\Models\MedicationLog;
use App\Models\Resident;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Genera los dos reportes en PDF (dompdf): por residente (medicación,
 * omisiones y responsable de cada dosis) y por enfermera (a quién atendió, a
 * quién omitió). Ambos aceptan el mismo filtro de periodo — día/semana/mes/año
 * o rango — resuelto en resolveDateRange(). Protegido por `view_reports`
 * (Admin y Enfermera); nursePdf() además exige que solo Admin pueda pedir el
 * reporte de otra persona (una enfermera solo ve el suyo). No persiste nada:
 * cada llamada arma el PDF al vuelo y lo devuelve como stream.
 */
class ReportController extends Controller
{
    // GET /api/reports/residents/{id}/medications
    public function residentMedicationPdf(Request $request, $id)
    {
        $resident = Resident::findOrFail($id);
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

        $missingDoses = $this->findMissingDoses($prescriptions, $logs, $start, $end);

        $administeredCount = $logs->where('status', 'administered')->count();
        $missedCount = $logs->where('status', 'missed')->count();
        $totalExpected = $administeredCount + $missedCount + $missingDoses->count();

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

        // El permiso view_reports no distingue "el mío" de "el de cualquiera": solo Admin
        // puede pedir el reporte de otra persona, una enfermera solo puede ver el propio.
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

        $pdf = Pdf::loadView('reports.nurse', [
            'nurse' => $nurse,
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
