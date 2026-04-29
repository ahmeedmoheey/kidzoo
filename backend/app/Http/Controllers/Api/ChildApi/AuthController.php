<?php

namespace App\Http\Controllers\Api\ChildApi;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $data['username'] = trim($data['username']);

        $child = Child::where('username', $data['username'])->where('is_active', true)->first();

        if (! $child || ! Hash::check($data['password'], $child->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $child->update(['last_login_at' => now()]);

        $token = $child->createToken('child-auth', ['role:child'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'child' => $child,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['child' => $request->user()]);
    }
}
