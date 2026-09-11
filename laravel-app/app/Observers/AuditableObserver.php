<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Escribe una fila en audit_logs cada vez que se crea/actualiza/borra/restaura
 * un modelo administrativo observado. Se registra a mano con
 * `static::observe(AuditableObserver::class)` en el boot() de cada modelo — hoy
 * son User, Resident, Prescription, Medication, Disease,
 * DiseaseResidentAssignment, ResidentVital, MedicationSchedule y MedicationLog.
 *
 * MedicationLog se agregó porque el Reporte de Auditoría de Actividad de la
 * tesis pide expresamente las confirmaciones de dosis y el registro de
 * incidencias (crear un log = confirmar/omitir una dosis; actualizarlo con
 * incident_type = reportar una incidencia). MedicationStockMovement sigue
 * fuera: ya tiene created_by y es movimiento de inventario, no una acción que
 * el reporte pida. Los inicios/cierres de sesión no pasan por aquí: los
 * registra AuthController, porque no son cambios de un modelo.
 *
 * Cualquier modelo nuevo que deba auditarse necesita agregar la línea
 * observe() en su propio booted() — no hay un registro central en un Provider.
 */
class AuditableObserver
{
    private const HIDDEN_FIELDS = ['password', 'remember_token'];

    // Cambios que no ameritan su propia fila en el audit log:
    // - updated_at: se toca en cada guardado, sin excepción.
    // - deleted_at: ya lo cubren, con más contexto, deactivated()/restored().
    // - last_login_at: lo actualiza cada login; es bitácora de sesión, no una
    //   acción administrativa sobre el registro.
    private const IGNORED_UPDATE_FIELDS = ['updated_at', 'deleted_at', 'last_login_at'];

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = collect($this->sanitize($model->getChanges()))
            ->except(self::IGNORED_UPDATE_FIELDS)
            ->toArray();
        if (empty($changes)) {
            return;
        }

        $original = $this->sanitize(array_intersect_key($model->getOriginal(), $changes));
        $this->record('updated', $model, $original, $changes);
    }

    public function deleted(Model $model): void
    {
        // Un borrado lógico (soft delete) se registra como "deactivated", no
        // "deleted" — la fila sigue existiendo, solo cambia deleted_at.
        $isSoftDelete = method_exists($model, 'trashed') && $model->trashed();
        $this->record($isSoftDelete ? 'deactivated' : 'deleted', $model, $this->sanitize($model->getAttributes()), null);
    }

    public function restored(Model $model): void
    {
        $this->record('restored', $model, null, $this->sanitize($model->getAttributes()));
    }

    // Redacta el VALOR de los campos sensibles en vez de quitar la llave: así el
    // log deja constancia de que el campo cambió (ej. "se cambió la contraseña")
    // sin exponer el hash — quitar la llave por completo haría que un cambio de
    // solo contraseña no generara ninguna fila, y esa acción desaparecería del
    // todo del registro de auditoría.
    private function sanitize(array $attributes): array
    {
        foreach (self::HIDDEN_FIELDS as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = '[REDACTED]';
            }
        }
        return $attributes;
    }

    private function record(string $action, Model $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
        ]);
    }
}
