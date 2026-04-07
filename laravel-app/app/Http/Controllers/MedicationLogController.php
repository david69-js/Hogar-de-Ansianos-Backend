<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedicationLog;

class MedicationLogController extends Controller
{
    public function index()
    {
        $items = MedicationLog::all();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = MedicationLog::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $item = MedicationLog::create($request->all());
        return response()->json([
            'message' => 'Creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = MedicationLog::findOrFail($id);
        $item->update($request->all());
        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = MedicationLog::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
