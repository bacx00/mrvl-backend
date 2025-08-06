<?php

echo "Testing Bracket Generation API\n";
echo "==============================\n\n";

// Test the bracket generation API endpoint
$eventId = 17; // Using the Marvel Rivals event
$apiUrl = "http://localhost:8000/api/admin/events/{$eventId}/bracket/generate";

// Test data matching the failing request
$testData = [
    'format' => 'single_elimination',
    'seeding_type' => 'rating', 
    'match_format' => 'bo3',
    'finals_format' => 'bo5'
];

echo "Testing endpoint: {$apiUrl}\n";
echo "Test data: " . json_encode($testData) . "\n\n";

// We need to test with admin authentication
// First, let's check if there are admin users
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    // Get admin user
    $adminUser = DB::table('users')->where('role', 'admin')->first();
    
    if (!$adminUser) {
        echo "No admin user found. Creating temporary admin...\n";
        $adminId = DB::table('users')->insertGetId([
            'username' => 'test_admin_' . time(),
            'email' => 'testadmin' . time() . '@test.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        $adminUser = DB::table('users')->where('id', $adminId)->first();
        echo "Created admin user ID: {$adminId}\n";
    }
    
    // Create a personal access token for API testing
    $user = App\Models\User::find($adminUser->id);
    $token = $user->createToken('bracket-test')->accessToken;
    
    echo "Using admin user ID: {$adminUser->id}\n";
    echo "Token created for authentication\n\n";
    
    // Make the API request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    echo "Making API request...\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "CURL Error: {$error}\n";
        exit(1);
    }
    
    echo "HTTP Status: {$httpCode}\n";
    echo "Response:\n";
    
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        
        if (isset($responseData['success']) && $responseData['success']) {
            echo "\n✓ BRACKET GENERATION SUCCESS!\n";
            echo "  Matches created: " . ($responseData['data']['matches_created'] ?? 'unknown') . "\n";
            echo "  Format: " . ($responseData['data']['format'] ?? 'unknown') . "\n";
            
            // Verify matches in database
            $matches = DB::table('matches')->where('event_id', $eventId)->get();
            $scheduledMatches = $matches->filter(function($match) {
                return !is_null($match->scheduled_at);
            });
            
            echo "  Matches in DB: " . count($matches) . "\n";
            echo "  With scheduled_at: " . count($scheduledMatches) . "\n";
            
            // Check format distribution
            $formatCounts = $matches->groupBy('format')->map->count();
            echo "  Format distribution: " . json_encode($formatCounts->toArray()) . "\n";
            
        } else {
            echo "\n✗ BRACKET GENERATION FAILED\n";
            echo "  Error: " . ($responseData['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "Raw response: {$response}\n";
    }
    
} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}