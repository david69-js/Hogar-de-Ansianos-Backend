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
use Illuminate\Database\QueryException;
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
    protected $description = 'Envía notificaciones push en 3 momentos de cada horario: 15 min antes, al llegar la hora, y 15 min después si sigue sin administrarse.';

    /**
     * Cuántos minutos antes de la hora programada se manda el primer aviso.
     */
    private const REMINDER_BEFORE_MINUTES = 15;

    /**
     * A partir de cuántos minutos de retraso se manda el aviso de "sigue pendiente".
     */
    private const REMINDER_DELAYED_MINUTES = 15;

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

            // Ya se administró hoy: no tiene sentido seguir recordando este horario.
            $alreadyAdministered = MedicationLog::where('schedule_id', $schedule->id)
                ->whereDate('scheduled_time', $todayKey)
                ->exists();
            if ($alreadyAdministered) {
                continue;
            }

            if ($minutesUntilDue > self::REMINDER_BEFORE_MINUTES) {
                continue; // todavía falta demasiado tiempo, nada que avisar aún
            } elseif ($minutesUntilDue > 0) {
                $alertType = 'reminder_before';
            } elseif ($minutesUntilDue > -self::REMINDER_DELAYED_MINUTES) {
                $alertType = 'due_now';
            } else {
                $alertType = 'reminder_delayed';
            }

            // Dedup por horario exacto (schedule_id), no solo por prescripción, para
            // que dos horarios distintos de la misma prescripción (ej. 08:00 y 20:00)
            // no se bloqueen entre sí. El insert ocurre ANTES de enviar el push (en vez
            // de solo verificar con un exists()) porque el comando corre cada minuto vía
            // schedule:work y una ejecución manual podría solaparse: el índice único de
            // la tabla hace que solo un proceso logre crear la fila, y ese es el único
            // que llega a mandar el push — el otro recibe un QueryException y lo ignora.
            try {
                MedicationAlert::create([
                    'prescription_id' => $prescription->id,
                    'schedule_id' => $schedule->id,
                    'resident_id' => $prescription->resident_id,
                    'scheduled_time' => $scheduledDateTime,
                    'alert_type' => $alertType,
                ]);
            } catch (QueryException $e) {
                continue; // otro proceso ya registró (y envió) este mismo aviso
            }

            $resident = $residents->get($prescription->resident_id);
            if (!$resident) {
                continue;
            }
            $medication = $medications->get($prescription->medication_id);

            $residentName = trim("{$resident->first_name} {$resident->last_name}");
            $medicationLabel = trim(($medication->name ?? 'Medicamento') . ' ' . ($prescription->dosage ?? ''));
            $scheduledLabel = $scheduledDateTime->format('H:i');

            [$title, $body] = match ($alertType) {
                'reminder_before' => [
                    'Medicamento en 15 minutos',
                    "{$residentName} (Hab. {$resident->room_number}) tiene {$medicationLabel} programado a las {$scheduledLabel}.",
                ],
                'due_now' => [
                    'Medicamento pendiente',
                    "{$residentName} (Hab. {$resident->room_number}) necesita {$medicationLabel} ahora.",
                ],
                default => [
                    'Medicamento atrasado',
                    "{$residentName} (Hab. {$resident->room_number}) sigue sin recibir {$medicationLabel} (programado a las {$scheduledLabel}).",
                ],
            };

            $firebase->sendToTokens(
                $tokens,
                $title,
                $body,
                [
                    'type' => 'medication_' . $alertType,
                    'resident_id' => (string) $resident->id,
                    'schedule_id' => (string) $schedule->id,
                ]
            );

            $sentCount++;
        }

        $this->info("Notificaciones enviadas: {$sentCount}");
        return self::SUCCESS;
    }
}
