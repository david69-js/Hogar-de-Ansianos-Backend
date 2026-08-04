<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medication;
use App\Models\Prescription;

class MedicationController extends Controller
{
    public function index()
    {
        $items = Medication::orderBy('name')->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = Medication::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:medications,name',
            'description' => 'nullable|string',
            'dosage_form' => 'nullable|string|max:100',
            // stock_quantity y expiration_date NO se aceptan aquí: solo cambian a través de
            // un movimiento en /medication-stock-movements, para que quede su rastro en el
            // kardex. minimum_stock sí es config editable junto con el resto del catálogo.
            'minimum_stock' => 'nullable|integer|min:0',
        ]);

        $item = Medication::create($validated);

        return response()->json([
            'message' => 'Medicamento creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = Medication::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:medications,name,' . $item->id,
            'description' => 'nullable|string',
            'dosage_form' => 'nullable|string|max:100',
            'minimum_stock' => 'nullable|integer|min:0',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Medicamento actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = Medication::findOrFail($id);

        // La FK de prescriptions.medication_id tiene onDelete('cascade'). El soft-delete de
        // Eloquent no dispara esa cascada (la fila sigue existiendo físicamente), pero si el
        // medicamento queda "eliminado" el scope global de SoftDeletes lo esconde de futuras
        // consultas (incluida la que usa el frontend para mostrar el nombre en prescripciones
        // existentes). Por eso bloqueamos el borrado mientras esté en uso, igual que con las
        // condiciones médicas.
        $inUse = Prescription::where('medication_id', $item->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: este medicamento está en una o más prescripciones. Descontinúalas primero.'
            ], 409);
        }

        $item->delete();

        return response()->json([
            'message' => 'Medicamento eliminado exitosamente'
        ], 200);
    }
}
