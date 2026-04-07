<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resident;

class ResidentController extends Controller
{
    public function index()
    {
        // Trae todos los residentes activos (SoftDeletes oculta automáticamente los "eliminados")
        $residents = Resident::all();
        return response()->json($residents, 200);
    }

    public function show($id)
    {
        $resident = Resident::findOrFail($id);
        return response()->json($resident, 200);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'dpi' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            // Puedes agregar las demás validaciones aquí
        ]);

        $resident = Resident::create($request->all());

        return response()->json([
            'message' => 'Residente creado exitosamente',
            'resident' => $resident
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $resident = Resident::findOrFail($id);
        $resident->update($request->all());

        return response()->json([
            'message' => 'Residente actualizado exitosamente',
            'resident' => $resident
        ], 200);
    }

    public function destroy($id)
    {
        $resident = Resident::findOrFail($id);
        
        // Gracias a SoftDeletes, delete() no lo elimina de la base de datos, 
        // solo le asigna la fecha actual a la columna deleted_at
        $resident->delete();

        return response()->json([
            'message' => 'Residente eliminado (cambio de estado a inactivo) exitosamente'
        ], 200);
    }
}
