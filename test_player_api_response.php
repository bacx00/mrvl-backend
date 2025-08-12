<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Get the PlayerController
$controller = new App\Http\Controllers\PlayerController();

// Test the show method
$response = $controller->show(405);

// Get the response content
$content = $response->getContent();
echo "API Response:\n";
echo $content;