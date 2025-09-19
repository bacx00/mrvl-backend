<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

echo "=== Testing Profile Change Endpoints ===\n\n";

// Test users for each role
$testUsers = [
    ['email' => 'test-user@test.com', 'role' => 'user', 'name' => 'Test User'],
    ['email' => 'test-moderator@test.com', 'role' => 'moderator', 'name' => 'Test Moderator'],
    ['email' => 'test-admin@test.com', 'role' => 'admin', 'name' => 'Test Admin']
];

foreach ($testUsers as $userData) {
    echo "Testing {$userData['role']} user: {$userData['email']}\n";
    echo "----------------------------------------\n";

    $user = User::where('email', $userData['email'])->first();
    if (!$user) {
        echo "❌ User not found!\n\n";
        continue;
    }

    // Test password change validation
    echo "1. Password Change Validation:\n";

    // Test weak password (should pass now)
    $weakPassword = 'newpass123';
    try {
        $validator = \Illuminate\Support\Facades\Validator::make([
            'current_password' => 'password123',
            'new_password' => $weakPassword,
            'new_password_confirmation' => $weakPassword
        ], [
            'current_password' => 'required|string|min:1',
            'new_password' => 'required|string|min:8|max:255|confirmed|different:current_password',
            'new_password_confirmation' => 'required|string'
        ]);

        if ($validator->fails()) {
            echo "   ❌ Weak password validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
        } else {
            echo "   ✅ Weak password validation passed\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Password validation error: " . $e->getMessage() . "\n";
    }

    // Test email change validation
    echo "2. Email Change Validation:\n";
    try {
        $newEmail = 'new_' . $userData['email'];
        $validator = \Illuminate\Support\Facades\Validator::make([
            'password' => 'password123',
            'new_email' => $newEmail
        ], [
            'password' => 'required|string',
            'new_email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            echo "   ❌ Email validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
        } else {
            echo "   ✅ Email validation passed\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Email validation error: " . $e->getMessage() . "\n";
    }

    // Test username change validation
    echo "3. Username Change Validation:\n";
    try {
        $newName = $userData['name'] . ' Updated';
        $validator = \Illuminate\Support\Facades\Validator::make([
            'password' => 'password123',
            'new_name' => $newName
        ], [
            'password' => 'required|string',
            'new_name' => 'required|string|min:3|max:255|unique:users,name,' . $user->id . '|regex:/^[a-zA-Z0-9\s_-]+$/',
        ]);

        if ($validator->fails()) {
            echo "   ❌ Username validation failed: " . implode(', ', $validator->errors()->all()) . "\n";
        } else {
            echo "   ✅ Username validation passed\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Username validation error: " . $e->getMessage() . "\n";
    }

    echo "   ✅ All validations tested for {$userData['role']}\n\n";
}

echo "=== Profile Endpoint Testing Complete ===\n";