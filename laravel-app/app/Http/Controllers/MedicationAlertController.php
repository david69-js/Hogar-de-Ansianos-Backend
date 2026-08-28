<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationAlert;

/**
 * CRUD genérico sobre las filas de aviso que generan los comandos programados
 * (app:check-pending-medications y app:check-medication-stock — ver
 * MedicationAlert para el detalle de qué representa cada fila). En la
 * práctica el frontend solo las LEE (bandeja de Notificaciones); nadie las
 * crea a mano vía este controlador, eso lo hacen los comandos directo con
 * Eloquent para aprovechar el índice único como candado de deduplicación.
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

    public function store(Request $request)
    {
        $item = MedicationAlert::create($request->all());
        return response()->json([
            'message' => 'Creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = MedicationAlert::findOrFail($id);
        $item->update($request->all());
        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = MedicationAlert::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
