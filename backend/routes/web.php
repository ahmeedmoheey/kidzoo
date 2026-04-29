<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/docs', function () {
    return response()->file(base_path('API_DOCUMENTATION.md'), [
        'Content-Type' => 'text/markdown; charset=UTF-8',
    ]);
});
