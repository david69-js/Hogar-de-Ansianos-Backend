<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\Medication;
use App\Models\MedicationAlert;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\Prescription;
use App\Models\Resident;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckPendingMedications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-pending-medications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envía una notificación push cuando a un residente le toca un medicamento y aún no ha sido administrado.';

    /**
     * Ventana de tolerancia (en minutos) desde que llega la hora programada:
     * evita mandar avisos de horarios que quedaron pendientes de hace mucho tiempo
     * (por ejemplo, si el servidor estuvo caído) en vez de solo los recién vencidos.
     */
    private const LOOKBACK_MINUTES = 15;

    public function handle(FirebaseService $firebase): int
    {
        $now = Carbon::now();
        $todayKey = $now->toDateString();

        $activePrescriptions = Prescription::where('is_active', true)
            ->where(function ($q) use ($todayKey) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $todayKey);
            })
            ->where(function ($q) use ($todayKey) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $todayKey);
            })
            ->get()
            ->keyBy('id');

        if ($activePrescriptions->isEmpty()) {
            $this->info('No hay prescripciones activas.');
            return self::SUCCESS;
        }

        $schedules = MedicationSchedule::whereIn('prescription_id', $activePrescriptions->keys())->get();
        $residents = Resident::whereIn('id', $activePrescriptions->pluck('resident_id'))->get()->keyBy('id');
        $medications = Medication::whereIn('id', $activePrescriptions->pluck('medication_id'))->get()->keyBy('id');

        $tokens = DeviceToken::pluck('token')->all();
        if (empty($tokens)) {
            $this->info('Nadie tiene dispositivos registrados para notificaciones.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($schedules as $schedule) {
            $prescription = $activePrescriptions->get($schedule->prescription_id);
            if (!$prescription) {
                continue;
            }

            [$hour, $minute] = array_pad(explode(':', $schedule->scheduled_time), 2, 0);
            $scheduledDateTime = $now->copy()->setTime((int) $hour, (int) $minute, 0);

            $minutesUntilDue = $now->diffInMinutes($scheduledDateTime, false);

            // Aún no le toca (la hora programada está en el futuro).
            if ($minutesUntilDue > 0) {
                continue;
            }
            // Ya pasó hace demasiado tiempo, no seguir avisando de horarios viejos.
            if ($minutesUntilDue < -self::LOOKBACK_MINUTES) {
                continue;
            }

            // ¿Ya se administró hoy?
            $alreadyAdministered = MedicationLog::where('schedule_id', $schedule->id)
                ->whereDate('scheduled_time', $todayKey)
                ->exists();
            if ($alreadyAdministered) {
                continue;
            }

            // ¿Ya se avisó de este horario hoy? (evita reenviar el mismo aviso cada minuto)
            $alreadyAlerted = MedicationAlert::where('prescription_id', $prescription->id)
                ->where('alert_type', 'pending_push')
                ->whereDate('scheduled_time', $todayKey)
                ->exists();
            if ($alreadyAlerted) {
                continue;
            }

            $resident = $residents->get($prescription->resident_id);
            if (!$resident) {
                continue;
            }
            $medication = $medications->get($prescription->medication_id);

            $residentName = trim("{$resident->first_name} {$resident->last_name}");
            $medicationLabel = trim(($medication->name ?? 'Medicamento') . ' ' . ($prescription->dosage ?? ''));

            $firebase->sendToTokens(
                $tokens,
                'Medicamento pendiente',
                "{$residentName} (Hab. {$resident->room_number}) necesita {$medicationLabel}",
                [
                    'type' => 'medication_pending',
                    'resident_id' => (string) $resident->id,
                    'schedule_id' => (string) $schedule->id,
                ]
            );

            MedicationAlert::create([
                'prescription_id' => $prescription->id,
                'resident_id' => $resident->id,
                'scheduled_time' => $scheduledDateTime,
                'alert_type' => 'pending_push',
            ]);

            $sentCount++;
        }

        $this->info("Notificaciones enviadas: {$sentCount}");
        return self::SUCCESS;
    }
}
