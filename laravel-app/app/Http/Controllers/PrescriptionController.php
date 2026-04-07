<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Prescription;

class PrescriptionController extends Controller
{
    public function index()
    {
        $items = Prescription::all();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = Prescription::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $item = Prescription::create($request->all());
        return response()->json([
            'message' => 'Creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = Prescription::findOrFail($id);
        $item->update($request->all());
        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = Prescription::findOrFail($id);
        $item->delete();
        return response()->json([
            'message' => 'Eliminado (estado inactivo) exitosamente'
        ], 200);
    }
}
