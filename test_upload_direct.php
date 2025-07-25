#!/usr/bin/env php
<?php
// Direct test of file upload functionality

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test storage paths
echo "Testing storage paths...\n";
echo "Storage path: " . storage_path() . "\n";
echo "Public storage: " . storage_path('app/public') . "\n";
echo "Events logos: " . storage_path('app/public/events/logos') . "\n\n";

// Check directories
$dirs = [
    'storage' => storage_path(),
    'app' => storage_path('app'),
    'public' => storage_path('app/public'),
    'events' => storage_path('app/public/events'),
    'logos' => storage_path('app/public/events/logos')
];

foreach ($dirs as $name => $path) {
    echo "$name: ";
    if (file_exists($path)) {
        echo "EXISTS";
        if (is_writable($path)) {
            echo " (WRITABLE)";
        } else {
            echo " (NOT WRITABLE)";
        }
        $perms = fileperms($path);
        echo " [" . substr(sprintf('%o', $perms), -4) . "]";
        $owner = posix_getpwuid(fileowner($path));
        echo " owner: " . $owner['name'];
    } else {
        echo "DOES NOT EXIST";
    }
    echo "\n";
}

// Test file creation
echo "\nTesting file creation...\n";
$testFile = storage_path('app/public/events/logos/test_' . time() . '.txt');
$result = file_put_contents($testFile, "Test content");
if ($result !== false) {
    echo "✅ Successfully created test file: $testFile\n";
    unlink($testFile);
} else {
    echo "❌ Failed to create test file\n";
}

// Check PHP upload settings
echo "\nPHP Upload Settings:\n";
echo "file_uploads: " . ini_get('file_uploads') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
echo "sys_temp_dir: " . sys_get_temp_dir() . "\n";

// Check temp directory
$tempDir = sys_get_temp_dir();
echo "\nTemp directory check:\n";
if (is_writable($tempDir)) {
    echo "✅ Temp directory is writable: $tempDir\n";
} else {
    echo "❌ Temp directory is NOT writable: $tempDir\n";
}