<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;

echo "\n🔧 Password Reset Debug\n";
echo "========================\n\n";

// Check if password_resets table exists
$tablesResult = DB::select("SHOW TABLES");
$tables = array_map(function($table) {
    return array_values((array)$table)[0];
}, $tablesResult);

if (in_array('password_resets', $tables)) {
    echo "✅ password_resets table exists\n";
} else {
    echo "❌ password_resets table NOT found\n";
    echo "📋 Available tables: " . implode(", ", array_slice($tables, 0, 10)) . "...\n";
}

// Check if password_reset_tokens table exists (Laravel 10+ uses this)
if (in_array('password_reset_tokens', $tables)) {
    echo "✅ password_reset_tokens table exists (Laravel 10+)\n";
}

echo "\n";

// Check the password broker configuration
$broker = Password::broker();
echo "📧 Password Broker Info:\n";
echo "   - Table: " . config('auth.passwords.users.table', 'password_resets') . "\n";
echo "   - Expire: " . config('auth.passwords.users.expire', 60) . " minutes\n";
echo "   - Throttle: " . config('auth.passwords.users.throttle', 60) . " seconds\n";

echo "\n";

// Test creating a password reset token
$user = User::where('email', 'jhonny@ar-mediia.com')->first();
if ($user) {
    echo "👤 Testing with user: {$user->email}\n";
    
    try {
        $token = Password::broker()->createToken($user);
        echo "✅ Token created: " . substr($token, 0, 20) . "...\n";
        
        // Check if token is in database
        $table = config('auth.passwords.users.table', 'password_resets');
        $record = DB::table($table)->where('email', $user->email)->first();
        
        if ($record) {
            echo "✅ Token stored in database\n";
        } else {
            echo "❌ Token NOT in database\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error creating token: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Test user not found\n";
}

echo "\n";