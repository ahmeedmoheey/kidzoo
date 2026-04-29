<?php

namespace App\Http\Controllers\Api\ParentApi;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function __construct(private readonly OtpService $otp)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
        ]);

        $otp = $this->otp->generate($user->email, OtpService::PURPOSE_VERIFY_EMAIL);

        return response()->json($this->withOtpDebug([
            'message' => 'Registration successful. Please verify your email with the OTP sent.',
            'email' => $user->email,
        ], $otp->otp), 201);
    }

    public function resendVerificationOtp(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $data['email'] = strtolower(trim($data['email']));

        $user = User::where('email', $data['email'])->firstOrFail();

        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified.'], 400);
        }

        $otp = $this->otp->generate($user->email, OtpService::PURPOSE_VERIFY_EMAIL, true);

        return response()->json($this->withOtpDebug([
            'message' => 'OTP sent.',
        ], $otp->otp));
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        $verified = $this->otp->verify($data['email'], $data['otp'], OtpService::PURPOSE_VERIFY_EMAIL);

        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['email_verified_at' => now()]);

        $token = $user->createToken('parent-auth', ['role:parent'])->plainTextToken;

        return response()->json($this->authPayload([
            'message' => 'Email verified successfully.',
            'token' => $token,
        ], $user));
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->email_verified_at) {
            if (app()->environment('local')) {
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
            $otp = $this->otp->generate($user->email, OtpService::PURPOSE_VERIFY_EMAIL);
            return response()->json($this->withOtpDebug([
                'message' => 'Email not verified. Use the latest OTP sent to your email or request a resend.',
                'requires_verification' => true,
                'email' => $user->email,
            ], $otp->otp), 403);
            }
        }

        $token = $user->createToken('parent-auth', ['role:parent'])->plainTextToken;

        return response()->json($this->authPayload([
            'message' => 'Login successful.',
            'token' => $token,
        ], $user));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $data['email'] = strtolower(trim($data['email']));

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $otp = $this->otp->generate($user->email, OtpService::PURPOSE_RESET_PASSWORD, true);
        } else {
            $otp = null;
        }

        return response()->json($this->withOtpDebug([
            'message' => 'If this email is registered, an OTP has been sent.',
        ], $otp?->otp));
    }

    public function verifyResetOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $data['email'] = strtolower(trim($data['email']));

        $verified = $this->otp->verify($data['email'], $data['otp'], OtpService::PURPOSE_RESET_PASSWORD);

        if (! $verified) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $resetToken = $user->createToken('password-reset', ['password-reset'], now()->addMinutes(15))->plainTextToken;

        return response()->json([
            'message' => 'OTP verified. Use the reset_token to set a new password.',
            'reset_token' => $resetToken,
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = $request->user();

        if (! $user || ! $request->user()->tokenCan('password-reset')) {
            return response()->json(['message' => 'Invalid reset token.'], 401);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset successfully. Please login again.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->authPayload([], $request->user()));
    }

    private function withOtpDebug(array $payload, ?string $otp): array
    {
        if (app()->environment('local') && $otp) {
            $payload['debug_otp'] = $otp;
        }

        return $payload;
    }

    private function authPayload(array $payload, User $user): array
    {
        $user->load('children');

        return array_merge($payload, [
            'user' => $user,
            'children' => $user->children,
            'has_children' => $user->children->isNotEmpty(),
        ]);
    }
}
