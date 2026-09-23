<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout/perfil propio vía Laravel Sanctum (tokens personales, no
 * sesiones). login() rechaza cuentas con status distinto de "active"/null;
 * logout() revoca el token actual (currentAccessToken()->delete()) — el
 * frontend debe llamarlo siempre al cerrar sesión, junto con
 * DELETE /device-tokens, o el token y el push del dispositivo siguen vivos
 * indefinidamente. register() existe pero el frontend no lo usa (alta de
 * personal se hace vía UserController, solo Admin).
 */
class AuthController extends Controller
{
    private function imageDisk(): string
    {
        return config('filesystems.default') === 'r2' ? 'r2' : 'public';
    }

    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'second_last_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'dpi' => 'required|string',
            'phone' => 'required|string',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'first_name' => $validatedData['first_name'],
            'middle_name' => $validatedData['middle_name'] ?? null,
            'last_name' => $validatedData['last_name'],
            'second_last_name' => $validatedData['second_last_name'] ?? null,
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'dpi' => $validatedData['dpi'],
            'phone' => $validatedData['phone'],
            'role' => $validatedData['role'],
            'status' => 'active'
        ]);

        // Asignar el rol Staff por defecto al registrarse
        try {
            $user->assignRole('Staff');
        } catch (\Exception $e) {
            // En caso de que el rol Staff no exista aún, no explota
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado existosamente',
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validatedData['email'])->first();

        // Validar credenciales y que el usuario esté activo (no inactivo)
        if (!$user || !Hash::check($validatedData['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }
        
        if ($user->status !== 'active' && $user->status !== null) {
            throw ValidationException::withMessages([
                'email' => ['Esta cuenta se encuentra inactiva.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Actualizar último login
        $user->last_login_at = now();
        $user->save();

        // El Reporte de Auditoría de la tesis pide los inicios de sesión. Se
        // registra aquí a mano porque el cambio de last_login_at de arriba NO
        // genera fila en AuditableObserver (está entre sus campos ignorados).
        $this->recordSessionEvent('login', $user);

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function logout(Request $request)
    {
        // Borra el token actual
        $request->user()->currentAccessToken()->delete();

        $this->recordSessionEvent('logout', $request->user());

        return response()->json([
            'message' => 'Cierre de sesión exitoso'
        ], 200);
    }

    // Fila de auditoría para inicio/cierre de sesión. No pasa por
    // AuditableObserver porque no es un cambio de datos del modelo, es un evento.
    private function recordSessionEvent(string $action, User $user): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'table_name' => 'users',
            'record_id' => $user->id,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ], 200);
    }
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validatedData = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            // Los cuatro campos del nombre, igual que en el alta de personal.
            // Antes solo se aceptaban el primero y el tercero: los otros dos
            // llegaban del formulario y se descartaban en silencio, así que
            // quien no fuera Admin no tenía forma de corregir su propio nombre.
            'middle_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'second_last_name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'recovery_email' => 'sometimes|nullable|string|email|max:255',
            'password' => 'sometimes|string|min:8',
            // nullable en los campos opcionales: el formulario de perfil manda
            // "" cuando están vacíos, y el middleware ConvertEmptyStringsToNull
            // los vuelve null antes de validar. Sin nullable, un usuario sin
            // dirección o sin cargo recibía 422 al guardar cualquier cambio.
            'phone' => 'sometimes|nullable|string',
            'position' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string',
            'emergency_contact' => 'sometimes|nullable|string',
            'emergency_phone' => 'sometimes|nullable|string',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:12288',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk($this->imageDisk())->delete($user->profile_image);
            }
            $validatedData['profile_image'] = ImageOptimizer::store($request->file('profile_image'), 'profile-images', $this->imageDisk(), 600, 85);
        }

        $user->update($validatedData);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => $user
        ], 200);
    }
}
