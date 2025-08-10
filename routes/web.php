<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mention-test', function () {
    return response()->file(base_path('mention_frontend_test.html'));
});
