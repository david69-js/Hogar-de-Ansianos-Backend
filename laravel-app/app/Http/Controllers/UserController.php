<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\User;

class UserController extends Controller
{
    // GET /api/users
    public function index()
    {
        $users = User::with('roles')->get();
        return response()->json($users, 200);
    }

    // GET /api/users/{id}
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return response()->json($user, 200);
    }

    // POST /api/users
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', 'string', Password::min(8)],
            'dpi'        => 'required|string|unique:users,dpi',
            'phone'      => 'required|string|max:20',
            'role'       => 'required|string|exists:roles,name',
            'position'   => 'nullable|string|max:255',
            'hire_date'  => 'nullable|date',
            'address'    => 'nullable|string|max:500',
            'profile_image' => 'nullable|string|max:255',
            'status'     => 'nullable|string|in:active,inactive',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone'   => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'first_name'    => $validated['first_name'],
            'last_name'     => $validated['last_name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'dpi'           => $validated['dpi'],
            'phone'         => $validated['phone'],
            'role'          => $validated['role'],
            'position'      => $validated['position'] ?? null,
            'hire_date'     => $validated['hire_date'] ?? null,
            'address'       => $validated['address'] ?? null,
            'profile_image' => $validated['profile_image'] ?? null,
            'status'        => $validated['status'] ?? 'active',
            'emergency_contact' => $validated['emergency_contact'] ?? null,
            'emergency_phone'   => $validated['emergency_phone'] ?? null,
        ]);

        // Asignar el rol en Spatie
        try {
            $user->assignRole($validated['role']);
        } catch (\Exception $e) {
            // Si el rol no existe en Spatie, continúa sin explotar
        }

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'data'    => $user->load('roles'),
        ], 201);
    }

    // PUT/PATCH /api/users/{id}
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'password'   => ['sometimes', 'string', Password::min(8)],
            'dpi'        => 'sometimes|string|unique:users,dpi,' . $user->id,
            'phone'      => 'sometimes|string|max:20',
            'role'       => 'sometimes|string|exists:roles,name',
            'position'   => 'nullable|string|max:255',
            'hire_date'  => 'nullable|date',
            'address'    => 'nullable|string|max:500',
            'profile_image' => 'nullable|string|max:255',
            'status'     => 'sometimes|in:active,inactive',
            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone'   => 'nullable|string|max:20',
        ]);

        // Hash de password si viene en el request
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Cambiar rol en Spatie si viene
        if (isset($validated['role'])) {
            try {
                $user->syncRoles([$validated['role']]);
            } catch (\Exception $e) {
                // Continúa sin explotar
            }
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data'    => $user->load('roles'),
        ], 200);
    }

    // DELETE /api/users/{id} → Soft delete cambiando status a 'inactive'
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => 'inactive']);

        return response()->json([
            'message' => 'Usuario desactivado exitosamente',
        ], 200);
    }
}

