<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Prescription;

/**
 * CRUD de prescripciones (ver Prescription para el modelo). index() no filtra
 * por `is_active`: el frontend necesita ver también las descontinuadas como
 * historial médico. destroy() NO borra — pone `is_active=false`
 * ("descontinuar"), reversible con `PUT {is_active:true}`. Los horarios
 * (MedicationSchedule) se gestionan aparte, no aquí.
 */
class PrescriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Prescription::query();

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->query('resident_id'));
        }

        // Sin filtrar por is_active: el frontend necesita ver también las
        // prescripciones descontinuadas (historial médico del residente).
        $items = $query->latest()->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = Prescription::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|exists:residents,id',
            'medication_id' => 'required|exists:medications,id',
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'administration_route' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'instructions' => 'nullable|string',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = true;

        $item = Prescription::create($validated);

        return response()->json([
            'message' => 'Prescripción creada exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = Prescription::findOrFail($id);

        $validated = $request->validate([
            'dosage' => 'nullable|string|max:100',
            'frequency' => 'nullable|string|max:100',
            'administration_route' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'instructions' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Prescripción actualizada exitosamente',
            'data' => $item
        ], 200);
    }

    // DELETE /api/prescriptions/{id} → Descontinuar (is_active = false), NO se borra.
    // Igual que Personal: se conserva para historial y se puede reactivar con
    // PUT {is_active: true}.
    public function destroy($id)
    {
        $item = Prescription::findOrFail($id);
        $item->update(['is_active' => false]);

        return response()->json([
            'message' => 'Prescripción descontinuada exitosamente'
        ], 200);
    }
}
