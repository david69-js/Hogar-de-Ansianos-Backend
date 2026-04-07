<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ejemplo; // Reemplaza con tu modelo real

class EjemploController extends Controller
{
    /**
     * Leer (Read All)
     * Obtiene todos los registros que estén activos (por ejemplo, estado = 1 o true)
     */
    public function index()
    {
        // Asumiendo que tienes un campo 'estado' para el soft-delete manual
        // O si usas el trait SoftDeletes de Laravel, solo sería Ejemplo::all()
        $registros = Ejemplo::where('estado', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $registros
        ], 200);
    }

    /**
     * Leer Uno (Read One)
     */
    public function show($id)
    {
        $registro = Ejemplo::find($id);

        if (!$registro) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $registro
        ], 200);
    }

    /**
     * Crear (Create)
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255',
            // Agrega tus campos aquí...
        ]);

        // Por defecto lo creamos con estado activo
        $validatedData['estado'] = true; 

        $registro = Ejemplo::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Registro creado exitosamente',
            'data' => $registro
        ], 201);
    }

    /**
     * Actualizar (Update)
     */
    public function update(Request $request, $id)
    {
        $registro = Ejemplo::find($id);

        if (!$registro) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        $validatedData = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            // Agrega tus campos aquí...
        ]);

        $registro->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado exitosamente',
            'data' => $registro
        ], 200);
    }

    /**
     * Eliminar (Soft Delete - Cambiar de estado)
     */
    public function destroy($id)
    {
        $registro = Ejemplo::find($id);

        if (!$registro) {
            return response()->json(['success' => false, 'message' => 'No encontrado'], 404);
        }

        // En lugar de $registro->delete(), solo cambiamos el estado a false / 0
        $registro->estado = false;
        $registro->save();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado (desactivado) exitosamente'
        ], 200);
    }
}
