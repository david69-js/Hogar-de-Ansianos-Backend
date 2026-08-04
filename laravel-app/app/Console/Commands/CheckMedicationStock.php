<?php

namespace App\Console\Commands;

use App\Models\Medication;
use App\Models\MedicationAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckMedicationStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-medication-stock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revisa el inventario de medicamentos y genera alertas de stock bajo y caducidad próxima/vencida (una vez al día).';

    /**
     * A cuántos días de la fecha de caducidad se empieza a avisar "por vencer".
     */
    private const EXPIRING_SOON_DAYS = 30;

    public function handle(): int
    {
        $today = Carbon::today();

        $medications = Medication::query()
            ->where(function ($q) {
                $q->whereNotNull('minimum_stock')->orWhereNotNull('expiration_date');
            })
            ->get();

        $created = 0;

        foreach ($medications as $medication) {
            if ($medication->minimum_stock !== null && $medication->stock_quantity <= $medication->minimum_stock) {
                $created += $this->createAlertIfMissing($medication, 'low_stock', $today);
            }

            if ($medication->expiration_date) {
                $expiresAt = Carbon::parse($medication->expiration_date);
                $daysUntilExpiration = $today->diffInDays($expiresAt, false);

                if ($daysUntilExpiration < 0) {
                    $created += $this->createAlertIfMissing($medication, 'expired', $today);
                } elseif ($daysUntilExpiration <= self::EXPIRING_SOON_DAYS) {
                    $created += $this->createAlertIfMissing($medication, 'expiring_soon', $today);
                }
            }
        }

        $this->info("Alertas de inventario creadas: {$created}");
        return self::SUCCESS;
    }

    // Dedup con un simple exists()+create, a diferencia del índice único que usa el chequeo
    // de medicamentos pendientes (CheckPendingMedications): este comando corre una vez al
    // día, no cada minuto, así que el riesgo real de dos ejecuciones concurrentes chocando
    // en el mismo instante es prácticamente nulo — no vale la pena la complejidad extra.
    private function createAlertIfMissing(Medication $medication, string $alertType, Carbon $today): int
    {
        $alreadyAlerted = MedicationAlert::where('medication_id', $medication->id)
            ->where('alert_type', $alertType)
            ->whereDate('scheduled_time', $today->toDateString())
            ->exists();

        if ($alreadyAlerted) {
            return 0;
        }

        MedicationAlert::create([
            'medication_id' => $medication->id,
            'alert_type' => $alertType,
            'scheduled_time' => $today,
        ]);

        return 1;
    }
}
