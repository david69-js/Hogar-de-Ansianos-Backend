<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationAlert;

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
    public function index()
    {
        $items = MedicationAlert::all();
        return response()->json($items, 200);
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
