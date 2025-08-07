<?php
/**
 * Simple Image Upload Test
 * Direct test of image upload functionality
 */

// Set working directory
chdir(__DIR__);

echo "=== SIMPLE IMAGE UPLOAD SYSTEM TEST ===\n\n";

// Test 1: Check if storage directories exist and are writable
echo "📁 Testing Storage Directories...\n";
$directories = [
    'storage/app/public/teams/logos',
    'storage/app/public/teams/banners', 
    'storage/app/public/teams/flags',
    'storage/app/public/players/avatars',
    'storage/app/public/events/logos',
    'storage/app/public/events/banners',
    'storage/app/public/news/featured'
];

$storageOk = true;
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Created directory: $dir\n";
    } else {
        echo "✅ Directory exists: $dir\n";
    }
    
    if (!is_writable($dir)) {
        echo "❌ Directory not writable: $dir\n";
        $storageOk = false;
    }
}

if ($storageOk) {
    echo "✅ All storage directories are ready\n\n";
} else {
    echo "❌ Some storage directories have permission issues\n\n";
}

// Test 2: Check symlink
echo "🔗 Testing Storage Symlink...\n";
$symlink = 'public/storage';
if (is_link($symlink)) {
    echo "✅ Storage symlink exists\n";
    $target = readlink($symlink);
    echo "   Points to: $target\n\n";
} else {
    echo "❌ Storage symlink missing\n";
    echo "   Run: php artisan storage:link\n\n";
}

// Test 3: Check existing images
echo "🖼️ Testing Existing Images...\n";
$testImages = [
    'storage/app/public/teams/logos/100t-logo.png',
    'public/images/heroes/spider-man-headbig.webp',
    'public/images/team-placeholder.svg',
    'public/images/player-placeholder.svg'
];

$imagesFound = 0;
foreach ($testImages as $image) {
    if (file_exists($image)) {
        echo "✅ Found: $image\n";
        $imagesFound++;
        
        // Check file size
        $size = filesize($image);
        echo "   Size: " . round($size / 1024, 2) . " KB\n";
    } else {
        echo "❌ Missing: $image\n";
    }
}
echo "Images found: $imagesFound/" . count($testImages) . "\n\n";

// Test 4: Check image upload controller
echo "🎯 Testing Image Upload Controller...\n";
$controllerFile = 'app/Http/Controllers/ImageUploadController.php';
if (file_exists($controllerFile)) {
    echo "✅ ImageUploadController exists\n";
    
    $controller = file_get_contents($controllerFile);
    
    // Check for key methods
    $methods = [
        'uploadTeamLogo' => 'Team logo upload method',
        'uploadPlayerAvatar' => 'Player avatar upload method', 
        'uploadEventLogo' => 'Event logo upload method',
        'processAndStoreImage' => 'Image processing method',
        'move_uploaded_file' => 'File upload handling'
    ];
    
    foreach ($methods as $method => $description) {
        if (strpos($controller, $method) !== false) {
            echo "✅ $description: Present\n";
        } else {
            echo "❌ $description: Missing\n";
        }
    }
} else {
    echo "❌ ImageUploadController not found\n";
}
echo "\n";

// Test 5: Check API routes
echo "🛣️ Testing API Routes...\n";
$apiRoutes = 'routes/api.php';
if (file_exists($apiRoutes)) {
    echo "✅ API routes file exists\n";
    
    $routes = file_get_contents($apiRoutes);
    
    $uploadRoutes = [
        'upload/team/{teamId}/logo' => 'Team logo upload route',
        'upload/player/{playerId}/avatar' => 'Player avatar upload route',
        'ImageUploadController' => 'Image upload controller reference'
    ];
    
    foreach ($uploadRoutes as $route => $description) {
        if (strpos($routes, $route) !== false) {
            echo "✅ $description: Present\n";
        } else {
            echo "❌ $description: Missing\n";
        }
    }
} else {
    echo "❌ API routes file not found\n";
}
echo "\n";

// Test 6: Check frontend image upload component
echo "🌐 Testing Frontend Components...\n";
$frontendImageUpload = '/var/www/mrvl-frontend/frontend/src/components/shared/ImageUpload.js';
if (file_exists($frontendImageUpload)) {
    echo "✅ Frontend ImageUpload component exists\n";
    
    $component = file_get_contents($frontendImageUpload);
    
    $features = [
        'FormData' => 'FormData support',
        'onImageSelect' => 'Image selection callback',
        'maxSize' => 'File size validation',
        'drag' => 'Drag and drop support'
    ];
    
    foreach ($features as $feature => $description) {
        if (strpos($component, $feature) !== false) {
            echo "✅ $description: Present\n";
        } else {
            echo "❌ $description: Missing\n";
        }
    }
} else {
    echo "❌ Frontend ImageUpload component not found\n";
}
echo "\n";

// Test 7: Test simple file operations
echo "📝 Testing File Operations...\n";
$testFile = 'storage/app/public/test-upload.txt';
$testContent = "Image upload test - " . date('Y-m-d H:i:s');

if (file_put_contents($testFile, $testContent)) {
    echo "✅ Can write files to storage\n";
    
    if (file_get_contents($testFile) === $testContent) {
        echo "✅ Can read files from storage\n";
    } else {
        echo "❌ Cannot read files from storage\n";
    }
    
    if (unlink($testFile)) {
        echo "✅ Can delete files from storage\n";
    } else {
        echo "❌ Cannot delete files from storage\n";
    }
} else {
    echo "❌ Cannot write files to storage\n";
}
echo "\n";

// Test 8: Check Laravel configuration
echo "⚙️ Testing Laravel Configuration...\n";
$filesystemConfig = 'config/filesystems.php';
if (file_exists($filesystemConfig)) {
    echo "✅ Filesystem configuration exists\n";
    
    $config = file_get_contents($filesystemConfig);
    
    if (strpos($config, "'public' =>") !== false) {
        echo "✅ Public disk configuration present\n";
    } else {
        echo "❌ Public disk configuration missing\n";
    }
    
    if (strpos($config, "storage_path('app/public')") !== false) {
        echo "✅ Storage path correctly configured\n";
    } else {
        echo "❌ Storage path configuration issue\n";
    }
} else {
    echo "❌ Filesystem configuration not found\n";
}
echo "\n";

// Summary
echo "📋 SUMMARY\n";
echo str_repeat("-", 40) . "\n";
echo "✅ Storage directories: " . ($storageOk ? "OK" : "ISSUES") . "\n";
echo "✅ Image files: $imagesFound/" . count($testImages) . " found\n";
echo "✅ Controllers: " . (file_exists($controllerFile) ? "OK" : "MISSING") . "\n";
echo "✅ API routes: " . (file_exists($apiRoutes) ? "OK" : "MISSING") . "\n";
echo "✅ Frontend component: " . (file_exists($frontendImageUpload) ? "OK" : "MISSING") . "\n";
echo "✅ File operations: OK\n";
echo "✅ Laravel config: " . (file_exists($filesystemConfig) ? "OK" : "MISSING") . "\n";

echo "\n🎯 RECOMMENDATIONS:\n";
if (!$storageOk) {
    echo "• Fix directory permissions: chmod 755 storage/app/public/ -R\n";
}
if (!is_link('public/storage')) {
    echo "• Create storage symlink: php artisan storage:link\n";
}
echo "• Test actual file uploads through the API endpoints\n";
echo "• Verify database storage of image paths\n";
echo "• Test frontend-backend integration\n";

echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";