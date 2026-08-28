<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\MedicationStockMovement;
use App\Models\Prescription;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MedicationLogController extends Controller
{
    public function index()
    {
        $items = MedicationLog::all();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = MedicationLog::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'exists:medication_schedules,id'],
            'scheduled_time' => ['required', 'date'],
            'administered_time' => ['nullable', 'date', 'required_if:status,administered'],
            'status' => ['required', 'in:administered,missed'],
            'reason_for_omission' => ['nullable', 'string', 'required_if:status,missed'],
            'administered_by' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        // Si el cliente no manda quién lo hizo, se asume el usuario autenticado — así el
        // responsable de cada dosis (para los reportes) siempre queda identificado.
        $data['administered_by'] = $data['administered_by'] ?? $request->user()?->id;

        // El retraso se calcula en el servidor (no se confía en lo que mande el cliente)
        // para que quede un registro confiable de qué tan tarde se administró.
        if ($data['status'] === 'administered' && !empty($data['administered_time'])) {
            $data['delay_minutes'] = max(0, (int) Carbon::parse($data['scheduled_time'])
                ->diffInMinutes(Carbon::parse($data['administered_time']), false));
        }

        try {
            return DB::transaction(function () use ($data) {
                $item = MedicationLog::create($data);

                if ($data['status'] === 'administered') {
                    $this->decrementStockForSchedule((int) $data['schedule_id'], $item);
                }

                return response()->json([
                    'message' => 'Creado exitosamente',
                    'data' => $item
                ], 201);
            });
        } catch (QueryException $e) {
            // Índice único (schedule_id, scheduled_time): dos pantallas (Dashboard y
            // Calendario) o dos dispositivos intentaron registrar la misma dosis casi
            // al mismo tiempo. La transacción ya hizo rollback solo (create() lanzó la
            // excepción antes del descuento de stock) — no queda nada a medias.
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'Esta dosis ya fue registrada por otra persona. Actualiza la pantalla para ver el registro existente.',
                ], 409);
            }
            throw $e;
        }
    }

    // Descuenta 1 unidad del stock del medicamento asociado al horario, sin bloquear la
    // administración si el stock ya está en 0 (registrar la dosis real dada al residente
    // es más importante que un contador de inventario exacto). Queda su propio movimiento
    // en el kardex, enlazado al log, para poder auditar de dónde salió cada descuento.
    private function decrementStockForSchedule(int $scheduleId, MedicationLog $log): void
    {
        $schedule = MedicationSchedule::find($scheduleId);
        if (!$schedule) {
            return;
        }
        $prescription = Prescription::find($schedule->prescription_id);
        if (!$prescription) {
            return;
        }
        $medication = Medication::lockForUpdate()->find($prescription->medication_id);
        if (!$medication) {
            return;
        }

        $resultingStock = max(0, $medication->stock_quantity - 1);
        $delta = $resultingStock - $medication->stock_quantity;
        if ($delta === 0) {
            return;
        }

        $medication->stock_quantity = $resultingStock;
        $medication->save();

        MedicationStockMovement::create([
            'medication_id' => $medication->id,
            'type' => 'salida',
            'quantity' => $delta,
            'resulting_stock' => $resultingStock,
            'reason' => 'Administrado automáticamente',
            'medication_log_id' => $log->id,
            'created_by' => $log->administered_by,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = MedicationLog::findOrFail($id);

        $data = $request->validate([
            'status' => ['sometimes', 'in:administered,missed'],
            'administered_time' => ['nullable', 'date'],
            'reason_for_omission' => ['nullable', 'string'],
            'administered_by' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->update($data);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = MedicationLog::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
