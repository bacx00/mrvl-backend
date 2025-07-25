#!/usr/bin/env php
<?php
// Simple storage test without Laravel bootstrap

$baseDir = __DIR__;
$storageDir = $baseDir . '/storage/app/public/events/logos';

echo "Testing storage directories...\n\n";

// Check directory existence and permissions
$dirsToCheck = [
    $baseDir . '/storage',
    $baseDir . '/storage/app',
    $baseDir . '/storage/app/public',
    $baseDir . '/storage/app/public/events',
    $storageDir
];

foreach ($dirsToCheck as $dir) {
    echo "Directory: " . str_replace($baseDir, '.', $dir) . "\n";
    if (file_exists($dir)) {
        $perms = fileperms($dir);
        $owner = posix_getpwuid(fileowner($dir));
        $group = posix_getgrgid(filegroup($dir));
        echo "  Exists: YES\n";
        echo "  Permissions: " . substr(sprintf('%o', $perms), -4) . "\n";
        echo "  Owner: " . $owner['name'] . "\n";
        echo "  Group: " . $group['name'] . "\n";
        echo "  Writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
    } else {
        echo "  Exists: NO\n";
        // Try to create it
        if (@mkdir($dir, 0775, true)) {
            echo "  Created: YES\n";
        } else {
            echo "  Created: NO (Error)\n";
        }
    }
    echo "\n";
}

// Test file creation
echo "Testing file creation in logos directory...\n";
$testFile = $storageDir . '/test_' . time() . '.txt';
if (file_put_contents($testFile, "Test content") !== false) {
    echo "✅ File created successfully: " . basename($testFile) . "\n";
    unlink($testFile);
} else {
    echo "❌ Failed to create file\n";
}

// Check PHP settings
echo "\nPHP Settings:\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "file_uploads: " . ini_get('file_uploads') . "\n";
echo "upload_tmp_dir: " . (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()) . "\n";

// Check temp directory
$tmpDir = sys_get_temp_dir();
echo "\nTemp directory: $tmpDir\n";
echo "Temp writable: " . (is_writable($tmpDir) ? 'YES' : 'NO') . "\n";