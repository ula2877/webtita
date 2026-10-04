<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
        ], [
            'user_id.required' => 'Silakan pilih nama.',
            'user_id.integer' => 'Silakan pilih nama.',
            'password.required' => 'Silakan masukkan password.',
        ]);

        $user = User::find($request->integer('user_id'));

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Nama atau password salah.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => ['Akun Anda tidak aktif. Silakan hubungi administrator.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function loginOptions()
    {
        return response()->json([
            'data' => User::where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'is_active' => (bool) $user->is_active,
                ])
                ->values(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama tidak boleh lebih dari 255 karakter.',
        ]);

        $user = $request->user();
        $user->name = $request->string('name')->trim();
        $user->save();

        return response()->json([
            'message' => 'Nama berhasil diperbarui.',
            'user' => new UserResource($user),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->password = Hash::make($request->string('password'));
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}
