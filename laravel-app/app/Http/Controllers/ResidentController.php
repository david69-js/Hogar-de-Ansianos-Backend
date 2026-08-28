<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resident;

/**
 * CRUD de residentes. Ver está abierto a cualquier rol autenticado;
 * crear/editar requiere `create_residents`/`edit_residents`, desactivar/
 * restaurar requiere `delete_residents` — todo Admin en la práctica (ver
 * routes/api.php). index()/show() siempre incluyen los desactivados
 * (withTrashed) para poder listarlos y reactivarlos; destroy() es baja lógica
 * reversible (SoftDeletes), no elimina nada físicamente.
 */
class ResidentController extends Controller
{
    public function index()
    {
        // Incluye también los residentes desactivados (soft-deleted) para que el
        // frontend pueda mostrarlos con su estado y permitir reactivarlos.
        $residents = Resident::withTrashed()->orderBy('first_name')->get();
        return response()->json($residents, 200);
    }

    public function show($id)
    {
        $resident = Resident::withTrashed()->findOrFail($id);
        return response()->json($resident, 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'second_last_name' => 'nullable|string|max:255',
            'dpi' => 'required|string|max:255|unique:residents,dpi',
            'birth_date' => 'required|date',
            'gender' => 'nullable|string|max:20',
            'room_number' => 'nullable|string|max:50',
            'admission_date' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'allergies' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $resident = Resident::create($validatedData);

        return response()->json([
            'message' => 'Residente creado exitosamente',
            'resident' => $resident
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $resident = Resident::withTrashed()->findOrFail($id);

        $validatedData = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'second_last_name' => 'nullable|string|max:255',
            'dpi' => 'nullable|string|max:255|unique:residents,dpi,' . $resident->id,
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|string|max:20',
            'room_number' => 'nullable|string|max:50',
            'admission_date' => 'nullable|date',
            'blood_type' => 'nullable|string|max:10',
            'weight' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'allergies' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:50',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $resident->update($validatedData);

        return response()->json([
            'message' => 'Residente actualizado exitosamente',
            'resident' => $resident
        ], 200);
    }

    public function destroy($id)
    {
        $resident = Resident::findOrFail($id);

        // Gracias a SoftDeletes, delete() no lo elimina de la base de datos,
        // solo le asigna la fecha actual a la columna deleted_at (= "desactivar").
        $resident->delete();

        return response()->json([
            'message' => 'Residente desactivado exitosamente'
        ], 200);
    }

    // POST /api/residents/{id}/restore → Reactiva un residente desactivado
    public function restore($id)
    {
        $resident = Resident::withTrashed()->findOrFail($id);
        $resident->restore();

        return response()->json([
            'message' => 'Residente activado exitosamente',
            'resident' => $resident,
        ], 200);
    }
}
