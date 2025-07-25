<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use App\Models\User;
use Spatie\Permission\Models\Role;

// Initialize Laravel
$app = new Application(realpath(__DIR__));

// Load configuration
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up database connection
$app['config']->set('database.default', env('DB_CONNECTION', 'sqlite'));
$app['config']->set('database.connections.sqlite', [
    'driver' => 'sqlite',
    'database' => env('DB_DATABASE', __DIR__ . '/database/database.sqlite'),
    'prefix' => '',
]);

echo "🔧 Assigning admin role to jhonny@ar-mediia.com...\n";

try {
    // Find the user
    $user = User::where('email', 'jhonny@ar-mediia.com')->first();
    
    if (!$user) {
        echo "❌ User not found: jhonny@ar-mediia.com\n";
        echo "📝 Available users:\n";
        $users = User::all(['id', 'name', 'email']);
        foreach ($users as $u) {
            echo "  - {$u->id}: {$u->name} ({$u->email})\n";
        }
        exit(1);
    }
    
    echo "✅ Found user: {$user->name} ({$user->email})\n";
    
    // Ensure roles exist
    $roles = ['admin', 'moderator', 'user'];
    foreach ($roles as $roleName) {
        $role = Role::firstOrCreate(['name' => $roleName]);
        echo "✅ Role '{$roleName}' exists (ID: {$role->id})\n";
    }
    
    // Remove any existing roles and assign admin
    $user->syncRoles(['admin']);
    
    echo "🔑 Assigned 'admin' role to {$user->name}\n";
    
    // Verify the assignment
    $userRoles = $user->getRoleNames();
    echo "✅ User roles: " . implode(', ', $userRoles->toArray()) . "\n";
    
    // Check specific role methods
    echo "✅ Has admin role: " . ($user->hasRole('admin') ? 'Yes' : 'No') . "\n";
    echo "✅ Has any role: " . ($user->hasAnyRole(['admin', 'moderator', 'user']) ? 'Yes' : 'No') . "\n";
    
    echo "🎉 Admin role assignment completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
}