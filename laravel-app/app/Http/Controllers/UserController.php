<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $items = User::all();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = User::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $item = User::create($request->all());
        return response()->json([
            'message' => 'Creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = User::findOrFail($id);
        $item->update($request->all());
        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = User::findOrFail($id);
        $item->delete();
        return response()->json([
            'message' => 'Eliminado (estado inactivo) exitosamente'
        ], 200);
    }
}
