<?php
/**
 * Script to validate that monitoring scripts are properly configured
 */

echo "=== Monitoring Scripts Validation ===\n\n";

$scripts = [
    'database_activity_monitor.php',
    'query_performance_analyzer.php', 
    'test_data_cleanup.php',
    'database_integrity_validator.php',
    'comprehensive_crud_testing_suite.php'
];

$errors = [];
$warnings = [];

echo "1. Checking script files exist...\n";
foreach ($scripts as $script) {
    if (file_exists($script)) {
        echo "  ✓ {$script}\n";
    } else {
        echo "  ✗ {$script} - NOT FOUND\n";
        $errors[] = "Missing script: {$script}";
    }
}

echo "\n2. Checking script syntax...\n";
foreach ($scripts as $script) {
    if (file_exists($script)) {
        $output = [];
        $return_code = 0;
        exec("php -l {$script} 2>&1", $output, $return_code);
        
        if ($return_code === 0) {
            echo "  ✓ {$script} - Syntax OK\n";
        } else {
            echo "  ✗ {$script} - Syntax Error\n";
            $errors[] = "Syntax error in {$script}: " . implode("\n", $output);
        }
    }
}

echo "\n3. Checking required directories...\n";
$directories = ['bootstrap', 'app', 'config', 'database'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "  ✓ {$dir}/\n";
    } else {
        echo "  ✗ {$dir}/ - NOT FOUND\n";
        $errors[] = "Missing directory: {$dir}";
    }
}

echo "\n4. Checking database file...\n";
if (file_exists('database/database.sqlite')) {
    $size = filesize('database/database.sqlite');
    echo "  ✓ database/database.sqlite ({$size} bytes)\n";
} else {
    echo "  ✗ database/database.sqlite - NOT FOUND\n";
    $warnings[] = "Database file not found - scripts may not work correctly";
}

echo "\n5. Checking file permissions...\n";
foreach ($scripts as $script) {
    if (file_exists($script)) {
        if (is_readable($script)) {
            echo "  ✓ {$script} - Readable\n";
        } else {
            echo "  ✗ {$script} - NOT READABLE\n";
            $errors[] = "Cannot read {$script}";
        }
    }
}

echo "\n6. Checking write permissions for log files...\n";
$testLogFile = 'test_write_permission.tmp';
if (file_put_contents($testLogFile, 'test') !== false) {
    echo "  ✓ Can write log files\n";
    unlink($testLogFile);
} else {
    echo "  ✗ Cannot write log files\n";
    $errors[] = "Cannot write log files in current directory";
}

echo "\n7. Testing basic PHP functionality...\n";
try {
    $json = json_encode(['test' => 'data']);
    if ($json) {
        echo "  ✓ JSON encoding works\n";
    } else {
        echo "  ✗ JSON encoding failed\n";
        $errors[] = "JSON encoding not working";
    }
} catch (Exception $e) {
    echo "  ✗ JSON encoding error: " . $e->getMessage() . "\n";
    $errors[] = "JSON encoding error: " . $e->getMessage();
}

try {
    $timestamp = date('Y-m-d H:i:s');
    echo "  ✓ Date functions work ({$timestamp})\n";
} catch (Exception $e) {
    echo "  ✗ Date functions error: " . $e->getMessage() . "\n";
    $errors[] = "Date functions error: " . $e->getMessage();
}

echo "\n8. Testing SQLite extension...\n";
if (extension_loaded('sqlite3')) {
    echo "  ✓ SQLite3 extension loaded\n";
} else {
    echo "  ✗ SQLite3 extension not loaded\n";
    $warnings[] = "SQLite3 extension not available - database operations may fail";
}

if (extension_loaded('pdo_sqlite')) {
    echo "  ✓ PDO SQLite extension loaded\n";
} else {
    echo "  ✗ PDO SQLite extension not loaded\n";
    $warnings[] = "PDO SQLite extension not available - database operations may fail";
}

echo "\n9. README and documentation...\n";
if (file_exists('README_CRUD_TESTING.md')) {
    echo "  ✓ README_CRUD_TESTING.md exists\n";
} else {
    echo "  ✗ README_CRUD_TESTING.md missing\n";
    $warnings[] = "Documentation file missing";
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "VALIDATION SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if (empty($errors) && empty($warnings)) {
    echo "✓ ALL CHECKS PASSED - Scripts are ready for use!\n\n";
    echo "Usage examples:\n";
    echo "  php database_integrity_validator.php\n";
    echo "  php test_data_cleanup.php identify\n";
    echo "  php comprehensive_crud_testing_suite.php --help\n";
} else {
    if (!empty($errors)) {
        echo "✗ ERRORS FOUND:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
        echo "\n";
    }
    
    if (!empty($warnings)) {
        echo "⚠ WARNINGS:\n";
        foreach ($warnings as $warning) {
            echo "  - {$warning}\n";
        }
        echo "\n";
    }
    
    if (!empty($errors)) {
        echo "Please fix the errors before using the scripts.\n";
        exit(1);
    } else {
        echo "Scripts should work but may have limitations due to warnings.\n";
    }
}

echo "\nFor detailed usage instructions, see: README_CRUD_TESTING.md\n";