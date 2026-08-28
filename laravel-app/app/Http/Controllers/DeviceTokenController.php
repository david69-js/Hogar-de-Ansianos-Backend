<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

/**
 * Registro/baja de tokens de Firebase Cloud Messaging por dispositivo (no por
 * sesión). El frontend debe llamar a destroy() al cerrar sesión — si no, el
 * dispositivo sigue recibiendo pushes aunque nadie esté logueado en él.
 */
class DeviceTokenController extends Controller
{
    /**
     * Registra (o actualiza el dueño de) un token de dispositivo/navegador.
     * Se llama cada vez que el frontend obtiene/renueva su token de FCM.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'platform' => 'nullable|string|in:web,android,ios',
        ]);

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'web',
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Token registrado exitosamente',
            'data' => $deviceToken,
        ], 200);
    }

    /**
     * Elimina un token (ej. al cerrar sesión o revocar el permiso de notificaciones).
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['message' => 'Token eliminado exitosamente'], 200);
    }

    /**
     * Envía una notificación de prueba a todos los dispositivos del usuario autenticado.
     * Útil para verificar que el pipeline completo (token -> backend -> Firebase -> dispositivo) funciona.
     */
    public function test(Request $request, FirebaseService $firebase)
    {
        $result = $firebase->sendToUser(
            $request->user(),
            'Notificación de prueba',
            '¡Las notificaciones push están funcionando correctamente!'
        );

        return response()->json($result, 200);
    }
}
