<?php
header('Content-Type: application/json');

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Test TeamRankingController directly
use App\Http\Controllers\TeamRankingController;

$controller = new TeamRankingController();
$request = new \Illuminate\Http\Request(['region' => 'all']);

try {
    $response = $controller->index($request);
    echo $response->getContent();
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => get_class($e),
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}