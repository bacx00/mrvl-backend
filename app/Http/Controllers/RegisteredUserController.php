<?php

// Within the register controller's store method (after user creation):
$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'password' => Hash::make($request->password),
]);
$user->assignRole('User');  // Assign default role

// Optionally, send email verification if enabled...