<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationSchedule;

class MedicationScheduleController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicationSchedule::query();

        if ($request->has('prescription_id')) {
            $query->where('prescription_id', $request->query('prescription_id'));
        }

        $items = $query->orderBy('scheduled_time')->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = MedicationSchedule::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'prescription_id' => 'required|exists:prescriptions,id',
            'scheduled_time' => 'required|date_format:H:i',
        ]);

        $item = MedicationSchedule::create($validated);

        return response()->json([
            'message' => 'Horario agregado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = MedicationSchedule::findOrFail($id);

        $validated = $request->validate([
            'scheduled_time' => 'sometimes|date_format:H:i',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Horario actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = MedicationSchedule::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Horario eliminado exitosamente'
        ], 200);
    }
}
