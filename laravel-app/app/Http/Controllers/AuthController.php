<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        return response()->json([
            'message' => 'Cierre de sesión exitoso'
        ], 200);
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
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'sometimes|string',
            'address' => 'sometimes|string',
            'emergency_contact' => 'sometimes|string',
            'emergency_phone' => 'sometimes|string',
            'profile_image' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (isset($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        if ($request->hasFile('profile_image')) {
            if ($user->profile_image) {
                Storage::disk($this->imageDisk())->delete($user->profile_image);
            }
            $validatedData['profile_image'] = $request->file('profile_image')->store('profile-images', $this->imageDisk());
        }

        $user->update($validatedData);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => $user
        ], 200);
    }
}
