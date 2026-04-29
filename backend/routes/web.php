<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'kidzoo-backend',
    ]);
});

Route::get('/docs', function () {
    return response()->file(base_path('API_DOCUMENTATION.md'), [
        'Content-Type' => 'text/markdown; charset=UTF-8',
    ]);
});
