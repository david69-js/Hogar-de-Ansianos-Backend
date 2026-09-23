<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Recuperación de contraseña con código de 6 dígitos enviado por correo.
 *
 * Se eligió código y no enlace porque el frontend corre como web (Cloudflare
 * Pages) y como APK de Android: un enlace exigiría deep links en Android,
 * mientras que un código funciona igual en ambas plataformas.
 *
 * Ambas rutas son públicas (sin token) — son justamente para quien no puede
 * entrar. De ahí que todo el cuidado esté en no filtrar información:
 * forgot() responde lo mismo exista o no la cuenta, para que nadie pueda usar
 * este endpoint como un detector de correos registrados.
 */
class PasswordResetController extends Controller
{
    /** Minutos que el código sigue siendo válido. */
    private const CODE_TTL_MINUTES = 15;

    /** Segundos que hay que esperar antes de pedir otro código. */
    private const RESEND_COOLDOWN_SECONDS = 60;

    /** Intentos fallidos antes de invalidar el código por completo. */
    private const MAX_ATTEMPTS = 5;

    // POST /api/password/forgot  { email }
    public function forgot(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
        ]);

        $email = mb_strtolower(trim($validated['email']));

        // Respuesta única para todos los caminos: cuenta inexistente, inactiva
        // o correo enviado. Si variara, este endpoint diría quién tiene cuenta.
        $genericResponse = response()->json([
            'message' => 'Si el correo está registrado, recibirás un código en unos momentos.',
        ], 200);

        $user = User::where('email', $email)->first();

        if (!$user || ($user->status !== 'active' && $user->status !== null)) {
            return $genericResponse;
        }

        // Sin correo de recuperación no hay recuperación automática.
        //
        // Antes el código se mandaba al correo institucional, que en este hogar
        // suele ser un buzón que nadie revisa: la persona pedía el código, no le
        // llegaba nunca, y no tenía forma de saber por qué. Ahora la única vía es
        // que la administración le asigne una contraseña nueva desde Personal,
        // que además la entrega en persona y verifica de quién se trata.
        //
        // Se devuelve la MISMA respuesta genérica: si acá se dijera "esa cuenta
        // no tiene correo de recuperación", este endpoint revelaría qué correos
        // están registrados, que es justo lo que evita responder siempre igual.
        if (!$user->recovery_email) {
            return $genericResponse;
        }

        $existing = DB::table('password_reset_tokens')->where('email', $email)->first();

        // Anti-spam: no reenviar si el código anterior tiene menos de un minuto.
        // Sigue devolviendo la respuesta genérica para no revelar nada.
        // El segundo argumento (absolute: true) es obligatorio aquí: en la versión
        // de Carbon que usa este proyecto, diffInSeconds()/diffInMinutes() devuelven
        // un valor CON SIGNO por defecto (negativo si la fecha comparada ya pasó,
        // que es siempre el caso de created_at). Sin absolute:true, "menos de un
        // minuto" (diff < 60) es válido para CUALQUIER número negativo, así que el
        // cooldown nunca dejaba de bloquear el reenvío una vez creada la primera fila.
        if ($existing && now()->diffInSeconds($existing->created_at, true) < self::RESEND_COOLDOWN_SECONDS) {
            return $genericResponse;
        }

        // random_int es criptográficamente seguro (rand()/mt_rand() no lo son y
        // harían el código predecible).
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'attempts' => 0,
                'created_at' => now(),
            ]
        );

        // Siempre al correo de recuperación: es el personal, el que la persona
        // sí revisa. Que exista ya se verificó arriba.
        $destination = $user->recovery_email;

        try {
            Mail::to($destination)->send(
                new PasswordResetCodeMail($code, $user->first_name ?? '', self::CODE_TTL_MINUTES)
            );
        } catch (\Throwable $e) {
            // Si el proveedor de correo falla, se borra el código: dejarlo vivo
            // sin que nadie lo haya recibido solo bloquearía el reenvío durante
            // el cooldown. El detalle va al log, nunca al cliente.
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            Log::error('No se pudo enviar el código de recuperación', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return $genericResponse;
    }

    // POST /api/password/reset  { email, code, password, password_confirmation }
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'code' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $code = trim($validated['code']);

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        $invalid = fn () => ValidationException::withMessages([
            'code' => ['El código es inválido o ya venció. Solicita uno nuevo.'],
        ]);

        if (!$record) {
            throw $invalid();
        }

        // Vencido: se borra para que un código viejo no quede disponible.
        // absolute:true por la misma razón que en forgot(): sin él, diffInMinutes()
        // da un número negativo para una fecha pasada y ">= 15" nunca es cierto, así
        // que un código jamás vencía (bug confirmado con un código forzado a 16
        // minutos de antigüedad: el backend lo aceptaba como válido).
        if (now()->diffInMinutes($record->created_at, true) >= self::CODE_TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw $invalid();
        }

        if (!Hash::check($code, $record->token)) {
            $attempts = $record->attempts + 1;

            if ($attempts >= self::MAX_ATTEMPTS) {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                throw ValidationException::withMessages([
                    'code' => ['Demasiados intentos fallidos. Solicita un código nuevo.'],
                ]);
            }

            DB::table('password_reset_tokens')->where('email', $email)->update(['attempts' => $attempts]);
            throw $invalid();
        }

        $user = User::where('email', $email)->first();

        if (!$user || ($user->status !== 'active' && $user->status !== null)) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw $invalid();
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        // El código es de un solo uso.
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Cierra toda sesión abierta: si alguien más había entrado con la
        // contraseña vieja, cambiarla debe echarlo fuera de verdad.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.',
        ], 200);
    }
}
