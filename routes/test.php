<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post('/api/auth/test-login', function (Request $request) {
    $input = $request->all();
    $email = $request->input('email');
    $password = $request->input('password');
    
    return response()->json([
        'received_data' => $input,
        'email' => $email,
        'password' => $password,
        'raw_content' => $request->getContent(),
        'headers' => $request->headers->all()
    ]);
});