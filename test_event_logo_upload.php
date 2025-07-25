#!/usr/bin/env php
<?php

// Test script for event logo upload

// Get auth token (you'll need to replace this with a valid admin token)
echo "Enter admin auth token: ";
$token = trim(fgets(STDIN));

if (empty($token)) {
    die("Error: Auth token is required\n");
}

// Event ID to test with
$eventId = 9; // Based on the logs showing event ID 9

// Create a test image
$testImagePath = '/tmp/test_logo.png';
$image = imagecreate(200, 200);
$bg = imagecolorallocate($image, 255, 0, 0); // Red background
$text_color = imagecolorallocate($image, 255, 255, 255);
imagestring($image, 5, 50, 90, 'TEST LOGO', $text_color);
imagepng($image, $testImagePath);
imagedestroy($image);

echo "Created test image at: $testImagePath\n";

// Test the upload
$url = "https://staging.mrvl.net/api/admin/events/$eventId/logo";

$curl = curl_init();

$cfile = new CURLFile($testImagePath, 'image/png', 'logo.png');
$data = ['logo' => $cfile];

curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        "Accept: application/json",
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_VERBOSE => true,
]);

echo "\nSending request to: $url\n";
echo "Headers: Authorization: Bearer [token], Accept: application/json\n\n";

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

// Clean up test image
unlink($testImagePath);

if ($error) {
    echo "CURL Error: $error\n";
    exit(1);
}

echo "HTTP Response Code: $httpCode\n";
echo "Response: $response\n\n";

$responseData = json_decode($response, true);
if ($responseData) {
    echo "Decoded Response:\n";
    print_r($responseData);
    
    if (isset($responseData['success']) && $responseData['success']) {
        echo "\n✅ SUCCESS: Logo uploaded successfully!\n";
        if (isset($responseData['data']['logo_url'])) {
            echo "Logo URL: " . $responseData['data']['logo_url'] . "\n";
        }
    } else {
        echo "\n❌ FAILED: " . ($responseData['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "\n❌ Failed to decode response\n";
}