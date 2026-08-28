<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationSchedule;
use Illuminate\Database\QueryException;

/**
 * CRUD de horarios recurrentes dentro de una prescripción (ver
 * MedicationSchedule). store()/update() bloquean repetir el mismo horario dos
 * veces EN LA MISMA prescripción (chequeo + índice único como respaldo ante
 * condiciones de carrera) — no bloquean que dos prescripciones distintas
 * compartan hora, eso es normal. destroy() es hard delete: la tabla no tiene
 * softDeletes.
 */
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

        // No bloquea que dos prescripciones distintas compartan hora (normal:
        // varios medicamentos administrados juntos) — solo que la MISMA
        // prescripción repita el mismo horario dos veces.
        $duplicate = MedicationSchedule::where('prescription_id', $validated['prescription_id'])
            ->where('scheduled_time', $validated['scheduled_time'])
            ->exists();
        if ($duplicate) {
            return response()->json([
                'message' => 'Esta prescripción ya tiene un horario a las ' . $validated['scheduled_time'] . '.',
            ], 422);
        }

        try {
            $item = MedicationSchedule::create($validated);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'Esta prescripción ya tiene un horario a las ' . $validated['scheduled_time'] . '.',
                ], 422);
            }
            throw $e;
        }

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

        if (isset($validated['scheduled_time'])) {
            $duplicate = MedicationSchedule::where('prescription_id', $item->prescription_id)
                ->where('scheduled_time', $validated['scheduled_time'])
                ->where('id', '!=', $item->id)
                ->exists();
            if ($duplicate) {
                return response()->json([
                    'message' => 'Esta prescripción ya tiene un horario a las ' . $validated['scheduled_time'] . '.',
                ], 422);
            }
        }

        try {
            $item->update($validated);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'Esta prescripción ya tiene un horario a las ' . $validated['scheduled_time'] . '.',
                ], 422);
            }
            throw $e;
        }

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
