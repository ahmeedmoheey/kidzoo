<?php

namespace App\Http\Controllers\Api\ParentApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar_url' => ['sometimes', 'nullable', 'url'],
            'language' => ['sometimes', 'string', 'in:en,ar'],
        ]);

        $user->update($data);

        return response()->json(['message' => 'Profile updated.', 'user' => $user->fresh()]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
