<?php
// Simple test to check if PHP is working in the backend directory
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'PHP is working in the backend directory',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'working_directory' => getcwd()
]);