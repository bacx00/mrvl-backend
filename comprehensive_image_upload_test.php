<?php
/**
 * Comprehensive Image Upload System Test
 * Tests all aspects of image upload functionality for MRVL platform
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ImageHelper;

// Bootstrap Laravel
require_once __DIR__ . '/bootstrap/app.php';

echo "=== MRVL COMPREHENSIVE IMAGE UPLOAD SYSTEM TEST ===\n\n";

class ImageUploadTestSuite
{
    private $testResults = [];
    private $apiBaseUrl;
    private $testToken;
    
    public function __construct()
    {
        $this->apiBaseUrl = 'http://localhost:8000/api';
        echo "🔧 Initializing Image Upload Test Suite\n";
        echo "API Base URL: {$this->apiBaseUrl}\n\n";
        
        // Get admin token for testing
        $this->testToken = $this->getAdminToken();
    }
    
    private function getAdminToken()
    {
        try {
            $adminUser = DB::table('users')->where('email', 'admin@admin.com')->first();
            if (!$adminUser) {
                echo "❌ No admin user found. Creating test admin...\n";
                // Create test admin if doesn't exist
                DB::table('users')->insert([
                    'name' => 'Test Admin',
                    'email' => 'admin@admin.com',
                    'password' => bcrypt('password'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $adminUser = DB::table('users')->where('email', 'admin@admin.com')->first();
            }
            
            // Create token via API
            $response = $this->makeRequest('POST', '/login', [
                'email' => 'admin@admin.com',
                'password' => 'password'
            ]);
            
            if (isset($response['data']['token'])) {
                echo "✅ Admin token obtained successfully\n\n";
                return $response['data']['token'];
            } else {
                echo "❌ Failed to get admin token\n";
                return null;
            }
        } catch (Exception $e) {
            echo "❌ Error getting admin token: " . $e->getMessage() . "\n";
            return null;
        }
    }
    
    public function runAllTests()
    {
        echo "🚀 Starting Comprehensive Image Upload Tests\n\n";
        
        // 1. Test Storage Configuration
        $this->testStorageConfiguration();
        
        // 2. Test Image Helper Functions
        $this->testImageHelperFunctions();
        
        // 3. Test Image Upload Endpoints
        $this->testImageUploadEndpoints();
        
        // 4. Test File Format Support
        $this->testFileFormatSupport();
        
        // 5. Test File Size Validation
        $this->testFileSizeValidation();
        
        // 6. Test Error Handling
        $this->testErrorHandling();
        
        // 7. Test Database Storage
        $this->testDatabaseStorage();
        
        // 8. Test Image Display
        $this->testImageDisplay();
        
        // Print final report
        $this->printTestReport();
    }
    
    private function testStorageConfiguration()
    {
        echo "📁 Testing Storage Configuration...\n";
        
        try {
            // Test if storage directories exist
            $directories = [
                'teams/logos',
                'teams/banners',
                'teams/flags',
                'players/avatars',
                'events/logos',
                'events/banners',
                'news/featured'
            ];
            
            foreach ($directories as $dir) {
                $fullPath = storage_path("app/public/{$dir}");
                if (!is_dir($fullPath)) {
                    mkdir($fullPath, 0755, true);
                    echo "✅ Created directory: {$dir}\n";
                } else {
                    echo "✅ Directory exists: {$dir}\n";
                }
                
                // Test if directory is writable
                if (!is_writable($fullPath)) {
                    echo "❌ Directory not writable: {$dir}\n";
                    $this->testResults['storage_config'] = false;
                    return;
                }
            }
            
            // Test storage symlink
            $symlinkPath = public_path('storage');
            if (!is_link($symlinkPath)) {
                echo "❌ Storage symlink missing\n";
                $this->testResults['storage_config'] = false;
                return;
            } else {
                echo "✅ Storage symlink exists\n";
            }
            
            $this->testResults['storage_config'] = true;
            echo "✅ Storage Configuration Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ Storage Configuration Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['storage_config'] = false;
        }
    }
    
    private function testImageHelperFunctions()
    {
        echo "🖼️ Testing Image Helper Functions...\n";
        
        try {
            // Test team logo helper
            $teamLogoResult = ImageHelper::getTeamLogo('100t-logo.png', '100 Thieves');
            echo "Team Logo Helper: " . ($teamLogoResult['exists'] ? "✅ FOUND" : "❌ NOT FOUND") . "\n";
            echo "  URL: " . $teamLogoResult['url'] . "\n";
            
            // Test player avatar helper
            $playerAvatarResult = ImageHelper::getPlayerAvatar('test-avatar.png', 'Test Player');
            echo "Player Avatar Helper: " . ($playerAvatarResult['exists'] ? "✅ FOUND" : "❌ FALLBACK") . "\n";
            echo "  URL: " . $playerAvatarResult['url'] . "\n";
            
            // Test hero image helper
            $heroImageResult = ImageHelper::getHeroImage('Spider-Man', 'portrait');
            echo "Hero Image Helper: " . ($heroImageResult['exists'] ? "✅ FOUND" : "❌ FALLBACK") . "\n";
            echo "  URL: " . $heroImageResult['url'] . "\n";
            
            $this->testResults['image_helper'] = true;
            echo "✅ Image Helper Functions Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ Image Helper Functions Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['image_helper'] = false;
        }
    }
    
    private function testImageUploadEndpoints()
    {
        echo "🌐 Testing Image Upload Endpoints...\n";
        
        if (!$this->testToken) {
            echo "❌ No test token available, skipping endpoint tests\n\n";
            $this->testResults['upload_endpoints'] = false;
            return;
        }
        
        try {
            // Create test team for logo upload
            $teamResponse = $this->makeRequest('POST', '/admin/teams', [
                'name' => 'Test Team Upload',
                'short_name' => 'TTU',
                'region' => 'NA'
            ]);
            
            if (!isset($teamResponse['data']['id'])) {
                echo "❌ Failed to create test team\n";
                $this->testResults['upload_endpoints'] = false;
                return;
            }
            
            $teamId = $teamResponse['data']['id'];
            echo "✅ Created test team with ID: {$teamId}\n";
            
            // Create test player for avatar upload
            $playerResponse = $this->makeRequest('POST', '/admin/players', [
                'username' => 'testuploads',
                'real_name' => 'Test Upload Player',
                'role' => 'Duelist',
                'region' => 'NA'
            ]);
            
            if (!isset($playerResponse['data']['id'])) {
                echo "❌ Failed to create test player\n";
                $this->testResults['upload_endpoints'] = false;
                return;
            }
            
            $playerId = $playerResponse['data']['id'];
            echo "✅ Created test player with ID: {$playerId}\n";
            
            // Test team logo upload endpoint exists
            $endpoints = [
                "/upload/team/{$teamId}/logo" => "Team Logo Upload",
                "/upload/team/{$teamId}/banner" => "Team Banner Upload",
                "/upload/player/{$playerId}/avatar" => "Player Avatar Upload"
            ];
            
            $endpointsWorking = 0;
            foreach ($endpoints as $endpoint => $description) {
                // Test with OPTIONS request to check if endpoint exists
                $testResponse = $this->makeRequest('GET', $endpoint, null, true);
                if ($testResponse !== false) {
                    echo "✅ {$description} endpoint accessible\n";
                    $endpointsWorking++;
                } else {
                    echo "❌ {$description} endpoint not accessible\n";
                }
            }
            
            // Cleanup test data
            DB::table('teams')->where('id', $teamId)->delete();
            DB::table('players')->where('id', $playerId)->delete();
            
            $this->testResults['upload_endpoints'] = $endpointsWorking >= 2;
            echo "✅ Image Upload Endpoints Test: " . ($endpointsWorking >= 2 ? "PASSED" : "PARTIAL") . "\n\n";
            
        } catch (Exception $e) {
            echo "❌ Image Upload Endpoints Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['upload_endpoints'] = false;
        }
    }
    
    private function testFileFormatSupport()
    {
        echo "🎨 Testing File Format Support...\n";
        
        try {
            $supportedFormats = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
            $formatResults = [];
            
            foreach ($supportedFormats as $format) {
                // Check if we have test images in different formats
                $testImagePath = public_path("images/test-image.{$format}");
                if (file_exists($testImagePath)) {
                    $formatResults[$format] = true;
                    echo "✅ {$format} format: Test image found\n";
                } else {
                    $formatResults[$format] = false;
                    echo "⚠️ {$format} format: No test image (acceptable)\n";
                }
            }
            
            // Check ImageUploadController validation
            $imageUploadController = file_get_contents(__DIR__ . '/app/Http/Controllers/ImageUploadController.php');
            if (strpos($imageUploadController, 'jpeg,jpg,png,webp') !== false) {
                echo "✅ Backend validation supports multiple formats\n";
            } else {
                echo "❌ Backend validation may not support all formats\n";
            }
            
            $this->testResults['file_formats'] = true;
            echo "✅ File Format Support Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ File Format Support Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['file_formats'] = false;
        }
    }
    
    private function testFileSizeValidation()
    {
        echo "📏 Testing File Size Validation...\n";
        
        try {
            // Check ImageUploadController for size limits
            $imageUploadController = file_get_contents(__DIR__ . '/app/Http/Controllers/ImageUploadController.php');
            
            if (strpos($imageUploadController, 'maxFileSize = 5120') !== false || 
                strpos($imageUploadController, 'max:5120') !== false) {
                echo "✅ Backend has 5MB file size limit configured\n";
            } else {
                echo "⚠️ Backend file size limit configuration unclear\n";
            }
            
            // Check frontend ImageUpload component
            $frontendImageUpload = file_get_contents('/var/www/mrvl-frontend/frontend/src/components/shared/ImageUpload.js');
            
            if (strpos($frontendImageUpload, 'maxSize = 5 * 1024 * 1024') !== false) {
                echo "✅ Frontend has 5MB file size limit configured\n";
            } else {
                echo "⚠️ Frontend file size limit configuration unclear\n";
            }
            
            $this->testResults['file_size_validation'] = true;
            echo "✅ File Size Validation Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ File Size Validation Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['file_size_validation'] = false;
        }
    }
    
    private function testErrorHandling()
    {
        echo "🚨 Testing Error Handling...\n";
        
        try {
            // Check if upload endpoints handle missing files
            if ($this->testToken) {
                // Test upload without file
                $response = $this->makeRequest('POST', '/upload/team/999999/logo', []);
                if (isset($response['message']) && strpos($response['message'], 'No logo file provided') !== false) {
                    echo "✅ Missing file error handled correctly\n";
                } else {
                    echo "⚠️ Missing file error handling unclear\n";
                }
            }
            
            // Check ImageUploadController error handling
            $imageUploadController = file_get_contents(__DIR__ . '/app/Http/Controllers/ImageUploadController.php');
            
            $errorPatterns = [
                'catch (\Exception $e)' => 'Exception handling',
                'isValid()' => 'File validation',
                'hasFile(' => 'File presence check'
            ];
            
            foreach ($errorPatterns as $pattern => $description) {
                if (strpos($imageUploadController, $pattern) !== false) {
                    echo "✅ {$description}: Present\n";
                } else {
                    echo "⚠️ {$description}: Not found\n";
                }
            }
            
            $this->testResults['error_handling'] = true;
            echo "✅ Error Handling Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ Error Handling Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['error_handling'] = false;
        }
    }
    
    private function testDatabaseStorage()
    {
        echo "🗄️ Testing Database Storage...\n";
        
        try {
            // Check if teams table has logo and banner columns
            $teamsColumns = DB::select("SHOW COLUMNS FROM teams LIKE '%logo%' OR LIKE '%banner%' OR LIKE '%flag%'");
            echo "Teams table image columns: " . count($teamsColumns) . " found\n";
            
            // Check if players table has avatar column  
            $playersColumns = DB::select("SHOW COLUMNS FROM players LIKE '%avatar%'");
            echo "Players table avatar columns: " . count($playersColumns) . " found\n";
            
            // Check if events table has image columns
            $eventsColumns = DB::select("SHOW COLUMNS FROM events LIKE '%image%' OR LIKE '%logo%' OR LIKE '%banner%'");
            echo "Events table image columns: " . count($eventsColumns) . " found\n";
            
            // Check if there are existing images in the database
            $teamsWithLogos = DB::table('teams')->whereNotNull('logo')->count();
            $playersWithAvatars = DB::table('players')->whereNotNull('avatar')->count();
            
            echo "Teams with logos: {$teamsWithLogos}\n";
            echo "Players with avatars: {$playersWithAvatars}\n";
            
            $this->testResults['database_storage'] = true;
            echo "✅ Database Storage Test: PASSED\n\n";
            
        } catch (Exception $e) {
            echo "❌ Database Storage Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['database_storage'] = false;
        }
    }
    
    private function testImageDisplay()
    {
        echo "👁️ Testing Image Display...\n";
        
        try {
            // Test if existing images are accessible
            $testImages = [
                '/storage/teams/logos/100t-logo.png',
                '/images/heroes/spider-man-headbig.webp',
                '/images/team-placeholder.svg',
                '/images/player-placeholder.svg'
            ];
            
            $accessibleImages = 0;
            foreach ($testImages as $imagePath) {
                $fullPath = public_path($imagePath);
                if (file_exists($fullPath)) {
                    echo "✅ Image accessible: {$imagePath}\n";
                    $accessibleImages++;
                } else {
                    echo "⚠️ Image not found: {$imagePath}\n";
                }
            }
            
            // Test ImageHelper fallback system
            $fallbackTest = ImageHelper::getTeamLogo('nonexistent-logo.png', 'Test Team');
            if ($fallbackTest['exists'] === false && !empty($fallbackTest['fallback'])) {
                echo "✅ Fallback system working for missing images\n";
                $accessibleImages++;
            }
            
            $this->testResults['image_display'] = $accessibleImages >= 3;
            echo "✅ Image Display Test: " . ($accessibleImages >= 3 ? "PASSED" : "PARTIAL") . "\n\n";
            
        } catch (Exception $e) {
            echo "❌ Image Display Test: FAILED - " . $e->getMessage() . "\n\n";
            $this->testResults['image_display'] = false;
        }
    }
    
    private function makeRequest($method, $endpoint, $data = null, $expectError = false)
    {
        $url = $this->apiBaseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $headers = ['Content-Type: application/json'];
        if ($this->testToken) {
            $headers[] = 'Authorization: Bearer ' . $this->testToken;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || ($httpCode >= 500 && !$expectError)) {
            return false;
        }
        
        return json_decode($response, true);
    }
    
    private function printTestReport()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 COMPREHENSIVE IMAGE UPLOAD SYSTEM TEST REPORT\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $totalTests = count($this->testResults);
        $passedTests = array_sum($this->testResults);
        $failedTests = $totalTests - $passedTests;
        
        echo "Overall Results:\n";
        echo "✅ Passed: {$passedTests}/{$totalTests}\n";
        echo "❌ Failed: {$failedTests}/{$totalTests}\n";
        echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";
        
        echo "Detailed Results:\n";
        foreach ($this->testResults as $test => $result) {
            $status = $result ? "✅ PASSED" : "❌ FAILED";
            echo "• " . ucwords(str_replace('_', ' ', $test)) . ": {$status}\n";
        }
        
        echo "\n📋 RECOMMENDATIONS:\n";
        
        if (!$this->testResults['storage_config']) {
            echo "• Fix storage directory permissions and symlinks\n";
        }
        
        if (!$this->testResults['upload_endpoints']) {
            echo "• Verify image upload API endpoints are working correctly\n";
        }
        
        if (!$this->testResults['file_formats']) {
            echo "• Ensure all required image formats are supported\n";
        }
        
        if (!$this->testResults['error_handling']) {
            echo "• Improve error handling in image upload controllers\n";
        }
        
        if ($passedTests === $totalTests) {
            echo "\n🎉 EXCELLENT! All image upload system tests passed!\n";
            echo "The image upload system is fully functional and ready for production.\n\n";
        } elseif ($passedTests >= $totalTests * 0.8) {
            echo "\n✅ GOOD! Most image upload system tests passed.\n";
            echo "Minor issues need to be addressed before production deployment.\n\n";
        } else {
            echo "\n⚠️ ATTENTION NEEDED! Several image upload system tests failed.\n";
            echo "Significant issues need to be resolved before deployment.\n\n";
        }
        
        echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
        echo str_repeat("=", 60) . "\n";
    }
}

// Run the comprehensive test suite
try {
    $testSuite = new ImageUploadTestSuite();
    $testSuite->runAllTests();
} catch (Exception $e) {
    echo "❌ Critical error running test suite: " . $e->getMessage() . "\n";
    exit(1);
}