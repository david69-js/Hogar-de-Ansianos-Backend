<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

// Registrado en los modelos administrativos (Users, Residents, Prescriptions,
// Medications, Diseases, DiseaseResidentAssignment, MedicationSchedule) — no en
// MedicationLog ni MedicationStockMovement, que ya tienen su propio responsable
// (administered_by / created_by) y su propia auditoría de dominio.
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
