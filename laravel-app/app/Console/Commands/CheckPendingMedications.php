<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\Medication;
use App\Models\MedicationAlert;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\Prescription;
use App\Models\Resident;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
    protected $description = 'Crea las alertas de la bandeja de la app en 3 momentos de cada horario (15 min antes, al llegar la hora, y 15 min después si sigue sin administrarse) y, si hay dispositivos registrados, envía además la notificación push.';

    /**
     * Cuántos minutos antes de la hora programada se manda el primer aviso.
     */
    private const REMINDER_BEFORE_MINUTES = 15;

    /**
     * Con menos de estos minutos para la toma, el recordatorio previo ya no
     * aporta nada y no se manda.
     *
     * Pasaba al crear una prescripción poco antes de su hora: el comando veía
     * que faltaban 4 minutos, mandaba igual el aviso titulado "en 15 minutos"
     * —falso— y cuatro minutos después llegaba el de "pendiente". Dos avisos
     * casi juntos para la misma dosis, que es lo que se leía como duplicado.
     */
    private const REMINDER_BEFORE_MIN_MINUTES = 5;

    /**
     * A partir de cuántos minutos de retraso se manda el aviso de "atrasado".
     *
     * Era 15. A los 15 minutos la dosis todavía se está atendiendo en la
     * práctica, así que ese aviso llegaba encima del anterior. A los 30 ya es un
     * atraso real, y queda un cuarto de hora para actuar antes de que la dosis
     * se registre sola como no administrada (MISSED_AFTER_MINUTES).
     */
    private const REMINDER_DELAYED_MINUTES = 30;

    /**
     * Cuántos minutos de retraso convierten una dosis pendiente en una omisión.
     *
     * Hasta este punto la dosis se puede administrar y se sigue recordando. Pasado
     * este punto se registra sola como "no administrada" y dejan de mandarse
     * avisos: ya no es un recordatorio, es un hecho que hay que documentar.
     *
     * El mismo número vive en el frontend (MISSED_THRESHOLD_MINUTES, en el panel y
     * en el calendario), que es hasta cuándo deja marcar una dosis como
     * administrada. Si se cambia acá, hay que cambiarlo allá: si no, queda una
     * franja en la que la dosis no se puede administrar pero tampoco se registró.
     *
     * La línea de tiempo completa de una dosis:
     *   -15 min  se habilita para administrar  +  aviso "en N minutos"
     *     0 min  hora programada               +  aviso "ahora"
     *   +30 min  pasa a contar como urgente    +  aviso "atrasado"
     *   +45 min  se registra sola como no administrada; los avisos paran
     *
     * Entre la hora y los 45 minutos la dosis se puede seguir administrando, y
     * queda registrada con su retraso (delay_minutes).
     */
    private const MISSED_AFTER_MINUTES = 45;

    public function handle(): int
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

        // La bandeja de la app (notifications.tsx, vía /medication-alerts) se
        // alimenta de las filas de MedicationAlert, y el push es solo un canal
        // extra. Por eso aquí no se sale temprano si no hay dispositivos
        // registrados — antes lo hacía, y entonces nadie veía ninguna alerta en
        // la app hasta que alguien activara el push en algún teléfono.
        // Se guarda la plataforma junto al token: el navegador necesita el mensaje
        // SIN bloque "notification" (ver FirebaseService::sendToTokens), y la app
        // nativa lo necesita CON él.
        $tokensByUser = DeviceToken::all(['user_id', 'token', 'platform'])
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => ['token' => $r->token, 'platform' => $r->platform])->all());
        $tokens = $tokensByUser->flatten(1)->all();

        // Destinatarios del push (asignación de enfermera responsable): si el
        // residente tiene una enfermera activa asignada, el aviso va a ella y a
        // Admin; si no tiene, a todo el personal, igual que antes de existir la
        // asignación. La bandeja de la app sigue el mismo criterio (ver
        // MedicationAlertController::index()).
        $adminIds = User::role('Admin')->where('status', 'active')->pluck('id');
        $activeNurseIds = User::role('Enfermera')->where('status', 'active')->pluck('id')->flip();

        // Firebase se resuelve aquí y no por inyección en handle(): si su
        // configuración falla (credenciales vacías, plantilla sin rellenar), la
        // inyección hacía morir el comando antes de crear ninguna alerta. Así,
        // un problema de push solo apaga el push.
        $firebase = null;
        if (!empty($tokens)) {
            try {
                $firebase = app(FirebaseService::class);
            } catch (\Throwable $e) {
                Log::error('Firebase no disponible: se crean las alertas sin enviar push', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $alertCount = 0;
        $sentCount = 0;
        $missedCount = 0;

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

            // Pasada la ventana, la dosis se registra sola como no administrada.
            // Queda SIN motivo y SIN responsable a propósito: nadie la administró y
            // nadie la registró, así que atribuirla a alguien sería inventar un dato
            // en un expediente. El motivo lo completa después quien corresponda,
            // desde el calendario.
            if ($minutesUntilDue <= -self::MISSED_AFTER_MINUTES) {
                if ($this->registrarOmision($schedule, $scheduledDateTime)) {
                    $missedCount++;
                }
                continue;
            }

            if ($minutesUntilDue > self::REMINDER_BEFORE_MINUTES) {
                continue; // todavía falta demasiado tiempo, nada que avisar aún
            } elseif ($minutesUntilDue > self::REMINDER_BEFORE_MIN_MINUTES) {
                $alertType = 'reminder_before';
            } elseif ($minutesUntilDue > 0) {
                // Faltan menos de 5 minutos: no vale la pena un recordatorio que
                // llegaría pegado al aviso de la hora. Se espera a ese.
                continue;
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
            $alertCount++;

            if (!$firebase) {
                continue; // la alerta ya está en la bandeja; sin push posible
            }

            $resident = $residents->get($prescription->resident_id);
            if (!$resident) {
                continue;
            }
            $medication = $medications->get($prescription->medication_id);

            $residentName = trim(implode(' ', array_filter([
                $resident->first_name,
                $resident->middle_name,
                $resident->last_name,
                $resident->second_last_name,
            ])));
            $medicationLabel = trim(($medication->name ?? 'Medicamento') . ' ' . ($prescription->dosage ?? ''));
            $scheduledLabel = $scheduledDateTime->format('H:i');

            [$title, $body] = match ($alertType) {
                'reminder_before' => [
                    // Los minutos reales, no un 15 fijo: si la prescripción se
                    // creó poco antes de la hora, el aviso salía diciendo "en 15
                    // minutos" cuando faltaban muchos menos.
                    'Medicamento en ' . max(1, (int) round($minutesUntilDue)) . ' minutos',
                    "{$residentName} (Hab. {$resident->room_number}) tiene {$medicationLabel} programado a las {$scheduledLabel}.",
                ],
                'due_now' => [
                    'Medicamento pendiente',
                    "{$residentName} (Hab. {$resident->room_number}) necesita {$medicationLabel} ahora (programado a las {$scheduledLabel}).",
                ],
                default => [
                    'Medicamento atrasado',
                    "{$residentName} (Hab. {$resident->room_number}) sigue sin recibir {$medicationLabel} (programado a las {$scheduledLabel}).",
                ],
            };

            // A quién le llega el aviso:
            //   - Siempre a Admin, que es quien supervisa el hogar completo.
            //   - Además, a la enfermera responsable del residente, si tiene una
            //     asignada y sigue activa.
            //
            // A NADIE MÁS. Antes, un residente sin enfermera asignada avisaba a
            // todo el personal, y eso hacía que cada enfermera recibiera avisos de
            // residentes que no son suyos: con el hogar lleno son decenas por
            // turno, y el ruido termina tapando lo que sí le toca a cada quien.
            //
            // Contrapartida a tener presente: un residente sin nadie asignado
            // depende de que Admin esté pendiente. La forma de evitarlo es
            // asignarle enfermera, no volver a avisarle a todos.
            $destinatarios = $adminIds;
            if ($resident->assigned_nurse_id && $activeNurseIds->has($resident->assigned_nurse_id)) {
                $destinatarios = $destinatarios->concat([$resident->assigned_nurse_id]);
            }

            $recipients = $destinatarios->unique()
                ->flatMap(fn ($userId) => $tokensByUser->get($userId, []))
                ->values()
                ->all();
            // $recipients son pares ['token' => ..., 'platform' => ...].

            // Nadie de los que corresponde tiene las notificaciones activadas en
            // algún aparato. La alerta ya quedó en la bandeja de la aplicación.
            if (empty($recipients)) {
                continue;
            }

            // Un fallo de envío no debe cortar el ciclo: los demás horarios
            // todavía necesitan su alerta.
            $datos = [
                'type' => 'medication_' . $alertType,
                'resident_id' => (string) $resident->id,
                'schedule_id' => (string) $schedule->id,
            ];

            $tokensWeb = array_values(array_map(
                fn ($r) => $r['token'],
                array_filter($recipients, fn ($r) => $r['platform'] === 'web')
            ));
            $tokensNativos = array_values(array_map(
                fn ($r) => $r['token'],
                array_filter($recipients, fn ($r) => $r['platform'] !== 'web')
            ));

            try {
                // Al navegador, solo datos: si no, el SDK muestra la notificación
                // y el service worker la muestra otra vez, y en el teléfono se
                // veían dos avisos idénticos por cada dosis.
                if (!empty($tokensWeb)) {
                    $firebase->sendToTokens($tokensWeb, $title, $body, $datos, true);
                }
                if (!empty($tokensNativos)) {
                    $firebase->sendToTokens($tokensNativos, $title, $body, $datos);
                }
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el push de medicamento pendiente', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Alertas creadas: {$alertCount}. Notificaciones push enviadas: {$sentCount}. Dosis registradas como no administradas: {$missedCount}.");
        return self::SUCCESS;
    }

    /**
     * Deja registrada una dosis que nunca se administró ni se marcó.
     *
     * No pasa por MedicationLogController::store() a propósito: ese endpoint exige
     * un motivo (required_if:status,missed), y acá justamente no hay ninguno que
     * decir todavía. Tampoco toca el inventario, porque no se consumió nada.
     *
     * El índice único (schedule_id, scheduled_time) es lo que evita duplicados si
     * dos procesos corren a la vez (el servicio web y el scheduler): solo uno logra
     * insertar y el otro recibe el error y sigue de largo.
     */
    private function registrarOmision(MedicationSchedule $schedule, Carbon $scheduledDateTime): bool
    {
        try {
            MedicationLog::create([
                'schedule_id' => $schedule->id,
                'scheduled_time' => $scheduledDateTime,
                'status' => 'missed',
                'administered_by' => null,
                'reason_for_omission' => null,
                // Lo mismo que pone MedicationLogController::resolveIncidentType() a
                // toda omisión. Sin esto, las dosis registradas solas quedarían
                // fuera del reporte de incidencias, que filtra por este campo.
                'incident_type' => 'omision',
            ]);

            return true;
        } catch (QueryException $e) {
            return false; // ya estaba registrada
        }
    }
}
