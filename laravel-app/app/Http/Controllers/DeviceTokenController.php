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

        $platform = $validated['platform'] ?? 'web';
        $userAgent = $request->userAgent();

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $platform,
                'user_agent' => $userAgent,
                'last_used_at' => now(),
            ]
        );

        // Firebase cambia el token de un mismo aparato cada cierto tiempo: al
        // reinstalar la app, al volver a conceder el permiso, o cuando el
        // navegador rehace la suscripción. La fila vieja se quedaba para siempre
        // (el DELETE solo corre al cerrar sesión, y solo para el token actual),
        // y mientras Firebase siguiera aceptando los dos, el mismo teléfono
        // recibía CADA aviso por duplicado. Pasó en producción: un solo envío de
        // prueba llegó dos veces.
        //
        // Al registrar, entonces, se descartan los tokens anteriores de este
        // mismo usuario que vengan del mismo aparato — misma plataforma y mismo
        // user_agent. Se compara con user_agent porque es lo único que identifica
        // al dispositivo sin cambiar la base de datos ni la app.
        //
        // Efecto secundario aceptado: si una persona usa dos aparatos idénticos
        // (mismo modelo y misma versión del sistema) con la MISMA cuenta, solo
        // recibirá los avisos en el último donde abrió la aplicación. Es raro, y
        // es preferible a que todo el personal reciba cada aviso repetido.
        if (!empty($userAgent)) {
            DeviceToken::where('user_id', $request->user()->id)
                ->where('platform', $platform)
                ->where('user_agent', $userAgent)
                ->where('id', '!=', $deviceToken->id)
                ->delete();
        }

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
