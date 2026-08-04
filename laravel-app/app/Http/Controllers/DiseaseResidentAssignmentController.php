<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiseaseResidentAssignment;

class DiseaseResidentAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $query = DiseaseResidentAssignment::query();

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->query('resident_id'));
        }

        $items = $query->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = DiseaseResidentAssignment::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|exists:residents,id',
            'disease_id' => 'required|exists:diseases,id',
            'diagnosed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $alreadyAssigned = DiseaseResidentAssignment::where('resident_id', $validated['resident_id'])
            ->where('disease_id', $validated['disease_id'])
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'message' => 'Este residente ya tiene esta condición asignada.'
            ], 422);
        }

        $item = DiseaseResidentAssignment::create($validated);

        return response()->json([
            'message' => 'Condición asignada exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = DiseaseResidentAssignment::findOrFail($id);

        $validated = $request->validate([
            'diagnosed_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = DiseaseResidentAssignment::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Condición retirada exitosamente'
        ], 200);
    }
}
