<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ResidentImage;

class ResidentImageController extends Controller
{
    public function index()
    {
        $items = ResidentImage::all();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = ResidentImage::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $item = ResidentImage::create($request->all());
        return response()->json([
            'message' => 'Creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = ResidentImage::findOrFail($id);
        $item->update($request->all());
        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = ResidentImage::findOrFail($id);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes
        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
