<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationAlert;
use App\Models\Resident;

/**
 * Lectura (y marcar como leída) de las filas de aviso que generan los
 * comandos programados (app:check-pending-medications y
 * app:check-medication-stock — ver MedicationAlert para el detalle de qué
 * representa cada fila). El frontend solo LEE y marca `read_at` (bandeja de
 * Notificaciones); nadie crea ni borra estas filas vía HTTP — eso lo hacen
 * los comandos directo con Eloquent para aprovechar el índice único como
 * candado de deduplicación. Por eso no hay store()/destroy() aquí, y
 * update() solo acepta `read_at`.
 */
class MedicationAlertController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = MedicationAlert::query();

        // Bandeja de una enfermera: SOLO los avisos de los residentes que tiene
        // asignados. Mismo criterio que el push de CheckPendingMedications, para
        // que la campanita muestre exactamente lo que le llegó al teléfono.
        //
        // Antes veía también los de residentes sin responsable y los avisos de
        // inventario. Eso convertía su bandeja en la del hogar entero y enterraba
        // lo suyo entre decenas de avisos ajenos. Los de residentes sin asignar y
        // los de inventario son de supervisión: quedan para Admin, que además es
        // el único que puede actuar sobre el inventario.
        if ($user->hasRole('Enfermera') && !$user->hasRole('Admin')) {
            $misResidentes = Resident::withTrashed()->select('id')
                ->where('assigned_nurse_id', $user->id);

            $query->whereIn('resident_id', $misResidentes);
        }

        return response()->json($query->get(), 200);
    }

    public function show($id)
    {
        $item = MedicationAlert::findOrFail($id);
        return response()->json($item, 200);
    }

    // El único uso real desde el frontend es marcar como leída (bandeja de
    // Notificaciones), así que solo se acepta `read_at` — sin este límite,
    // cualquier usuario autenticado podría reescribir el contenido de una alerta
    // ya generada (hora programada, tipo, residente) a través de este endpoint.
    public function update(Request $request, $id)
    {
        $item = MedicationAlert::findOrFail($id);

        $validated = $request->validate([
            'read_at' => 'nullable|date',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }
}
