<?php

use App\Http\Controllers\Api\ChildApi\AuthController as ChildAuthController;
use App\Http\Controllers\Api\ChildApi\ChatbotController;
use App\Http\Controllers\Api\ChildApi\GameController;
use App\Http\Controllers\Api\ChildApi\SessionController;
use App\Http\Controllers\Api\ParentApi\AuthController as ParentAuthController;
use App\Http\Controllers\Api\ParentApi\ChildController;
use App\Http\Controllers\Api\ParentApi\DashboardController;
use App\Http\Controllers\Api\ParentApi\NotificationController;
use App\Http\Controllers\Api\ParentApi\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => 'KidZoo API',
    'time' => now()->toIso8601String(),
]));

Route::prefix('parent')->group(function () {
    Route::post('/register', [ParentAuthController::class, 'register']);
    Route::post('/verify-email', [ParentAuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [ParentAuthController::class, 'resendVerificationOtp']);
    Route::post('/login', [ParentAuthController::class, 'login']);
    Route::post('/forgot-password', [ParentAuthController::class, 'forgotPassword']);
    Route::post('/verify-reset-otp', [ParentAuthController::class, 'verifyResetOtp']);

    Route::middleware(['auth:sanctum', 'ability:password-reset'])->group(function () {
        Route::post('/reset-password', [ParentAuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'ability:role:parent'])->group(function () {
        Route::get('/me', [ParentAuthController::class, 'me']);
        Route::post('/logout', [ParentAuthController::class, 'logout']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);

        Route::apiResource('children', ChildController::class);

        Route::get('/children/{child}/dashboard', [DashboardController::class, 'show']);
        Route::get('/children/{child}/predictions', [DashboardController::class, 'predictions']);
        Route::get('/children/{child}/sessions', [DashboardController::class, 'sessions']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

        Route::get('/chatbot/history', [ChatbotController::class, 'history']);
        Route::post('/chatbot/send', [ChatbotController::class, 'send']);
    });
});

Route::prefix('child')->group(function () {
    Route::post('/login', [ChildAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'ability:role:child'])->group(function () {
        Route::get('/me', [ChildAuthController::class, 'me']);
        Route::post('/logout', [ChildAuthController::class, 'logout']);

        Route::get('/games', [GameController::class, 'index']);
        Route::get('/games/{game}', [GameController::class, 'show']);

        Route::post('/sessions/start', [SessionController::class, 'start']);
        Route::post('/sessions/{session}/trials', [SessionController::class, 'submitTrial']);
        Route::post('/sessions/{session}/end', [SessionController::class, 'end']);
        Route::get('/sessions/history', [SessionController::class, 'history']);
        Route::get('/sessions/{session}', [SessionController::class, 'show']);

        Route::get('/chatbot/history', [ChatbotController::class, 'history']);
        Route::post('/chatbot/send', [ChatbotController::class, 'send']);
    });
});
