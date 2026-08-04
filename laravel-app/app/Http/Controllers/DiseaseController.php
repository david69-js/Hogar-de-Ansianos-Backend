<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Disease;
use App\Models\DiseaseResidentAssignment;

class DiseaseController extends Controller
{
    public function index()
    {
        $items = Disease::orderBy('name')->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = Disease::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:diseases,name',
            'description' => 'nullable|string',
            'icd_10_code' => 'nullable|string|max:20',
        ]);

        $item = Disease::create($validated);

        return response()->json([
            'message' => 'Condición creada exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = Disease::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:diseases,name,' . $item->id,
            'description' => 'nullable|string',
            'icd_10_code' => 'nullable|string|max:20',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Condición actualizada exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = Disease::findOrFail($id);

        // La FK de disease_resident_assignments tiene onDelete('cascade'): si permitiéramos
        // borrar una condición en uso, se perderían en silencio las asignaciones de los
        // residentes. Mejor bloquear y pedir que se retire de los residentes primero.
        $inUse = DiseaseResidentAssignment::where('disease_id', $item->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: esta condición está asignada a uno o más residentes. Retírala de ellos primero.'
            ], 409);
        }

        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Condición eliminada exitosamente'
        ], 200);
    }
}
