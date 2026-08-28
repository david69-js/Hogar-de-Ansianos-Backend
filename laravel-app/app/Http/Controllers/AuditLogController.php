<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

// Solo lectura a propósito: las filas las genera únicamente AuditableObserver.
// Un audit log que se pueda escribir/borrar por API deja de servir como prueba
// de qué pasó — por eso no hay store/update/destroy aquí ni en las rutas.
class AuditLogController extends Controller
{
    public function index()
    {
        $items = AuditLog::latest()->paginate(50);
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = AuditLog::findOrFail($id);
        return response()->json($item, 200);
    }
}
