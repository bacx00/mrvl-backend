<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Rules\StrongPassword;

// Test cases for password validation
$testPasswords = [
    // Should FAIL - too short
    'Pass1!',
    
    // Should FAIL - no uppercase
    'password123!',
    
    // Should FAIL - no lowercase
    'PASSWORD123!',
    
    // Should FAIL - no number
    'Password!',
    
    // Should FAIL - no special character
    'Password123',
    
    // Should FAIL - has spaces
    'Pass word123!',
    
    // Should FAIL - common password
    'Password123!',
    
    // Should PASS - meets all requirements
    'SecureP@ss123',
    'MyStr0ng#Pass',
    'Test@2024Password',
    'Admin$ecure99',
];

echo "Testing Password Validation Rules\n";
echo "=================================\n\n";

$rule = new StrongPassword();

foreach ($testPasswords as $password) {
    $errors = [];
    $rule->validate('password', $password, function($message) use (&$errors) {
        $errors[] = $message;
    });
    
    if (empty($errors)) {
        echo "✅ VALID: '$password'\n";
    } else {
        echo "❌ INVALID: '$password'\n";
        foreach ($errors as $error) {
            echo "   - $error\n";
        }
    }
    echo "\n";
}

echo "\nPassword Requirements:\n";
echo "- Minimum 8 characters\n";
echo "- At least one uppercase letter (A-Z)\n";
echo "- At least one lowercase letter (a-z)\n";
echo "- At least one number (0-9)\n";
echo "- At least one special character (@$!%*#?&^-_+=)\n";
echo "- No spaces allowed\n";
echo "- Cannot be a common password\n";