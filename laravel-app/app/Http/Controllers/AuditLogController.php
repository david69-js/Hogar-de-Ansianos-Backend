<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Disease;
use App\Models\Medication;
use App\Models\MedicationSchedule;
use App\Models\Prescription;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Expone el registro de auditoría en solo lectura (index/show). Protegido por
 * `manage_users` en las rutas (solo Admin) — ver routes/api.php. Las filas las
 * genera App\Observers\AuditableObserver cuando se guarda/borra un modelo
 * observado, y AuthController en cada inicio/cierre de sesión; un audit log que
 * se pueda escribir/borrar por API deja de
 * servir como prueba de qué pasó, por eso no hay store/update/destroy aquí ni
 * en las rutas (apiResource solo registra ['index', 'show']).
 */
class AuditLogController extends Controller
{
    // GET /api/audit-logs — filtra por tabla/acción/usuario (todos opcionales,
    // combinables) y pagina de 50 en 50, más reciente primero.
    public function index(Request $request)
    {
        // orderByDesc('id') desempata: varios eventos caen en el mismo segundo
        // (confirmar una dosis también descuenta stock) y sin él el orden entre
        // ellos quedaba al azar, rompiendo la cronología de la bitácora.
        $query = AuditLog::query()->with('user')->latest()->orderByDesc('id');

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->query('table_name'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        // Rango de fechas del Reporte de Auditoría de Actividad (Fecha inicio /
        // Fecha fin en la especificación de la tesis). Ambos opcionales e
        // inclusivos: "to" cubre el día completo.
        $range = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        if (!empty($range['from'])) {
            $query->whereDate('created_at', '>=', $range['from']);
        }
        if (!empty($range['to'])) {
            $query->whereDate('created_at', '<=', $range['to']);
        }

        $items = $query->paginate(50);
        $this->attachReferences($items->getCollection());
        return response()->json($items, 200);
    }

    // GET /api/audit-logs/{id} — detalle de una fila puntual.
    public function show($id)
    {
        $item = AuditLog::with('user')->findOrFail($id);
        $this->attachReferences(collect([$item]));
        return response()->json($item, 200);
    }

    /**
     * old_values/new_values guardan llaves foráneas como números
     * ("resident_id": 3), que no le dicen nada a quien revisa la bitácora. Aquí
     * se agrega a cada fila un `references` con el nombre legible de cada una
     * ({"resident_id": "María López"}), resuelto al consultar — así el nombre
     * sale aunque el residente o medicamento se haya desactivado después.
     * Se resuelve en lote (una consulta por tipo) para no hacer N+1 por fila.
     */
    private function attachReferences(Collection $entries): void
    {
        $userFields = ['user_id', 'administered_by', 'created_by', 'recorded_by', 'claimed_by', 'uploaded_by'];
        $fields = array_merge($userFields, ['resident_id', 'medication_id', 'disease_id', 'prescription_id', 'schedule_id']);

        $decoded = $entries->mapWithKeys(fn (AuditLog $e) => [$e->id => array_merge(
            (array) json_decode($e->old_values ?? '', true),
            (array) json_decode($e->new_values ?? '', true),
        )]);

        $ids = fn (array $keys) => $decoded
            ->flatMap(fn (array $v) => array_values(array_intersect_key($v, array_flip($keys))))
            ->filter(fn ($id) => is_numeric($id))
            ->unique()
            ->values();

        $schedules = MedicationSchedule::with(['prescription' => fn ($q) => $q->withTrashed()->with([
            'medication' => fn ($m) => $m->withTrashed(),
            'resident' => fn ($r) => $r->withTrashed(),
        ])])->whereIn('id', $ids(['schedule_id']))->get()->keyBy('id');

        $prescriptions = Prescription::withTrashed()
            ->with(['medication' => fn ($m) => $m->withTrashed(), 'resident' => fn ($r) => $r->withTrashed()])
            ->whereIn('id', $ids(['prescription_id']))->get()->keyBy('id');

        $describePrescription = fn (?Prescription $p) => $p
            ? trim(($p->medication?->name ?? 'Medicamento') . ' — ' . ($p->resident?->full_name ?? 'Residente'))
            : null;

        $names = [
            'user' => User::whereIn('id', $ids($userFields))->get()->mapWithKeys(fn (User $u) => [$u->id => $u->full_name ?: $u->email]),
            'resident_id' => Resident::withTrashed()->whereIn('id', $ids(['resident_id']))->get()->mapWithKeys(fn (Resident $r) => [$r->id => $r->full_name]),
            'medication_id' => Medication::withTrashed()->whereIn('id', $ids(['medication_id']))->pluck('name', 'id'),
            'disease_id' => Disease::whereIn('id', $ids(['disease_id']))->pluck('name', 'id'),
            'prescription_id' => $prescriptions->map($describePrescription),
            'schedule_id' => $schedules->map(fn (MedicationSchedule $s) => trim(
                substr((string) $s->scheduled_time, 0, 5) . ' · ' . ($describePrescription($s->prescription) ?? '')
            )),
        ];

        $entries->each(function (AuditLog $entry) use ($decoded, $fields, $userFields, $names) {
            $references = [];
            foreach (array_intersect_key($decoded[$entry->id], array_flip($fields)) as $field => $id) {
                $name = $names[in_array($field, $userFields, true) ? 'user' : $field][$id] ?? null;
                if ($name !== null && $name !== '') {
                    $references[$field] = $name;
                }
            }
            // Objeto (no arreglo vacío) para que el cliente siempre reciba {} y no [].
            $entry->setAttribute('references', (object) $references);
        });
    }
}
