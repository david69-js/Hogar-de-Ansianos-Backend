<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Expone el registro de auditoría en solo lectura (index/show). Protegido por
 * `manage_users` en las rutas (solo Admin) — ver routes/api.php. Las filas las
 * genera únicamente App\Observers\AuditableObserver cuando se guarda/borra un
 * modelo observado; un audit log que se pueda escribir/borrar por API deja de
 * servir como prueba de qué pasó, por eso no hay store/update/destroy aquí ni
 * en las rutas (apiResource solo registra ['index', 'show']).
 */
class AuditLogController extends Controller
{
    // GET /api/audit-logs — filtra por tabla/acción/usuario (todos opcionales,
    // combinables) y pagina de 50 en 50, más reciente primero.
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

    // GET /api/audit-logs/{id} — detalle de una fila puntual.
    public function show($id)
    {
        $item = AuditLog::with('user')->findOrFail($id);
        return response()->json($item, 200);
    }
}
