<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medication;
use App\Models\MedicationLog;
use App\Models\MedicationSchedule;
use App\Models\MedicationStockMovement;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El kardex real de administración de medicamentos (ver MedicationLog).
 * index() acepta `?date=` o `?from=&to=` para no bajar la tabla completa (crece
 * sin límite) — Calendario/Dashboard/Historial la usan así. store() es el
 * corazón clínico del sistema: calcula el retraso en el servidor (nunca confía
 * en lo que mande el cliente), descuenta 1 unidad de inventario si la dosis
 * fue administrada (ver decrementStockForSchedule(), en la misma transacción),
 * y está protegido contra doble registro de la misma dosis por un índice único
 * — si dos pantallas o dispositivos intentan marcar la misma dosis casi al
 * mismo tiempo, el segundo recibe 409, no un registro duplicado.
 * Solo `store` requiere `administer_medications`; index/show/update/destroy
 * están abiertos a cualquier rol autenticado.
 */
class MedicationLogController extends Controller
{
    /** Rango máximo (en días) que acepta index(). Un año cubre el reporte anual. */
    private const MAX_RANGE_DAYS = 366;

    // Sin filtros, esta tabla crece sin límite (una fila por cada dosis
    // administrada u omitida) y Calendario/Dashboard/Historial la bajaban
    // completa para luego filtrar en el cliente. `date` (un día) y `from`/`to`
    // (rango) dejan que cada pantalla pida solo lo que va a mostrar.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date' => 'nullable|date',
            'from' => 'nullable|date|required_with:to',
            'to' => 'nullable|date|required_with:from|after_or_equal:from',
        ]);

        $query = MedicationLog::query();

        if (!empty($filters['date'])) {
            $query->whereDate('scheduled_time', $filters['date']);
        } else {
            // RNF4: sin filtro, antes devolvía la tabla completa — es la que más
            // crece (una fila por dosis), y la medición de MANUAL_TECNICO.md dio
            // 5.4 MB con 90 días de datos. Ahora siempre hay un rango: por defecto
            // los últimos 30 días (lo que ya pedía como máximo Historial) y nunca
            // más de un año.
            $from = Carbon::parse($filters['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
            $to = Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay();

            if ($from->diffInDays($to, true) > self::MAX_RANGE_DAYS) {
                throw ValidationException::withMessages([
                    'from' => ['El rango no puede superar ' . self::MAX_RANGE_DAYS . ' días.'],
                ]);
            }

            $query->whereBetween('scheduled_time', [$from, $to]);
        }

        $items = $query->get();

        // Nombre de quien registró cada dosis (trazabilidad, RF7). Historial lo
        // resolvía pidiendo GET /users, pero esa ruta exige manage_users: una
        // Enfermera recibía 403 y nunca veía quién administró. Se manda solo el
        // nombre, no la lista de personal (que trae DPI, teléfono y dirección).
        // Va como campo aparte y no con with('administeredBy'): Laravel
        // serializaría esa relación como "administered_by" y pisaría el id
        // numérico que ya usan las pantallas.
        $names = User::withTrashed()
            ->whereIn('id', $items->pluck('administered_by')->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $u) => [$u->id => trim("{$u->first_name} {$u->last_name}")]);
        $items->each(fn (MedicationLog $log) => $log->setAttribute(
            'administered_by_name',
            $names[$log->administered_by] ?? null
        ));
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
            'incident_type' => ['nullable', Rule::in(array_keys(MedicationLog::INCIDENT_TYPES))],
        ]);

        $data['incident_type'] = $this->resolveIncidentType($data['status'], $data['incident_type'] ?? null);

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
            // Permite reportar una incidencia después del hecho (p. ej. una reacción
            // que se nota horas más tarde) sobre una dosis ya registrada.
            'incident_type' => ['sometimes', 'nullable', Rule::in(array_keys(MedicationLog::INCIDENT_TYPES))],
        ]);

        if (array_key_exists('status', $data) || array_key_exists('incident_type', $data)) {
            $data['incident_type'] = $this->resolveIncidentType(
                $data['status'] ?? $item->status,
                array_key_exists('incident_type', $data) ? $data['incident_type'] : $item->incident_type
            );
        }

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

    /**
     * Una dosis omitida es, por definición, una incidencia de tipo "omision":
     * se fija aquí para que el Reporte Mensual de Incidencias la cuente aunque el
     * cliente no mande el tipo. Una dosis administrada puede traer cualquier otro
     * tipo (o ninguno), pero nunca "omision" — sería decir que se dio y no se dio.
     */
    private function resolveIncidentType(string $status, ?string $incidentType): ?string
    {
        if ($status === 'missed') {
            return 'omision';
        }

        if ($incidentType === 'omision') {
            throw ValidationException::withMessages([
                'incident_type' => ['Una dosis administrada no puede clasificarse como omisión.'],
            ]);
        }

        return $incidentType;
    }
}
