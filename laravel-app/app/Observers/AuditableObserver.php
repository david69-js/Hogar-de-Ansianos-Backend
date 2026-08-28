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

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $this->sanitize($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->sanitize($model->getChanges());
        unset($changes['updated_at']);
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

    private function sanitize(array $attributes): array
    {
        return collect($attributes)->except(self::HIDDEN_FIELDS)->toArray();
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
