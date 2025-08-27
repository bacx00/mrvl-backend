<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "🔍 Verifying Johnny's Admin Account\n";
echo "===================================\n\n";

try {
    // Check if Johnny's account exists
    $user = User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if (!$user) {
        echo "❌ User jhonny@ar-mediia.com not found!\n";
        echo "Creating admin account...\n\n";
        
        $user = User::create([
            'name' => 'Johnny Admin',
            'email' => 'jhonny@ar-mediia.com',
            'password' => Hash::make('password123'),
            'role' => 'admin'
        ]);
        
        echo "✅ Created new admin account:\n";
    } else {
        echo "✅ User found:\n";
    }
    
    echo "   ID: {$user->id}\n";
    echo "   Name: {$user->name}\n";
    echo "   Email: {$user->email}\n";
    echo "   Role: {$user->role}\n";
    echo "   2FA Enabled: " . ($user->hasTwoFactorEnabled() ? 'Yes' : 'No') . "\n";
    echo "   Must Use 2FA: " . ($user->mustUseTwoFactor() ? 'Yes' : 'No') . "\n";
    
    // Ensure user is admin
    if ($user->role !== 'admin') {
        echo "\n🔧 Updating role to admin...\n";
        $user->update(['role' => 'admin']);
        echo "✅ Role updated to admin\n";
    }
    
    // Test password
    if (!Hash::check('password123', $user->password)) {
        echo "\n🔧 Updating password...\n";
        $user->update(['password' => Hash::make('password123')]);
        echo "✅ Password updated\n";
    }
    
    echo "\n✅ Account verification complete!\n";
    echo "\n📱 Next Steps:\n";
    echo "1. Use your authenticator app (Google Authenticator, Authy, etc.)\n";
    echo "2. Try logging in with the curl commands from the test script\n";
    echo "3. Follow the 2FA setup flow\n";
    echo "4. Save your recovery codes when provided\n\n";
    
    echo "🔐 Login Details:\n";
    echo "Email: jhonny@ar-mediia.com\n";
    echo "Password: password123\n";
    echo "Role: admin (requires 2FA)\n\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>