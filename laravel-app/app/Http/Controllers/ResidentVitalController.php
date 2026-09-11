<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ResidentVital;

/**
 * CRUD de mediciones de signos vitales de un residente (ver
 * App\Models\ResidentVital). Lo usa la pantalla de detalle de residente en
 * el frontend (sección "Signos Vitales").
 */
class ResidentVitalController extends Controller
{
    // GET /api/resident-vitals?resident_id={id} → historial de un residente,
    // más reciente primero. Sin resident_id, devuelve todas las mediciones de
    // todos los residentes (sin uso real del frontend, pero queda disponible).
    public function index(Request $request)
    {
        $query = ResidentVital::query();

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->query('resident_id'));
        }

        $items = $query->orderByDesc('recorded_at')->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = ResidentVital::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|exists:residents,id',
            'weight' => 'nullable|numeric|min:0',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate' => 'nullable|integer|min:0',
            'temperature' => 'nullable|numeric|min:0',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'recorded_at' => 'nullable|date',
        ]);

        // Quién y cuándo: recorded_by siempre es el usuario autenticado (no se
        // manda desde el frontend), y recorded_at usa el momento del registro si
        // la pantalla no especificó uno explícito (permite capturar mediciones
        // retroactivas sin obligar a elegir fecha/hora en el caso normal).
        $validated['recorded_by'] = $request->user()?->id;
        $validated['recorded_at'] = $validated['recorded_at'] ?? now();

        $item = ResidentVital::create($validated);

        return response()->json([
            'message' => 'Signos vitales registrados exitosamente',
            'data' => $item
        ], 201);
    }

    // Sin pantalla que lo use todavía (vital-signs.tsx solo crea) — existe para
    // corregir un valor mal capturado sin tener que borrar y volver a crear.
    public function update(Request $request, $id)
    {
        $item = ResidentVital::findOrFail($id);

        $validated = $request->validate([
            'weight' => 'nullable|numeric|min:0',
            'blood_pressure' => 'nullable|string|max:20',
            'heart_rate' => 'nullable|integer|min:0',
            'temperature' => 'nullable|numeric|min:0',
            'oxygen_saturation' => 'nullable|integer|min:0|max:100',
            'recorded_at' => 'nullable|date',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = ResidentVital::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
