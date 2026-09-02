<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

// Solo lectura a propósito: las filas las genera únicamente AuditableObserver.
// Un audit log que se pueda escribir/borrar por API deja de servir como prueba
// de qué pasó — por eso no hay store/update/destroy aquí ni en las rutas.
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('user')->latest();

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->query('table_name'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $items = $query->paginate(50);
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = AuditLog::with('user')->findOrFail($id);
        return response()->json($item, 200);
    }
}
